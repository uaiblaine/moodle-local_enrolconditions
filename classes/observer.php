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

namespace local_enrolconditions;

use local_enrolconditions\local\enforcer;
use local_enrolconditions\task\recheck_user;

/**
 * Observers of the enforcer: queue the re-checks on events and keep local_enrolconditions_state consistent.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /** @var int Seconds a process keeps the names of the observed events before reading the shared cache again. */
    protected const OBSERVED_TTL = 60;

    /** @var array|null Names of the observed events (without the leading backslash) as keys; null until loaded. */
    protected static ?array $observed = null;

    /** @var int When $observed was loaded. */
    protected static int $observedat = 0;

    /** @var int|null Depth of the recheck_user task running in this process; null outside one. */
    protected static ?int $depth = null;

    /** @var bool Whether an event during the running task was not queued because of the depth limit. */
    protected static bool $limitreached = false;

    /** @var int[] Open suspensions (state id by user enrolment id) that stay owned after the update in progress. */
    protected static array $keep = [];

    /**
     * Queues a re-check of the user of an event observed by the conditions of an instance in the events mode.
     *
     * Observes every event of the site, inside the caller's request and transaction, so it does the least possible:
     * an event nobody observes costs a lookup in a per-process map, and an observed one queues one adhoc task per
     * user (identical pending requests collapse into one). Nothing is evaluated or written here. Events raised while
     * a recheck_user task works (the enforcer's own changes among them) queue the next task one level deeper, up to
     * recheck_user::MAX_DEPTH, which bounds instances that keep changing each other.
     *
     * Core's dispatcher catches only \Exception: anything thrown here is reported and swallowed, so that a fault of
     * this plugin never aborts an unrelated caller.
     *
     * @param \core\event\base $event
     */
    public static function any_event(\core\event\base $event): void {
        global $CFG;
        try {
            if (!empty($CFG->upgraderunning) || during_initial_install()) {
                return;
            }
            if (!isset(self::observed_events()[ltrim($event->eventname, '\\')])) {
                return;
            }
            $userid = (int) ($event->relateduserid ?: $event->userid);
            if ($userid <= 0 || isguestuser($userid)) {
                return;
            }
            if (!recheck_user::queue($userid, self::$depth === null ? 0 : self::$depth + 1)) {
                self::$limitreached = true;
            }
        } catch (\Throwable $e) {
            debugging('local_enrolconditions could not queue the re-check of an event: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Names of the events observed by the instances in the events mode, kept for the request.
     *
     * A long process (cron) reads the shared cache again after OBSERVED_TTL seconds, so it sees what other nodes
     * changed; enforcer::purge_event_map() forgets it at once in this process.
     *
     * @return array Event names (without the leading backslash) as keys.
     */
    protected static function observed_events(): array {
        $now = time();
        if (self::$observed === null || $now - self::$observedat >= self::OBSERVED_TTL) {
            $enforcer = new enforcer();
            self::$observed = $enforcer->get_plugins() ? array_fill_keys(array_keys($enforcer->get_event_map()), true) : [];
            self::$observedat = $now;
        }
        return self::$observed;
    }

    /**
     * Forgets the names of the observed events kept by this process.
     */
    public static function reset_caches(): void {
        self::$observed = null;
    }

    /**
     * Runs the work of a recheck_user task at a depth, so the events it raises queue the next depth.
     *
     * @param int $depth Depth of the task.
     * @param callable $work
     * @return bool Whether an event was not queued because the next depth is beyond the limit.
     */
    public static function during_recheck(int $depth, callable $work): bool {
        $previousdepth = self::$depth;
        $previousflag = self::$limitreached;
        self::$depth = $depth;
        self::$limitreached = false;
        try {
            $work();
            return self::$limitreached;
        } finally {
            self::$depth = $previousdepth;
            self::$limitreached = $previousflag;
        }
    }

    /**
     * An enrolment instance was created or updated: the observed events may have changed.
     *
     * @param \core\event\base $event enrol_instance_created or enrol_instance_updated.
     */
    public static function enrol_instance_changed(\core\event\base $event): void {
        if (self::is_enforceable((string) ($event->other['enrol'] ?? ''))) {
            enforcer::purge_event_map();
        }
    }

    /**
     * Whether an enrolment method is handled by the enforcer, enabled or not.
     *
     * @param string $enrol
     * @return bool
     */
    protected static function is_enforceable(string $enrol): bool {
        return enforcer::is_enforceable_method($enrol);
    }

    /**
     * Open suspensions of a user enrolment.
     *
     * @param int $ueid
     * @return \stdClass[]
     */
    protected static function open_suspensions(int $ueid): array {
        global $DB;
        return $DB->get_records_select(
            enforcer::TABLE,
            'userenrolmentid = :ueid AND action = :action AND timeclosed IS NULL',
            ['ueid' => $ueid, 'action' => enforcer::STATE_SUSPEND]
        );
    }

    /**
     * Someone else is about to change an enrolment suspended by the enforcer.
     *
     * The record still has the previous timemodified here, and already the new status. The enforcer keeps the
     * suspension only when it still owned it and the enrolment stays suspended (for example, only its dates change).
     *
     * When the enforcer itself is writing, core dispatches this hook only because it is about to change the row:
     * that is the fact the enforcer records its state on (enforcer::record_applied()).
     *
     * @param \core_enrol\hook\before_user_enrolment_updated $hook
     */
    public static function before_user_enrolment_updated(\core_enrol\hook\before_user_enrolment_updated $hook): void {
        $ue = $hook->userenrolmentinstance;
        if (enforcer::is_acting()) {
            enforcer::record_applied($ue);
            return;
        }
        if (!self::is_enforceable((string) $hook->enrolinstance->enrol)) {
            return;
        }
        unset(self::$keep[(int) $ue->id]);
        foreach (self::open_suspensions((int) $ue->id) as $state) {
            $stillowned = (int) $ue->status === ENROL_USER_SUSPENDED
                && (int) $ue->timemodified === (int) $state->uetimemodified;
            if ($stillowned) {
                self::$keep[(int) $ue->id] = (int) $state->id;
            } else {
                enforcer::close($state, enforcer::CLOSE_EXTERNAL);
            }
        }
    }

    /**
     * Someone else changed an enrolment suspended by the enforcer.
     *
     * Suspensions kept by before_user_enrolment_updated() get the new timemodified. Any other open
     * suspension is no longer owned.
     *
     * @param \core\event\user_enrolment_updated $event
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event): void {
        global $DB;
        if (enforcer::is_acting() || !self::is_enforceable((string) ($event->other['enrol'] ?? ''))) {
            return;
        }
        $ueid = (int) $event->objectid;
        $keep = self::$keep[$ueid] ?? null;
        unset(self::$keep[$ueid]);
        $ue = $DB->get_record('user_enrolments', ['id' => $ueid]);
        foreach (self::open_suspensions($ueid) as $state) {
            if ($ue && enforcer::owns($state, $ue)) {
                // Unchanged since the enforcer acted: events triggered inside other observers are delivered
                // later, so this may be the enforcer's own suspension.
                continue;
            }
            if ($ue && (int) $state->id === $keep && (int) $ue->status === ENROL_USER_SUSPENDED) {
                $DB->set_field(enforcer::TABLE, 'uetimemodified', $ue->timemodified, ['id' => $state->id]);
            } else {
                enforcer::close($state, enforcer::CLOSE_EXTERNAL);
            }
        }
    }

    /**
     * The enrolment no longer exists, so its records go too.
     *
     * Only enrolments of enforceable methods have records. Deletions that fire no event are left to the task
     * purge_orphan_states.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        global $DB;
        if (self::is_enforceable((string) ($event->other['enrol'] ?? ''))) {
            $DB->delete_records(enforcer::TABLE, ['userenrolmentid' => $event->objectid]);
        }
    }

    /**
     * The instance no longer exists, so its records go too, and the observed events may have changed.
     *
     * @param \core\event\enrol_instance_deleted $event
     */
    public static function enrol_instance_deleted(\core\event\enrol_instance_deleted $event): void {
        global $DB;
        if (self::is_enforceable((string) ($event->other['enrol'] ?? ''))) {
            $DB->delete_records(enforcer::TABLE, ['instanceid' => $event->objectid]);
            enforcer::purge_event_map();
        }
    }
}
