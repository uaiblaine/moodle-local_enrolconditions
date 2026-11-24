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

use local_enrolconditions\condition\manager;
use local_enrolconditions\local\enforcer;
use local_enrolconditions\output\enforcement_form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/fake_condition.php');
require_once(__DIR__ . '/fixtures/fake_enforceable.php');
require_once(__DIR__ . '/fixtures/refusing_lock_factory.php');
require_once(__DIR__ . '/fixtures/scripted_condition.php');

/**
 * Tests of the generic enforcer with an enrolment method other than enrol_conditional.
 *
 * The fake enrolment method keeps its configuration in other columns and lets the enforcer filter the instances
 * itself; the fake condition is met when the user id is in config['userids'].
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enforcer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(enforcement_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\user_enrolment_suspended::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\user_enrolment_reactivated::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\conditions_not_met::class)]
final class enforcer_test extends \advanced_testcase {
    /** @var \local_enrolconditions_fake_enforceable */
    protected \local_enrolconditions_fake_enforceable $plugin;

    /** @var enforcer */
    protected enforcer $enforcer;

    /** @var \stdClass Enrolled user. */
    protected \stdClass $user;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        manager::register_for_testing('scripted', new fixtures\scripted_condition());
        fixtures\scripted_condition::$calls = 0;
        $this->plugin = new \local_enrolconditions_fake_enforceable();
        // Observers and the hook callback build their own enforcer: they see the fake method too.
        enforcer::register_plugins_for_testing(['manual' => $this->plugin]);
        $this->enforcer = new enforcer(['manual' => $this->plugin]);
        $this->user = $this->getDataGenerator()->create_user();
    }

    #[\Override]
    protected function tearDown(): void {
        enforcer::register_plugins_for_testing(null);
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Creates an instance in a new course whose tree accepts the given users, and enrols the test user.
     *
     * @param int $action One of the enforceable::ACTION_ constants.
     * @param int[] $allowed Users that meet the conditions.
     * @param int $modes Check modes.
     * @param bool $reactivate
     * @return \stdClass Instance.
     */
    protected function create_instance(int $action, array $allowed, int $modes = 0, bool $reactivate = true): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        // Manual enrolments allow one instance per course.
        $DB->delete_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $id = $this->plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customtext1' => $this->tree($allowed),
            'customint7' => $modes,
            'customint8' => $action | ($reactivate ? 16 : 0),
        ]);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->plugin->enrol_user($instance, $this->user->id);
        return $instance;
    }

    /**
     * Condition tree with the fake condition.
     *
     * @param int[] $allowed
     * @return string
     */
    protected function tree(array $allowed): string {
        return json_encode(['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => $allowed]]]]);
    }

    /**
     * Changes the tree of an instance.
     *
     * @param \stdClass $instance Changed in place.
     * @param int[] $allowed
     */
    protected function set_allowed(\stdClass $instance, array $allowed): void {
        global $DB;
        $instance->customtext1 = $this->tree($allowed);
        $DB->set_field('enrol', 'customtext1', $instance->customtext1, ['id' => $instance->id]);
    }

    /**
     * Enrols a new user in an instance.
     *
     * @param \stdClass $instance
     * @return int User id.
     */
    protected function enrol_new_user(\stdClass $instance): int {
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $user->id);
        return (int) $user->id;
    }

    /**
     * User enrolment record of the test user.
     *
     * @param \stdClass $instance
     * @return \stdClass
     */
    protected function ue(\stdClass $instance): \stdClass {
        global $DB;
        return $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $this->user->id], '*', MUST_EXIST);
    }

    /**
     * State records of an instance, oldest first.
     *
     * @param \stdClass $instance
     * @param bool $openonly
     * @return \stdClass[]
     */
    protected function states(\stdClass $instance, bool $openonly = false): array {
        global $DB;
        $select = 'instanceid = :id' . ($openonly ? ' AND timeclosed IS NULL' : '');
        return array_values($DB->get_records_select(enforcer::TABLE, $select, ['id' => $instance->id], 'id ASC'));
    }

    /**
     * Status of the user enrolment.
     *
     * @param \stdClass $instance
     * @return int|null Null when there is no enrolment.
     */
    protected function ue_status(\stdClass $instance): ?int {
        global $DB;
        $status = $DB->get_field('user_enrolments', 'status', ['enrolid' => $instance->id, 'userid' => $this->user->id]);
        return $status === false ? null : (int) $status;
    }

    public function test_suspends_and_reactivates(): void {
        global $DB;
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);

        $sink = $this->redirectEvents();
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user($instance, $this->user->id));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\user_enrolment_suspended));
        $sink->close();
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance));
        $state = $DB->get_record(enforcer::TABLE, ['instanceid' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('manual', $state->enrol);
        $this->assertSame(enforcer::STATE_SUSPEND, $state->action);
        $this->assertStringContainsString('Not a listed user', $state->reason);
        $this->assertCount(1, $events);
        $this->assertSame('manual', $events[0]->other['enrol']);
        $this->assertNotEmpty($events[0]->get_name());

        $this->set_allowed($instance, [(int) $this->user->id]);
        $sink = $this->redirectEvents();
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($instance, $this->user->id));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\user_enrolment_reactivated));
        $sink->close();
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
        $this->assertCount(1, $events);
        $this->assertSame(enforcer::CLOSE_REACTIVATED, $DB->get_field(enforcer::TABLE, 'closereason', ['id' => $state->id]));
    }

    public function test_does_not_reactivate_suspension_of_others(): void {
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->plugin->update_user_enrol($instance, $this->user->id, ENROL_USER_SUSPENDED);

        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->set_allowed($instance, [(int) $this->user->id]);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance));
    }

    public function test_records_and_removes(): void {
        $recorded = $this->create_instance(enforceable::ACTION_NONE, []);
        $this->assertSame(enforcer::OUTCOME_RECORDED, $this->enforcer->check_user($recorded, $this->user->id));
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($recorded, $this->user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($recorded));

        $removed = $this->create_instance(enforceable::ACTION_REMOVE, []);
        $sink = $this->redirectEvents();
        $this->assertSame(enforcer::OUTCOME_REMOVED, $this->enforcer->check_user($removed, $this->user->id));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\conditions_not_met));
        $sink->close();
        $this->assertNull($this->ue_status($removed));
        $this->assertSame('remove', $events[0]->other['action']);
    }

    public function test_unknown_and_unhandled_change_nothing(): void {
        global $DB;
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $DB->set_field('enrol', 'customtext1', '{"op":', ['id' => $instance->id]);
        $instance->customtext1 = '{"op":';
        $this->assertSame(enforcer::OUTCOME_UNKNOWN, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertDebuggingCalled();

        // An enforcer without the method leaves its enrolments alone.
        $this->set_allowed($instance, []);
        $this->assertSame(enforcer::OUTCOME_NOTHING, (new enforcer([]))->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
        $this->assertNull(enforcer::evaluate('', $this->user->id, $instance));
    }

    public function test_check_user_in_mode_filters_instances(): void {
        $login = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_LOGIN);
        $periodic = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_PERIODIC);

        $this->assertSame([], $this->enforcer->check_user_in_mode($this->user->id, enforceable::CHECK_LOGIN, 'self'));
        $outcomes = $this->enforcer->check_user_in_mode($this->user->id, enforceable::CHECK_LOGIN, 'manual');
        $this->assertSame([enforcer::OUTCOME_SUSPENDED], array_values($outcomes));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($login));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($periodic));
    }

    public function test_event_map(): void {
        $events = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_EVENTS);
        $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_LOGIN);
        enforcer::purge_event_map();
        $this->assertSame(['core\event\user_updated' => [(int) $events->id]], $this->enforcer->get_event_map());
        $this->assertSame([], (new enforcer([]))->get_event_map());
    }

    public function test_form_submitted_values(): void {
        $fields = ['conditionaction' => '2', 'checkmodeevents' => '1', 'checkmodelogin' => '0', 'name' => 'x'];
        $submitted = enforcement_form::get_submitted($fields, enforceable::ACTION_SUSPEND, true, enforceable::CHECK_LOGIN);
        $this->assertSame([
            'action' => enforceable::ACTION_REMOVE,
            'reactivate' => true,
            'modes' => enforceable::CHECK_ENROL | enforceable::CHECK_EVENTS,
            'sent' => true,
        ], $submitted);
        $this->assertSame(['name' => 'x'], $fields);

        $fields = [];
        $this->assertFalse(enforcement_form::get_submitted($fields, 0, false, 0)['sent']);
        $this->assertCount(3, enforcement_form::get_action_options());
    }

    public function test_stale_snapshot_does_not_claim_a_suspension_made_by_someone_else(): void {
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $snapshot = $this->ue($instance);
        // A teacher suspends the enrolment after the enforcer loaded it.
        $this->plugin->update_user_enrol($instance, $this->user->id, ENROL_USER_SUSPENDED);

        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user_enrolment($instance, $snapshot));
        $this->assertSame([], $this->states($instance));

        // Met again, with reactivation on: the teacher's suspension stays.
        $this->set_allowed($instance, [(int) $this->user->id]);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance));
    }

    public function test_accurate_snapshot_suspends_owns_and_reactivates(): void {
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $snapshot = $this->ue($instance);

        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user_enrolment($instance, $snapshot));
        $states = $this->states($instance, true);
        $this->assertCount(1, $states);
        $this->assertTrue(enforcer::owns($states[0], $this->ue($instance)));

        $this->set_allowed($instance, [(int) $this->user->id]);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
    }

    public function test_second_checker_with_an_older_snapshot_keeps_the_ownership(): void {
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $snapshot = $this->ue($instance);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user($instance, $this->user->id));

        // Another node loaded the enrolment before the suspension and checks it now.
        $this->assertSame(enforcer::OUTCOME_NOTHING, (new enforcer(['manual' => $this->plugin]))
            ->check_user_enrolment($instance, $snapshot));
        $states = $this->states($instance);
        $this->assertCount(1, $states);
        $this->assertNull($states[0]->timeclosed);

        $this->set_allowed($instance, [(int) $this->user->id]);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($instance, $this->user->id));
    }

    /**
     * Enforcer that runs a callback right before it asks core to change a status, as another writer would.
     *
     * @param callable|null $before
     * @return enforcer
     */
    protected function racing_enforcer(?callable $before): enforcer {
        return new class (['manual' => $this->plugin], $before) extends enforcer {
            /**
             * Constructor.
             *
             * @param array $plugins
             * @param callable|null $before
             */
            public function __construct(array $plugins, /** @var callable|null */ protected $before) {
                parent::__construct($plugins);
            }

            #[\Override]
            protected function update_status(enforceable $plugin, \stdClass $instance, \stdClass $ue, int $status): bool {
                if ($this->before) {
                    ($this->before)();
                }
                return parent::update_status($plugin, $instance, $ue, $status);
            }
        };
    }

    public function test_suspension_not_applied_by_core_is_not_recorded(): void {
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $teacher = fn() => $this->plugin->update_user_enrol($instance, $this->user->id, ENROL_USER_SUSPENDED);

        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->racing_enforcer($teacher)->check_user($instance, $this->user->id));
        $this->assertSame([], $this->states($instance));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance));

        // Control: without the other writer the same enforcer suspends and records.
        $other = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->racing_enforcer(null)->check_user($other, $this->user->id));
        $this->assertCount(1, $this->states($other, true));
    }

    public function test_reactivation_not_applied_by_core_closes_as_external(): void {
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $this->set_allowed($instance, [(int) $this->user->id]);
        $teacher = fn() => $this->plugin->update_user_enrol($instance, $this->user->id, ENROL_USER_ACTIVE);

        $sink = $this->redirectEvents();
        $outcome = $this->racing_enforcer($teacher)->check_user($instance, $this->user->id);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof event\user_enrolment_reactivated);
        $sink->close();
        $this->assertSame(enforcer::OUTCOME_NOTHING, $outcome);
        $this->assertSame([], $events);
        $this->assertSame(enforcer::CLOSE_EXTERNAL, $this->states($instance)[0]->closereason);

        // Control: the same enforcer without the other writer reactivates and says so.
        $other = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->enforcer->check_user($other, $this->user->id);
        $this->set_allowed($other, [(int) $this->user->id]);
        $sink = $this->redirectEvents();
        $outcome = $this->racing_enforcer(null)->check_user($other, $this->user->id);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof event\user_enrolment_reactivated);
        $sink->close();
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $outcome);
        $this->assertCount(1, $events);
        $this->assertSame(enforcer::CLOSE_REACTIVATED, $this->states($other)[0]->closereason);
    }

    public function test_refused_lock_changes_nothing(): void {
        global $CFG;
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);

        $CFG->lock_factory = '\\local_enrolconditions\\refusing_lock_factory';
        $this->assertSame(enforcer::OUTCOME_SKIPPED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
        $this->assertSame([], $this->states($instance));

        // Control: with the site's lock factory the same check suspends.
        $CFG->lock_factory = 'auto';
        $enforcer = new enforcer(['manual' => $this->plugin]);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $enforcer->check_user($instance, $this->user->id));
        $this->assertCount(1, $this->states($instance));
    }

    public function test_failed_state_insert_leaves_the_enrolment_active(): void {
        // The rollback of the enforcer's own transaction is what is tested: no test transaction around it.
        $this->preventResetByRollback();
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $failing = new class (['manual' => $this->plugin]) extends enforcer {
            #[\Override]
            protected function insert_state(\stdClass $instance, \stdClass $ue, string $action, string $reason): int {
                throw new \moodle_exception('error');
            }
        };

        try {
            $failing->check_user($instance, $this->user->id);
            $this->fail('The insert should have thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error', $e->errorcode);
        }
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
        $this->assertSame([], $this->states($instance));

        // Control: the same check with a working insert suspends.
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance));
    }

    public function test_throwing_row_does_not_stop_the_periodic_check(): void {
        global $DB;
        // The rollback of the enforcer's own transaction is what is tested: no test transaction around it.
        $this->preventResetByRollback();
        set_config(enforcer::POSITION_SETTING, '', 'local_enrolconditions');
        $broken = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_PERIODIC);
        $DB->set_field('enrol', 'customchar1', 'throw', ['id' => $broken->id]);
        $healthy = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_PERIODIC);

        // One enrolment per run: the broken one is reported and the position goes past it.
        $outcomes = $this->enforcer->check_periodic(1, 0);
        $this->assertDebuggingCalled();
        $this->assertSame([enforcer::OUTCOME_ERROR], array_values($outcomes));
        $this->assertSame([(int) $broken->id, (int) $this->user->id], enforcer::get_position());

        $outcomes = $this->enforcer->check_periodic(1, 0);
        $this->assertSame([enforcer::OUTCOME_SUSPENDED], array_values($outcomes));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($healthy));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($broken));

        // In one run with time to spare, both are checked.
        set_config(enforcer::POSITION_SETTING, '', 'local_enrolconditions');
        $this->plugin->update_user_enrol($healthy, $this->user->id, ENROL_USER_ACTIVE);
        $outcomes = $this->enforcer->check_periodic(10, 60);
        $this->assertDebuggingCalled();
        $this->assertCount(2, $outcomes);
        $this->assertSame([0, 0], enforcer::get_position());
    }

    public function test_periodic_check_resumes_across_runs_and_instances(): void {
        set_config(enforcer::POSITION_SETTING, '', 'local_enrolconditions');
        $first = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_PERIODIC);
        $second = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_PERIODIC);
        $login = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_LOGIN);
        $other = $this->enrol_new_user($first);
        $this->plugin->enrol_user($second, $other);
        $this->plugin->enrol_user($login, $other);
        $users = [(int) $this->user->id, $other];
        sort($users);

        // Chunks of one and no time to spare: one enrolment per run, by instance id and then by user id.
        $checked = [];
        for ($run = 0; $run < 4; $run++) {
            $outcomes = $this->enforcer->check_periodic(1, 0);
            $this->assertCount(1, $outcomes);
            $checked[] = (int) array_key_first($outcomes);
        }
        global $DB;
        $expected = [];
        foreach ([$first, $second] as $instance) {
            foreach ($users as $userid) {
                $expected[] = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $userid]);
            }
        }
        $this->assertSame($expected, $checked);
        $this->assertSame([(int) $second->id, $users[1]], enforcer::get_position());

        // The next run finds nothing after the last enrolment and completes the cycle.
        $this->assertSame([], $this->enforcer->check_periodic(1, 0));
        $this->assertSame([0, 0], enforcer::get_position());
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($login));

        // A new cycle starts from the first instance again.
        $outcomes = $this->enforcer->check_periodic(1, 0);
        $this->assertSame([$expected[0]], array_keys($outcomes));

        // With time to spare one run completes the cycle.
        $this->assertCount(3, $this->enforcer->check_periodic(1, 60));
        $this->assertSame([0, 0], enforcer::get_position());
    }

    public function test_disabled_condition_type_is_unknown(): void {
        global $DB;
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $tree = json_encode(['op' => 'and', 'children' => [['type' => 'date', 'config' => ['to' => time() - HOURSECS]]]]);
        $DB->set_field('enrol', 'customtext1', $tree, ['id' => $instance->id]);
        $instance->customtext1 = $tree;

        set_config('disabled', 1, 'enrolcond_date');
        $this->assertSame(enforcer::OUTCOME_UNKNOWN, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
        $this->assertSame([], $this->states($instance));

        // Control: enabled, the same expired period suspends.
        unset_config('disabled', 'enrolcond_date');
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user($instance, $this->user->id));
    }

    public function test_duplicate_open_states_are_all_closed(): void {
        global $DB;
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $duplicate = $this->states($instance)[0];
        unset($duplicate->id);
        $DB->insert_record(enforcer::TABLE, $duplicate);
        $this->assertCount(2, $this->states($instance, true));

        $this->set_allowed($instance, [(int) $this->user->id]);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame([], $this->states($instance, true));
        $this->assertCount(2, $this->states($instance));
    }

    public function test_login_check_reports_a_broken_instance_and_goes_on(): void {
        global $DB;
        // The rollback of the enforcer's own transaction is what is tested: no test transaction around it.
        $this->preventResetByRollback();
        $broken = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_LOGIN);
        $DB->set_field('enrol', 'customchar1', 'throw', ['id' => $broken->id]);
        $healthy = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_LOGIN);

        $outcomes = $this->enforcer->check_user_in_mode($this->user->id, enforceable::CHECK_LOGIN, 'manual');
        $this->assertDebuggingCalled();
        $this->assertEqualsCanonicalizing([enforcer::OUTCOME_ERROR, enforcer::OUTCOME_SUSPENDED], array_values($outcomes));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($healthy));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($broken));
    }

    /**
     * An instance object loaded before a teacher changed its stored row, as the periodic check keeps it for a run.
     *
     * @param callable $change Receives the instance id and changes the stored row.
     * @return \stdClass The stale instance object, with the remove action and a tree the test user fails.
     */
    protected function stale_instance(callable $change): \stdClass {
        $instance = $this->create_instance(enforceable::ACTION_REMOVE, []);
        $change((int) $instance->id);
        return $instance;
    }

    public function test_a_stale_instance_is_read_again_under_the_lock(): void {
        global $DB;
        $cases = [
            'conditions removed' => fn(int $id) => $DB->set_field('enrol', 'customtext1', '', ['id' => $id]),
            'instance disabled' => fn(int $id) => $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $id]),
            'instance deleted' => fn(int $id) => $DB->delete_records('enrol', ['id' => $id]),
            'method no longer enforceable' => fn(int $id) => $DB->set_field('enrol', 'enrol', 'self', ['id' => $id]),
        ];
        foreach ($cases as $name => $change) {
            $instance = $this->stale_instance($change);
            $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id), $name);
            $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance), $name);
            $this->assertSame([], $this->states($instance), $name);
        }

        // The action became "none": the failure is only recorded, the enrolment stays.
        $instance = $this->stale_instance(
            fn(int $id) => $DB->set_field('enrol', 'customint8', enforceable::ACTION_NONE, ['id' => $id])
        );
        $this->assertSame(enforcer::OUTCOME_RECORDED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
        $this->assertSame(enforcer::STATE_NONE, $this->states($instance)[0]->action);

        // Control: the same stale object of an unchanged row removes the enrolment.
        $instance = $this->stale_instance(fn(int $id) => null);
        $this->assertSame(enforcer::OUTCOME_REMOVED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertNull($this->ue_status($instance));
    }

    public function test_periodic_run_decides_on_the_instance_as_stored_now(): void {
        global $DB;
        set_config(enforcer::POSITION_SETTING, '', 'local_enrolconditions');
        $instance = $this->create_instance(enforceable::ACTION_REMOVE, [], enforceable::CHECK_PERIODIC);
        $other = $this->enrol_new_user($instance);
        // The run loaded the instance before the teacher relaxed the tree; the first row it checks sees the change.
        $relaxed = $this->tree([(int) $this->user->id, $other]);
        $enforcer = new class (['manual' => $this->plugin], $relaxed) extends enforcer {
            /**
             * Constructor.
             *
             * @param array $plugins
             * @param string $relaxed Tree the teacher stores while the run goes on.
             */
            public function __construct(array $plugins, /** @var string */ protected string $relaxed) {
                parent::__construct($plugins);
            }

            #[\Override]
            protected function check_rows(array $ues, array $instances, ?\progress_trace $trace = null): array {
                global $DB;
                $DB->set_field('enrol', 'customtext1', $this->relaxed, ['id' => array_key_first($instances)]);
                return parent::check_rows($ues, $instances, $trace);
            }
        };
        $outcomes = $enforcer->check_periodic(10, 60);
        $this->assertSame([enforcer::OUTCOME_NOTHING, enforcer::OUTCOME_NOTHING], array_values($outcomes));
        $this->assertSame(2, $DB->count_records('user_enrolments', ['enrolid' => $instance->id]));
    }

    public function test_a_suspension_changed_behind_the_hooks_is_not_reactivated(): void {
        global $DB;
        // Still suspended, but changed since (another writer that fired no hook): not owned any more.
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $ue = $this->ue($instance);
        $DB->set_field('user_enrolments', 'timemodified', (int) $ue->timemodified + 100, ['id' => $ue->id]);
        $this->set_allowed($instance, [(int) $this->user->id]);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance));
        $this->assertSame(enforcer::CLOSE_EXTERNAL, $this->states($instance)[0]->closereason);

        // Control: unchanged, the same suspension is reactivated.
        $other = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->enforcer->check_user($other, $this->user->id);
        $this->set_allowed($other, [(int) $this->user->id]);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($other, $this->user->id));
    }

    public function test_a_suspension_lifted_behind_the_hooks_is_suspended_again(): void {
        global $DB;
        // Active again with the same timemodified (another writer in the same second): the open row is not owned,
        // so a failing check suspends again under a new row instead of keeping the old one.
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, ['id' => $this->ue($instance)->id]);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user($instance, $this->user->id));
        $states = $this->states($instance);
        $this->assertCount(2, $states);
        $this->assertSame(enforcer::CLOSE_EXTERNAL, $states[0]->closereason);
        $this->assertNull($states[1]->timeclosed);

        // Control: an owned suspension that still fails is kept as it is.
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertCount(2, $this->states($instance));
    }

    public function test_suspension_still_owned_is_the_one_predicate(): void {
        $state = (object) ['action' => enforcer::STATE_SUSPEND, 'uetimemodified' => 1000];
        $this->assertTrue(enforcer::suspension_still_owned($state, ENROL_USER_SUSPENDED, 1000));
        $this->assertFalse(enforcer::suspension_still_owned($state, ENROL_USER_ACTIVE, 1000));
        $this->assertFalse(enforcer::suspension_still_owned($state, ENROL_USER_SUSPENDED, 1001));
        $this->assertTrue(enforcer::owns($state, (object) ['status' => ENROL_USER_SUSPENDED, 'timemodified' => 1000]));
        $none = (object) ['action' => enforcer::STATE_NONE];
        $this->assertTrue(enforcer::owns($none, (object) ['status' => ENROL_USER_ACTIVE, 'timemodified' => 1]));
    }

    public function test_unknown_never_removes(): void {
        global $DB;
        $instance = $this->create_instance(enforceable::ACTION_REMOVE, []);
        $tree = json_encode(['op' => 'and', 'children' => [['type' => 'date', 'config' => ['to' => time() - HOURSECS]]]]);
        $DB->set_field('enrol', 'customtext1', $tree, ['id' => $instance->id]);
        $instance->customtext1 = $tree;

        set_config('disabled', 1, 'enrolcond_date');
        $this->assertSame(enforcer::OUTCOME_UNKNOWN, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));

        // Control: enabled, the same expired period removes the enrolment.
        unset_config('disabled', 'enrolcond_date');
        $this->assertSame(enforcer::OUTCOME_REMOVED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertNull($this->ue_status($instance));
    }

    public function test_unknown_explanation_under_the_lock_writes_nothing(): void {
        global $DB;
        // Not met for the check without the lock and the value read under it, unknown for the explanation after.
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $tree = json_encode(['op' => 'and', 'children' => [
            ['type' => 'scripted', 'config' => ['met' => false, 'throwafter' => 2]],
        ]]);
        $DB->set_field('enrol', 'customtext1', $tree, ['id' => $instance->id]);
        $instance->customtext1 = $tree;

        $this->assertSame(enforcer::OUTCOME_UNKNOWN, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertDebuggingCalled();
        $this->assertSame(3, fixtures\scripted_condition::$calls);
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
        $this->assertSame([], $this->states($instance));

        // Control: answering not met every time, the same check suspends.
        fixtures\scripted_condition::$calls = -10;
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user($instance, $this->user->id));
    }

    public function test_failing_row_inside_the_callers_transaction_is_rethrown(): void {
        global $DB;
        // A transaction of the test's own is what is tested: no test transaction around it.
        $this->preventResetByRollback();
        $broken = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_LOGIN);
        $DB->set_field('enrol', 'customchar1', 'throw', ['id' => $broken->id]);

        $transaction = $DB->start_delegated_transaction();
        try {
            $this->enforcer->check_user_in_mode($this->user->id, enforceable::CHECK_LOGIN, 'manual');
            $this->fail('The failure must reach the caller that opened the transaction.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error', $e->errorcode);
        }
        $transaction->allow_commit();

        // Control: outside a transaction the same row is reported and the check goes on.
        $outcomes = $this->enforcer->check_user_in_mode($this->user->id, enforceable::CHECK_LOGIN, 'manual');
        $this->assertDebuggingCalled();
        $this->assertSame([enforcer::OUTCOME_ERROR], array_values($outcomes));
    }

    public function test_position_is_saved_after_each_chunk_of_a_run_that_goes_on(): void {
        set_config(enforcer::POSITION_SETTING, '', 'local_enrolconditions');
        $instance = $this->create_instance(enforceable::ACTION_NONE, [], enforceable::CHECK_PERIODIC);
        $users = [(int) $this->user->id, $this->enrol_new_user($instance), $this->enrol_new_user($instance)];
        sort($users);
        $enforcer = new class (['manual' => $this->plugin]) extends enforcer {
            /** @var array[] Position saved when each chunk started. */
            public array $seen = [];

            #[\Override]
            protected function check_rows(array $ues, array $instances, ?\progress_trace $trace = null): array {
                $this->seen[] = self::get_position();
                return parent::check_rows($ues, $instances, $trace);
            }
        };

        // Chunks of one with time to spare: one run, three chunks, the position saved between them.
        $this->assertCount(3, $enforcer->check_periodic(1, 60));
        $id = (int) $instance->id;
        $this->assertSame([[0, 0], [$id, $users[0]], [$id, $users[1]]], $enforcer->seen);
        // Control: the cycle completes, so the position is reset at the end.
        $this->assertSame([0, 0], enforcer::get_position());
    }

    /**
     * Fake method whose update_user_enrol() runs a callback once, right after core wrote the row.
     *
     * Stands in for an observer of user_enrolment_updated in another plugin, which core runs inside the write.
     *
     * @return \local_enrolconditions_fake_enforceable
     */
    protected function plugin_with_inner_writer(): \local_enrolconditions_fake_enforceable {
        return new class extends \local_enrolconditions_fake_enforceable {
            /** @var callable|null Runs once after the next write. */
            public $after = null;

            #[\Override]
            public function update_user_enrol(\stdClass $instance, $userid, $status = null, $timestart = null, $timeend = null) {
                parent::update_user_enrol($instance, $userid, $status, $timestart, $timeend);
                if ($this->after) {
                    $after = $this->after;
                    $this->after = null;
                    $after();
                }
            }
        };
    }

    public function test_a_write_to_another_row_during_the_enforcers_write_keeps_the_suspension_owned(): void {
        global $DB;
        $plugin = $this->plugin_with_inner_writer();
        enforcer::register_plugins_for_testing(['manual' => $plugin]);
        $enforcer = new enforcer(['manual' => $plugin]);
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        // Another enrolment of the user, suspended by the enforcer some time ago.
        $other = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $enforcer->check_user($other, $this->user->id));
        $otherue = $this->ue($other);
        $DB->set_field('user_enrolments', 'timemodified', time() - 100, ['id' => $otherue->id]);
        $DB->set_field(enforcer::TABLE, 'uetimemodified', time() - 100, ['userenrolmentid' => $otherue->id]);
        // While the enforcer writes the first one, an observer of another plugin changes the dates of the other.
        $plugin->after = fn() => $plugin->update_user_enrol($other, $this->user->id, null, time() - 50);

        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $enforcer->check_user($instance, $this->user->id));
        $states = $this->states($instance, true);
        $this->assertCount(1, $states);
        $this->assertTrue(enforcer::owns($states[0], $this->ue($instance)));
        // The other row was changed by someone else, as the hook sees any such change: its dates only, so kept.
        $otherstates = $this->states($other, true);
        $this->assertCount(1, $otherstates);
        $this->assertTrue(enforcer::owns($otherstates[0], $this->ue($other)));

        // Control: the reactivation of the owned suspension still works.
        $this->set_allowed($instance, [(int) $this->user->id]);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $enforcer->check_user($instance, $this->user->id));
    }

    public function test_only_the_first_write_of_the_acting_row_is_the_enforcers(): void {
        $plugin = $this->plugin_with_inner_writer();
        enforcer::register_plugins_for_testing(['manual' => $plugin]);
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        // An observer of another plugin lifts the suspension inside the enforcer's write.
        $plugin->after = fn() => $plugin->update_user_enrol($instance, $this->user->id, ENROL_USER_ACTIVE);

        $enforcer = new enforcer(['manual' => $plugin]);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $enforcer->check_user($instance, $this->user->id));
        $states = $this->states($instance, true);
        $this->assertCount(1, $states);
        // The enforcer's suspension is recorded; the later change is someone else's, so it no longer owns it.
        $this->assertFalse(enforcer::owns($states[0], $this->ue($instance)));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
    }

    public function test_a_nested_enforcer_call_does_not_clear_the_outer_record(): void {
        global $DB;
        $plugin = $this->plugin_with_inner_writer();
        enforcer::register_plugins_for_testing(['manual' => $plugin]);
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $other = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $plugin->after = fn() => (new enforcer(['manual' => $plugin]))->check_user($other, $this->user->id);

        $enforcer = new enforcer(['manual' => $plugin]);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $enforcer->check_user($instance, $this->user->id));
        $this->assertCount(1, $this->states($instance, true));
        $this->assertCount(1, $this->states($other, true));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($other));
        // Nothing is marked as the enforcer's own once it is done, whichever row is asked.
        foreach ([$instance, $other] as $each) {
            $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $each->id, 'userid' => $this->user->id]);
            $this->assertFalse(enforcer::is_acting_on($ueid));
        }
    }
}
