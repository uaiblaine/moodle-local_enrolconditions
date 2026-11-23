<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_enrolconditions\local;

use enrol_plugin;
use local_enrolconditions\enforceable;
use local_enrolconditions\event\conditions_not_met;
use local_enrolconditions\event\user_enrolment_reactivated;
use local_enrolconditions\event\user_enrolment_suspended;
use local_enrolconditions\invalid_tree_exception;
use local_enrolconditions\observer;
use local_enrolconditions\output\failures;
use local_enrolconditions\tree;
use local_enrolconditions\tree_result;
use stdClass;

/**
 * Re-checks the conditions of existing enrolments and applies the action of their instance.
 *
 * Works on the instances of every enabled enrolment method that implements \local_enrolconditions\enforceable.
 *
 * Rules:
 * - An unknown result (unknown or disabled condition type, invalid tree) never changes anything.
 * - The enforcer only reactivates enrolments that it suspended itself, recorded in local_enrolconditions_state,
 *   and only while nobody else changed the enrolment since.
 * - A state row is written only when core applied the enforcer's own change (see update_status()).
 * - The action "none" only records the failure once per episode (met, then not met).
 *
 * Every change is decided under a core lock per user enrolment, on the row and the state read again under it,
 * and written in one transaction. A check that finds nothing to change takes no lock and writes nothing.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enforcer {
    /** @var string State table. */
    public const TABLE = 'local_enrolconditions_state';

    /** @var string Nothing changed. */
    public const OUTCOME_NOTHING = 'nothing';
    /** @var string The result is unknown, so nothing changed. */
    public const OUTCOME_UNKNOWN = 'unknown';
    /** @var string The failure was recorded (action none). */
    public const OUTCOME_RECORDED = 'recorded';
    /** @var string The enrolment was suspended. */
    public const OUTCOME_SUSPENDED = 'suspended';
    /** @var string The enrolment was removed. */
    public const OUTCOME_REMOVED = 'removed';
    /** @var string The enrolment was reactivated. */
    public const OUTCOME_REACTIVATED = 'reactivated';
    /** @var string A recorded failure was closed because the conditions are met again. */
    public const OUTCOME_RESOLVED = 'resolved';
    /** @var string Another process held the enrolment: not checked, the next check takes it again. */
    public const OUTCOME_SKIPPED = 'skipped';
    /** @var string The check threw; reported, nothing written, the next check takes it again. */
    public const OUTCOME_ERROR = 'error';

    /** @var string State action: only recorded. */
    public const STATE_NONE = 'none';
    /** @var string State action: suspended by the enforcer. */
    public const STATE_SUSPEND = 'suspend';

    /** @var string Closed because the enforcer reactivated the enrolment. */
    public const CLOSE_REACTIVATED = 'reactivated';
    /** @var string Closed because the conditions are met again. */
    public const CLOSE_MET = 'met';
    /** @var string Closed because someone else changed the enrolment. */
    public const CLOSE_EXTERNAL = 'external';
    /** @var string Closed because the configured action changed. */
    public const CLOSE_SUPERSEDED = 'superseded';

    /** @var string Lock type of the per-enrolment locks. */
    public const LOCK_TYPE = 'local_enrolconditions';

    /** @var int Seconds to wait for the lock of an enrolment another process is checking. */
    public const LOCK_TIMEOUT = 2;

    /** @var string Plugin setting with the position of the periodic check: "instance id,user id". */
    public const POSITION_SETTING = 'periodicposition';

    /** @var string Plan: reactivate the enrolment. */
    protected const DO_REACTIVATE = 'reactivate';
    /** @var string Plan: suspend the enrolment. */
    protected const DO_SUSPEND = 'suspend';
    /** @var string Plan: record the failure. */
    protected const DO_RECORD = 'record';
    /** @var string Plan: remove the enrolment. */
    protected const DO_REMOVE = 'remove';

    /** @var bool True while the enforcer itself is changing an enrolment. */
    protected static bool $acting = false;

    /** @var array|null Enrolment the core hook saw being written while acting: ['ueid' => int, 'status' => int]. */
    protected static ?array $applied = null;

    /** @var array|null Enrolment methods registered by unit tests, by name; null outside them. */
    protected static ?array $testplugins = null;

    /** @var array<string, enrol_plugin&enforceable>|null Enrolment methods, by name; null until loaded. */
    protected ?array $plugins;

    /** @var array Parsed trees by instance id: ['json' => ?string, 'tree' => ?tree], tree null when invalid. */
    protected array $trees = [];

    /** @var \core\lock\lock_factory|null Factory of the per-enrolment locks, once needed. */
    protected ?\core\lock\lock_factory $lockfactory = null;

    /**
     * Constructor.
     *
     * @param array|null $plugins Enrolment methods (enrol_plugin implementing enforceable) by name. Defaults to the
     *     enabled methods that implement enforceable.
     */
    public function __construct(?array $plugins = null) {
        $this->plugins = $plugins;
    }

    /**
     * Enrolment methods handled by this enforcer.
     *
     * @return array<string, enrol_plugin&enforceable> By name.
     */
    public function get_plugins(): array {
        if ($this->plugins === null) {
            $this->plugins = self::$testplugins ?? [];
            if (self::$testplugins === null) {
                foreach (enrol_get_plugins(true) as $name => $plugin) {
                    if ($plugin instanceof enforceable) {
                        $this->plugins[$name] = $plugin;
                    }
                }
            }
        }
        return $this->plugins;
    }

    /**
     * Enrolment method of an instance, when this enforcer handles it.
     *
     * @param string $enrol Name of the enrolment method.
     * @return (enrol_plugin&enforceable)|null
     */
    public function get_plugin(string $enrol): ?enforceable {
        return $this->get_plugins()[$enrol] ?? null;
    }

    /**
     * Whether an enrolment method implements enforceable, enabled or not.
     *
     * Observers that clean up after deletions use it: a disabled method's enrolments still have state rows.
     *
     * @param string $enrol Name of the enrolment method.
     * @return bool
     */
    public static function is_enforceable_method(string $enrol): bool {
        if ($enrol === '') {
            return false;
        }
        if (self::$testplugins !== null) {
            return isset(self::$testplugins[$enrol]);
        }
        return enrol_get_plugin($enrol) instanceof enforceable;
    }

    /**
     * Replaces the enrolment methods every new enforcer handles, in unit tests.
     *
     * Observers and tasks build their own enforcer; this lets a test give them a fake method.
     *
     * @param array|null $plugins Enrolment methods by name; null restores the enabled methods.
     * @throws \coding_exception Outside unit tests.
     */
    public static function register_plugins_for_testing(?array $plugins): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('register_plugins_for_testing() is only allowed in unit tests');
        }
        self::$testplugins = $plugins;
        self::purge_event_map();
    }

    /**
     * Whether the enforcer is changing an enrolment right now.
     *
     * Observers use it to tell the enforcer's own changes from changes made by others.
     *
     * @return bool
     */
    public static function is_acting(): bool {
        return self::$acting;
    }

    /**
     * Records that core is about to write an enrolment while the enforcer is acting.
     *
     * Called from the hook core_enrol\hook\before_user_enrolment_updated, which core dispatches only when it is
     * about to change the row: a status the row already had dispatches nothing. update_status() reads it back.
     *
     * @param stdClass $ue User enrolment as core is about to store it.
     */
    public static function record_applied(stdClass $ue): void {
        if (self::$acting) {
            self::$applied = ['ueid' => (int) $ue->id, 'status' => (int) $ue->status];
        }
    }

    /**
     * Evaluates a stored condition tree for a user, with the explanation of what is not met.
     *
     * A stored tree that is no longer valid counts as unknown, so it refuses new enrolments and changes nothing in
     * existing ones.
     *
     * @param string|null $json Tree as stored.
     * @param int $userid
     * @param stdClass $instance Enrolment instance, passed to the conditions.
     * @return tree_result|null Null when there are no conditions.
     */
    public static function evaluate(?string $json, int $userid, stdClass $instance): ?tree_result {
        try {
            $tree = tree::from_json($json);
        } catch (invalid_tree_exception $e) {
            debugging("Invalid condition tree in enrolment instance {$instance->id}: " . $e->getMessage(), DEBUG_DEVELOPER);
            return new tree_result(null, [[
                'message' => get_string('failureunknown', 'local_enrolconditions'),
                'children' => [],
            ]]);
        }
        if ($tree->is_empty()) {
            return null;
        }
        return $tree->evaluate($userid, $instance);
    }

    /**
     * Re-checks the enrolment of a user in an instance.
     *
     * @param stdClass $instance Enrolment instance.
     * @param int $userid
     * @return string One of the OUTCOME_ constants.
     */
    public function check_user(stdClass $instance, int $userid): string {
        global $DB;
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        if (!$ue) {
            return self::OUTCOME_NOTHING;
        }
        return $this->check_user_enrolment($instance, $ue);
    }

    /**
     * Re-checks one user enrolment.
     *
     * The record passed is only a hint: a change is decided on the row read again under the lock.
     *
     * @param stdClass $instance Enrolment instance.
     * @param stdClass $ue User enrolment record.
     * @return string One of the OUTCOME_ constants.
     * @throws \coding_exception When the enrolment is not of the instance.
     */
    public function check_user_enrolment(stdClass $instance, stdClass $ue): string {
        if ((int) $ue->enrolid !== (int) $instance->id) {
            throw new \coding_exception('The user enrolment does not belong to this enrolment instance.');
        }
        $opens = $this->get_open_states([(int) $ue->id])[(int) $ue->id] ?? [];
        return $this->check_loaded($instance, $ue, $opens);
    }

    /**
     * SQL that selects the enabled instances that use a check mode.
     *
     * @param int $mode One of the CHECK_ constants.
     * @param string|null $enrol Only the instances of this enrolment method.
     * @return array [sql where, params] over the alias e; the where is '1 = 0' when no instance can match.
     */
    protected function instances_in_mode_sql(int $mode, ?string $enrol = null): array {
        global $DB;
        $parts = [];
        $params = ['enabled' => ENROL_INSTANCE_ENABLED];
        $i = 0;
        foreach ($this->get_plugins() as $name => $plugin) {
            if ($enrol !== null && $name !== $enrol) {
                continue;
            }
            $i++;
            $params["enforceenrol$i"] = $name;
            $sql = $plugin->get_enforcement_mode_sql($mode);
            if ($sql !== null) {
                [$where, $pluginparams] = $sql;
                $parts[] = "(e.enrol = :enforceenrol$i AND ($where))";
                $params += $pluginparams;
                continue;
            }
            $ids = [];
            foreach ($DB->get_records('enrol', ['enrol' => $name, 'status' => ENROL_INSTANCE_ENABLED]) as $instance) {
                if (($plugin->get_enforcement_check_modes($instance) & $mode) === $mode) {
                    $ids[] = (int) $instance->id;
                }
            }
            if ($ids) {
                [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, "enforceid{$i}_");
                $parts[] = "(e.enrol = :enforceenrol$i AND e.id $insql)";
                $params += $inparams;
            }
        }
        if (!$parts) {
            return ['1 = 0', []];
        }
        return ['e.status = :enabled AND (' . implode(' OR ', $parts) . ')', $params];
    }

    /**
     * Re-checks the enrolments of a user in the instances that use a check mode.
     *
     * Enrolment methods call it with CHECK_LOGIN from sync_user_enrolments(), passing their own name; the task
     * recheck_user calls it with CHECK_EVENTS. An enrolment whose check throws is reported and skipped, so one
     * broken instance never blocks a login.
     *
     * @param int $userid
     * @param int $mode One of the CHECK_ constants.
     * @param string|null $enrol Only the instances of this enrolment method.
     * @param \progress_trace|null $trace Receives the changes and the errors.
     * @return string[] Outcome by user enrolment id.
     */
    public function check_user_in_mode(int $userid, int $mode, ?string $enrol = null, ?\progress_trace $trace = null): array {
        global $DB;
        [$where, $params] = $this->instances_in_mode_sql($mode, $enrol);
        if (!$params) {
            return [];
        }
        $ues = $DB->get_records_sql(
            "SELECT ue.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE $where AND ue.userid = :enforceuserid",
            $params + ['enforceuserid' => $userid]
        );
        return $this->check_rows($ues, $this->load_instances($ues), $trace);
    }

    /**
     * Re-checks the enrolments of a user in some instances.
     *
     * @param int $userid
     * @param int[] $instanceids
     * @param stdClass[] $instances Instance records already loaded, by id; the others are loaded in one query.
     * @return string[] Outcome by user enrolment id.
     */
    public function check_user_in_instances(int $userid, array $instanceids, array $instances = []): array {
        global $DB;
        if (!$instanceids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
        $ues = $DB->get_records_select('user_enrolments', "userid = :userid AND enrolid $insql", $params + ['userid' => $userid]);
        return $this->check_rows($ues, $this->load_instances($ues, $instances));
    }

    /**
     * Re-checks the enrolments of the instances with CHECK_PERIODIC, in chunks, until a time budget is spent.
     *
     * The instances are visited by id and the enrolments of each by user id, so every page is served by the unique
     * index on user_enrolments (enrolid, userid) without a sort. The position ("instance id,user id") is kept in the
     * plugin settings, shared by every node, and saved after each chunk, so a run on any node or in a fresh cron
     * container continues where the previous one stopped. A run stops when the budget is spent (after at least one
     * chunk) or when the cycle reaches the last instance; the next run then starts a new cycle.
     *
     * @param int $chunksize Enrolments per query.
     * @param int $timelimit Seconds after which no new chunk starts; 0 checks one chunk.
     * @param \progress_trace|null $trace Receives the changes and the errors.
     * @return string[] Outcome by user enrolment id.
     */
    public function check_periodic(int $chunksize, int $timelimit, ?\progress_trace $trace = null): array {
        global $DB;
        $chunksize = max(1, $chunksize);
        $deadline = time() + max(0, $timelimit);
        [$frominstance, $fromuser] = self::get_position();
        [$where, $params] = $this->instances_in_mode_sql(enforceable::CHECK_PERIODIC);
        $instances = [];
        if ($params) {
            $instances = $DB->get_records_sql(
                "SELECT e.* FROM {enrol} e WHERE $where AND e.id >= :enforcefrom ORDER BY e.id ASC",
                $params + ['enforcefrom' => $frominstance]
            );
        }

        $outcomes = [];
        foreach ($instances as $instance) {
            $instanceid = (int) $instance->id;
            $cursor = $instanceid === $frominstance ? $fromuser : 0;
            while (true) {
                $ues = $DB->get_records_select(
                    'user_enrolments',
                    'enrolid = :enrolid AND userid > :cursor',
                    ['enrolid' => $instanceid, 'cursor' => $cursor],
                    'userid ASC',
                    '*',
                    0,
                    $chunksize
                );
                if (!$ues) {
                    break;
                }
                $outcomes += $this->check_rows($ues, [$instanceid => $instance], $trace);
                $cursor = (int) end($ues)->userid;
                $more = count($ues) === $chunksize;
                if (time() >= $deadline) {
                    self::set_position($more ? $instanceid : $instanceid + 1, $more ? $cursor : 0);
                    return $outcomes;
                }
                if (!$more) {
                    break;
                }
                self::set_position($instanceid, $cursor);
            }
        }
        self::set_position(0, 0);
        return $outcomes;
    }

    /**
     * Position of the periodic check.
     *
     * @return int[] [instance id, user id]; the next enrolments checked are those of the instances from that id, and
     *     in that instance those of the users after that id.
     */
    public static function get_position(): array {
        $value = (string) get_config('local_enrolconditions', self::POSITION_SETTING);
        if (!preg_match('/^(\d+),(\d+)$/', $value, $matches)) {
            return [0, 0];
        }
        return [(int) $matches[1], (int) $matches[2]];
    }

    /**
     * Saves the position of the periodic check, unless it is already saved.
     *
     * @param int $instanceid
     * @param int $userid
     */
    protected static function set_position(int $instanceid, int $userid): void {
        if (self::get_position() !== [$instanceid, $userid]) {
            set_config(self::POSITION_SETTING, "$instanceid,$userid", 'local_enrolconditions');
        }
    }

    /**
     * Events observed by the instances with CHECK_EVENTS.
     *
     * Cached in a shared application cache; purged when an instance of an enforceable method is created, updated or
     * deleted, and when a condition type is enabled or disabled.
     *
     * @return array Instance ids by event class name, without the leading backslash.
     */
    public function get_event_map(): array {
        global $DB;
        $cache = \cache::make('local_enrolconditions', 'observedevents');
        $key = 'map_' . implode('_', array_keys($this->get_plugins()));
        $map = $cache->get($key);
        if ($map !== false) {
            return $map;
        }
        [$where, $params] = $this->instances_in_mode_sql(enforceable::CHECK_EVENTS);
        $instances = $params ? $DB->get_records_sql("SELECT e.* FROM {enrol} e WHERE $where", $params) : [];
        $map = [];
        foreach ($instances as $instance) {
            $plugin = $this->get_plugin((string) $instance->enrol);
            try {
                $tree = tree::from_json($plugin->get_enforcement_tree($instance));
            } catch (invalid_tree_exception $e) {
                continue;
            }
            foreach ($tree->get_observed_events() as $eventname) {
                $map[ltrim($eventname, '\\')][] = (int) $instance->id;
            }
        }
        $cache->set($key, $map);
        return $map;
    }

    /**
     * Forgets the map of observed events, on every node.
     *
     * Call it after anything that changes which events the instances observe: an instance of an enforceable method
     * created, updated or deleted, or a condition type enabled or disabled (the enable switch of the enrolcond
     * subplugins calls it).
     */
    public static function purge_event_map(): void {
        \cache::make('local_enrolconditions', 'observedevents')->purge();
        observer::reset_caches();
    }

    /**
     * Open state record of a user enrolment, the newest when there are several.
     *
     * @param int $ueid
     * @return stdClass|null
     */
    public function get_open_state(int $ueid): ?stdClass {
        $records = $this->get_open_states([$ueid])[$ueid] ?? [];
        return $records ? reset($records) : null;
    }

    /**
     * Open state records of some user enrolments, in one query.
     *
     * @param int[] $ueids
     * @return array Lists of records, newest first, by user enrolment id; enrolments without one are absent.
     */
    public function get_open_states(array $ueids): array {
        global $DB;
        if (!$ueids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ueids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(self::TABLE, "userenrolmentid $insql AND timeclosed IS NULL", $params, 'id DESC');
        $states = [];
        foreach ($records as $record) {
            $states[(int) $record->userenrolmentid][] = $record;
        }
        return $states;
    }

    /**
     * Whether the enforcer still owns the state of the enrolment.
     *
     * A suspension is owned while the enrolment is suspended and nobody changed it since.
     *
     * @param stdClass $state
     * @param stdClass $ue
     * @return bool
     */
    public static function owns(stdClass $state, stdClass $ue): bool {
        if ($state->action === self::STATE_NONE) {
            return true;
        }
        return (int) $ue->status === ENROL_USER_SUSPENDED
            && (int) $ue->timemodified === (int) $state->uetimemodified;
    }

    /**
     * Closes a state record.
     *
     * @param stdClass $state
     * @param string $reason One of the CLOSE_ constants.
     */
    public static function close(stdClass $state, string $reason): void {
        global $DB;
        $update = (object) [
            'id' => $state->id,
            'timeclosed' => time(),
            'closereason' => $reason,
        ];
        if ($reason === self::CLOSE_REACTIVATED) {
            $update->timereactivated = $update->timeclosed;
        }
        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Loads the instances of some enrolments in one query.
     *
     * @param stdClass[] $ues User enrolments.
     * @param stdClass[] $instances Instances already loaded, by id.
     * @return stdClass[] Instances by id.
     */
    protected function load_instances(array $ues, array $instances = []): array {
        global $DB;
        $missing = [];
        foreach ($ues as $ue) {
            if (!isset($instances[$ue->enrolid])) {
                $missing[(int) $ue->enrolid] = (int) $ue->enrolid;
            }
        }
        if ($missing) {
            $instances += $DB->get_records_list('enrol', 'id', array_values($missing));
        }
        return $instances;
    }

    /**
     * Re-checks some enrolments, reading their open states in one query.
     *
     * A check that throws is reported (debugging and the trace) and the next one goes on, so one broken
     * enrolment or instance never stops a batch, a login or a task; nothing it wrote stays, since every write
     * runs in a transaction. Inside a transaction the caller opened the exception is rethrown: the rollback
     * already doomed that transaction.
     *
     * @param stdClass[] $ues User enrolments.
     * @param stdClass[] $instances Their instances, by id.
     * @param \progress_trace|null $trace
     * @return string[] Outcome by user enrolment id.
     */
    protected function check_rows(array $ues, array $instances, ?\progress_trace $trace = null): array {
        global $DB;
        $opens = $this->get_open_states(array_map(fn(stdClass $ue): int => (int) $ue->id, array_values($ues)));
        $outcomes = [];
        foreach ($ues as $ue) {
            $instance = $instances[$ue->enrolid] ?? null;
            if (!$instance) {
                continue;
            }
            try {
                $outcome = $this->check_loaded($instance, $ue, $opens[(int) $ue->id] ?? []);
            } catch (\Throwable $e) {
                if ($DB->is_transaction_started()) {
                    throw $e;
                }
                $outcome = self::OUTCOME_ERROR;
                $message = "Re-check of the user enrolment {$ue->id} (user {$ue->userid}, instance {$ue->enrolid}) failed: "
                    . $e->getMessage();
                debugging($message, DEBUG_DEVELOPER);
                if ($trace) {
                    $trace->output($message);
                }
            }
            $outcomes[(int) $ue->id] = $outcome;
            if ($trace && $outcome !== self::OUTCOME_NOTHING && $outcome !== self::OUTCOME_ERROR) {
                $trace->output("User enrolment {$ue->id} (user {$ue->userid}, instance {$ue->enrolid}): {$outcome}");
            }
        }
        return $outcomes;
    }

    /**
     * Re-checks one enrolment whose open states were already read.
     *
     * Decides first on what was loaded, without a lock: when that decision writes nothing (the usual case), it is
     * the answer. Otherwise the decision is taken again under the lock, on fresh data.
     *
     * @param stdClass $instance
     * @param stdClass $ue
     * @param stdClass[] $opens Open states of the enrolment, newest first.
     * @return string One of the OUTCOME_ constants.
     */
    protected function check_loaded(stdClass $instance, stdClass $ue, array $opens): string {
        $plugin = $this->get_plugin((string) $instance->enrol);
        if (!$plugin || (int) $instance->status !== ENROL_INSTANCE_ENABLED) {
            return self::OUTCOME_NOTHING;
        }
        $met = $this->evaluate_value($plugin, $instance, (int) $ue->userid);
        if ($met === null) {
            return self::OUTCOME_UNKNOWN;
        }
        $plan = $this->plan($plugin, $instance, $ue, $opens, $met);
        if ($plan->do === null && $plan->close === null) {
            return $plan->outcome;
        }
        return $this->decide_and_write($plugin, $instance, (int) $ue->id);
    }

    /**
     * Takes the lock of an enrolment, decides again on the row and the states read under it, and writes.
     *
     * @param enforceable $plugin Enrolment method (an enrol_plugin).
     * @param stdClass $instance
     * @param int $ueid
     * @return string One of the OUTCOME_ constants.
     */
    protected function decide_and_write(enforceable $plugin, stdClass $instance, int $ueid): string {
        global $DB;
        $this->lockfactory ??= \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE);
        $lock = $this->lockfactory->get_lock('ue_' . $ueid, self::LOCK_TIMEOUT);
        if (!$lock) {
            return self::OUTCOME_SKIPPED;
        }
        try {
            $ue = $DB->get_record('user_enrolments', ['id' => $ueid]);
            if (!$ue || (int) $ue->enrolid !== (int) $instance->id) {
                return self::OUTCOME_NOTHING;
            }
            $met = $this->evaluate_value($plugin, $instance, (int) $ue->userid);
            if ($met === null) {
                return self::OUTCOME_UNKNOWN;
            }
            $opens = $this->get_open_states([$ueid])[$ueid] ?? [];
            $plan = $this->plan($plugin, $instance, $ue, $opens, $met);
            if ($plan->do === null && $plan->close === null) {
                return $plan->outcome;
            }

            $reason = '';
            if ($plan->do === self::DO_SUSPEND || $plan->do === self::DO_RECORD) {
                // The explanation is built only now, when a new action is about to be recorded.
                $result = $this->get_tree($plugin, $instance)->evaluate((int) $ue->userid, $instance);
                if (!$result->is_not_met()) {
                    return $result->is_unknown() ? self::OUTCOME_UNKNOWN : self::OUTCOME_NOTHING;
                }
                $reason = trim((new failures($result, ''))->to_text());
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                $outcome = $this->write($plugin, $instance, $ue, $opens, $plan, $reason);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $outcome;
        } finally {
            $lock->release();
        }
    }

    /**
     * Parsed tree of an instance, kept for the enforcer's lifetime while the stored JSON stays the same.
     *
     * @param enforceable $plugin
     * @param stdClass $instance
     * @return tree|null Null when the stored tree is invalid.
     */
    protected function get_tree(enforceable $plugin, stdClass $instance): ?tree {
        $json = $plugin->get_enforcement_tree($instance);
        $id = (int) $instance->id;
        if (!isset($this->trees[$id]) || $this->trees[$id]['json'] !== $json) {
            try {
                $tree = tree::from_json($json);
            } catch (invalid_tree_exception $e) {
                debugging("Invalid condition tree in enrolment instance {$id}: " . $e->getMessage(), DEBUG_DEVELOPER);
                $tree = null;
            }
            $this->trees[$id] = ['json' => $json, 'tree' => $tree];
        }
        return $this->trees[$id]['tree'];
    }

    /**
     * Whether a user meets the conditions of an instance, without explanations.
     *
     * @param enforceable $plugin
     * @param stdClass $instance
     * @param int $userid
     * @return bool|null Null when unknown; true without conditions.
     */
    protected function evaluate_value(enforceable $plugin, stdClass $instance, int $userid): ?bool {
        $tree = $this->get_tree($plugin, $instance);
        if ($tree === null) {
            return null;
        }
        if ($tree->is_empty()) {
            return true;
        }
        return $tree->evaluate($userid, $instance, false)->get_value();
    }

    /**
     * Decides what to do with an enrolment, without writing anything.
     *
     * @param enforceable $plugin Enrolment method (an enrol_plugin).
     * @param stdClass $instance
     * @param stdClass $ue
     * @param stdClass[] $opens Open states, newest first; the newest decides, all are closed together.
     * @param bool $met Whether the conditions are met.
     * @return stdClass {outcome: string, close: ?string, do: ?string}; nothing to write when close and do are null.
     */
    protected function plan(enforceable $plugin, stdClass $instance, stdClass $ue, array $opens, bool $met): stdClass {
        $plan = (object) ['outcome' => self::OUTCOME_NOTHING, 'close' => null, 'do' => null];
        $open = $opens ? reset($opens) : null;

        if ($met) {
            if (!$open) {
                return $plan;
            }
            if ($open->action === self::STATE_NONE) {
                $plan->outcome = self::OUTCOME_RESOLVED;
                $plan->close = self::CLOSE_MET;
            } else if (!self::owns($open, $ue)) {
                // Someone else changed the enrolment after the enforcer suspended it: it is not ours any more.
                $plan->close = self::CLOSE_EXTERNAL;
            } else if ($plugin->get_enforcement_reactivate($instance)) {
                $plan->do = self::DO_REACTIVATE;
            }
            // Otherwise kept open, so the enrolment can still be reactivated if the setting is enabled later.
            return $plan;
        }

        $action = $plugin->get_enforcement_action($instance);
        if (!in_array($action, [enforceable::ACTION_NONE, enforceable::ACTION_SUSPEND, enforceable::ACTION_REMOVE], true)) {
            $action = enforceable::ACTION_SUSPEND;
        }
        if ($open) {
            $owned = self::owns($open, $ue);
            $same = ($open->action === self::STATE_SUSPEND && $action === enforceable::ACTION_SUSPEND)
                || ($open->action === self::STATE_NONE && $action === enforceable::ACTION_NONE);
            // An owned suspension stays when the action becomes "none": the enforcer does not reactivate on failure.
            if ($owned && ($same || $action === enforceable::ACTION_NONE)) {
                return $plan;
            }
            $plan->close = $owned ? self::CLOSE_SUPERSEDED : self::CLOSE_EXTERNAL;
        }
        if ($action === enforceable::ACTION_REMOVE) {
            $plan->do = self::DO_REMOVE;
        } else if ($action === enforceable::ACTION_NONE) {
            $plan->do = self::DO_RECORD;
        } else if ((int) $ue->status !== ENROL_USER_SUSPENDED) {
            $plan->do = self::DO_SUSPEND;
        }
        // A suspension made by someone else is never taken over.
        return $plan;
    }

    /**
     * Writes a plan, inside the caller's transaction.
     *
     * A state row is written, and an event fired, only when core applied the enforcer's own change.
     *
     * @param enforceable $plugin Enrolment method (an enrol_plugin).
     * @param stdClass $instance
     * @param stdClass $ue Enrolment as read under the lock.
     * @param stdClass[] $opens Open states, newest first.
     * @param stdClass $plan From plan().
     * @param string $reason Explanation of the failure, for a new state row.
     * @return string One of the OUTCOME_ constants.
     */
    protected function write(
        enforceable $plugin,
        stdClass $instance,
        stdClass $ue,
        array $opens,
        stdClass $plan,
        string $reason
    ): string {
        global $DB;
        if ($plan->do === self::DO_REACTIVATE) {
            $applied = $this->update_status($plugin, $instance, $ue, ENROL_USER_ACTIVE);
            $this->close_all($opens, $applied ? self::CLOSE_REACTIVATED : self::CLOSE_EXTERNAL);
            if (!$applied) {
                return self::OUTCOME_NOTHING;
            }
            user_enrolment_reactivated::create_from_state($instance, $ue, (int) reset($opens)->id)->trigger();
            return self::OUTCOME_REACTIVATED;
        }

        if ($plan->close !== null) {
            $this->close_all($opens, $plan->close);
        }
        switch ($plan->do) {
            case self::DO_REMOVE:
                conditions_not_met::create_from_enrolment($instance, $ue, 'remove')->trigger();
                self::$acting = true;
                try {
                    $plugin->unenrol_user($instance, (int) $ue->userid);
                } finally {
                    self::$acting = false;
                }
                return self::OUTCOME_REMOVED;
            case self::DO_RECORD:
                $stateid = $this->insert_state($instance, $ue, self::STATE_NONE, $reason);
                conditions_not_met::create_from_enrolment($instance, $ue, 'none', $stateid)->trigger();
                return self::OUTCOME_RECORDED;
            case self::DO_SUSPEND:
                if (!$this->update_status($plugin, $instance, $ue, ENROL_USER_SUSPENDED)) {
                    // Someone else suspended it first: the suspension is theirs, and nothing is recorded.
                    return self::OUTCOME_NOTHING;
                }
                $ue = $DB->get_record('user_enrolments', ['id' => $ue->id], '*', MUST_EXIST);
                $stateid = $this->insert_state($instance, $ue, self::STATE_SUSPEND, $reason);
                user_enrolment_suspended::create_from_state($instance, $ue, $stateid)->trigger();
                return self::OUTCOME_SUSPENDED;
        }
        return $plan->outcome;
    }

    /**
     * Closes every open state of an enrolment; there is normally one, older releases could leave duplicates.
     *
     * @param stdClass[] $opens
     * @param string $reason One of the CLOSE_ constants.
     */
    protected function close_all(array $opens, string $reason): void {
        foreach ($opens as $open) {
            self::close($open, $reason);
        }
    }

    /**
     * Changes the status of an enrolment, marking the change as the enforcer's own.
     *
     * core's update_user_enrol() returns silently when the row already has the status; it dispatches
     * before_user_enrolment_updated only when it is about to write, and the observer passes that to
     * record_applied(). That record, not the request, is what tells whether the change happened.
     *
     * @param enforceable $plugin Enrolment method (an enrol_plugin).
     * @param stdClass $instance
     * @param stdClass $ue
     * @param int $status
     * @return bool Whether core applied this status to this enrolment.
     */
    protected function update_status(enforceable $plugin, stdClass $instance, stdClass $ue, int $status): bool {
        self::$acting = true;
        self::$applied = null;
        try {
            $plugin->update_user_enrol($instance, (int) $ue->userid, $status);
            $applied = self::$applied;
        } finally {
            self::$acting = false;
            self::$applied = null;
        }
        return $applied !== null && $applied['ueid'] === (int) $ue->id && $applied['status'] === $status;
    }

    /**
     * Inserts an open state record.
     *
     * @param stdClass $instance
     * @param stdClass $ue User enrolment, as it is after the action.
     * @param string $action One of the STATE_ constants.
     * @param string $reason
     * @return int Id of the record.
     */
    protected function insert_state(stdClass $instance, stdClass $ue, string $action, string $reason): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'enrol' => $instance->enrol,
            'userenrolmentid' => $ue->id,
            'instanceid' => $instance->id,
            'action' => $action,
            'reason' => $reason,
            'timesuspended' => time(),
            'uetimemodified' => (int) $ue->timemodified,
            'timereactivated' => null,
            'timeclosed' => null,
            'closereason' => null,
        ]);
    }
}
