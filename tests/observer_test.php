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
use local_enrolconditions\task\recheck_user;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_condition.php');
require_once(__DIR__ . '/fixtures/fake_enforceable.php');
require_once(__DIR__ . '/fixtures/active_enrolment_condition.php');

/**
 * Tests of the observers: re-checks queued on events, the depth limit and the clean-up observers.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(recheck_user::class)]
final class observer_test extends \advanced_testcase {
    /** @var \local_enrolconditions_fake_enforceable */
    protected \local_enrolconditions_fake_enforceable $plugin;

    /** @var \stdClass Enrolled user. */
    protected \stdClass $user;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        manager::register_for_testing('activeenrolment', new fixtures\active_enrolment_condition());
        $this->plugin = new \local_enrolconditions_fake_enforceable();
        enforcer::register_plugins_for_testing(['manual' => $this->plugin]);
        $this->user = $this->getDataGenerator()->create_user();
    }

    #[\Override]
    protected function tearDown(): void {
        enforcer::register_plugins_for_testing(null);
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Creates an instance of the fake method in a new course and enrols the test user.
     *
     * @param array $root Condition tree.
     * @param int $modes Check modes.
     * @param \stdClass|null $course Course; a new one when null.
     * @return \stdClass Instance.
     */
    protected function create_instance(array $root, int $modes, ?\stdClass $course = null): \stdClass {
        global $DB;
        $course = $course ?? $this->getDataGenerator()->create_course();
        $DB->delete_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $id = $this->plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customtext1' => json_encode($root),
            'customint7' => $modes,
            'customint8' => enforceable::ACTION_SUSPEND | 16,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->plugin->enrol_user($instance, $this->user->id);
        return $instance;
    }

    /**
     * Pending re-check tasks.
     *
     * @return \stdClass[] Their custom data.
     */
    protected function queued(): array {
        global $DB;
        $records = $DB->get_records('task_adhoc', ['classname' => '\\' . recheck_user::class], 'id ASC');
        return array_values(array_map(fn($r) => json_decode($r->customdata), $records));
    }

    /**
     * Status of the test user's enrolment.
     *
     * @param \stdClass $instance
     * @return int
     */
    protected function ue_status(\stdClass $instance): int {
        global $DB;
        return (int) $DB->get_field('user_enrolments', 'status', ['enrolid' => $instance->id, 'userid' => $this->user->id]);
    }

    public function test_observed_event_queues_one_task_and_evaluates_nothing(): void {
        $instance = $this->create_instance(
            ['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => [-1]]]]],
            enforceable::CHECK_EVENTS
        );
        $this->assertSame([], $this->queued());

        // An event no condition observes queues nothing.
        \core\event\user_password_updated::create_from_user($this->user)->trigger();
        $this->assertSame([], $this->queued());

        // Three identical observed events queue one task, and the enrolment is not touched in this request.
        for ($i = 0; $i < 3; $i++) {
            \core\event\user_updated::create_from_userid($this->user->id)->trigger();
        }
        $queued = $this->queued();
        $this->assertCount(1, $queued);
        $this->assertSame((int) $this->user->id, (int) $queued[0]->userid);
        $this->assertSame(0, (int) $queued[0]->depth);
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));

        $this->expectOutputRegex('/suspended/');
        $this->runAdhocTasks(recheck_user::class);
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance));
    }

    public function test_instances_that_change_each_other_stop_at_the_depth_limit(): void {
        global $DB;
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        // A requires NOT an active enrolment in B; B requires an active enrolment in A: each change flips the other.
        $instancea = $this->create_instance(['op' => 'and', 'children' => [
            ['type' => 'activeenrolment', 'negate' => true, 'config' => ['courseid' => (int) $courseb->id]],
        ]], enforceable::CHECK_EVENTS, $coursea);
        $instanceb = $this->create_instance(['op' => 'and', 'children' => [
            ['type' => 'activeenrolment', 'config' => ['courseid' => (int) $coursea->id]],
        ]], enforceable::CHECK_EVENTS, $courseb);
        $this->assertCount(1, $this->queued());

        $this->expectOutputRegex('/depth limit \(' . recheck_user::MAX_DEPTH . '\) reached/');
        $depths = [];
        for ($guard = 0; $guard < 20 && ($queued = $this->queued()); $guard++) {
            $this->assertCount(1, $queued);
            $depths[] = (int) $queued[0]->depth;
            $this->runAdhocTasks(recheck_user::class);
        }
        $this->assertSame(range(0, recheck_user::MAX_DEPTH), $depths);
        $this->assertSame([], $this->queued());
        $states = $DB->count_records_select(enforcer::TABLE, 'instanceid IN (?, ?)', [$instancea->id, $instanceb->id]);
        $this->assertGreaterThan(0, $states);
        $this->assertLessThanOrEqual(2 * (recheck_user::MAX_DEPTH + 1), $states);
    }

    public function test_queue_refuses_beyond_the_depth_limit(): void {
        $this->assertTrue(recheck_user::queue((int) $this->user->id, recheck_user::MAX_DEPTH));
        $this->assertFalse(recheck_user::queue((int) $this->user->id, recheck_user::MAX_DEPTH + 1));
        $this->assertCount(1, $this->queued());
    }

    public function test_observer_fault_does_not_escape(): void {
        global $DB;
        $instance = $this->create_instance(
            ['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => [-1]]]]],
            enforceable::CHECK_EVENTS
        );
        $DB->set_field('enrol', 'customchar1', 'throwtree', ['id' => $instance->id]);
        enforcer::purge_event_map();
        $DB->delete_records('task_adhoc');

        \core\event\user_updated::create_from_userid($this->user->id)->trigger();
        $this->assertDebuggingCalled();
        $this->assertSame([], $this->queued());

        // Control: the same event queues the re-check once the method works.
        $DB->set_field('enrol', 'customchar1', '', ['id' => $instance->id]);
        enforcer::purge_event_map();
        \core\event\user_updated::create_from_userid($this->user->id)->trigger();
        $this->assertCount(1, $this->queued());
    }

    public function test_instance_observers_ignore_other_methods(): void {
        $cache = \cache::make('local_enrolconditions', 'observedevents');
        $course = $this->getDataGenerator()->create_course();

        $cache->set('sentinel', 1);
        $selfid = enrol_get_plugin('self')->add_instance($course);
        $this->assertSame(1, $cache->get('sentinel'));
        enrol_get_plugin('self')->delete_instance($this->get_instance($selfid));
        $this->assertSame(1, $cache->get('sentinel'));

        // Control: an instance of an enforceable method purges the map.
        $this->create_instance(['op' => 'and', 'children' => []], 0);
        $this->assertFalse($cache->get('sentinel'));
    }

    public function test_unenrolment_cleans_only_enforceable_methods(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $self = enrol_get_plugin('self');
        $selfinstance = $this->get_instance($self->add_instance($course));
        $self->enrol_user($selfinstance, $this->user->id);
        $selfue = $DB->get_record('user_enrolments', ['enrolid' => $selfinstance->id, 'userid' => $this->user->id]);
        $this->insert_state($selfinstance, $selfue);

        $own = $this->create_instance(['op' => 'and', 'children' => []], 0);
        $ownue = $DB->get_record('user_enrolments', ['enrolid' => $own->id, 'userid' => $this->user->id]);
        $this->insert_state($own, $ownue);

        $self->unenrol_user($selfinstance, $this->user->id);
        $this->assertTrue($DB->record_exists(enforcer::TABLE, ['userenrolmentid' => $selfue->id]));

        // Control: the enforceable method's unenrolment deletes its records.
        $this->plugin->unenrol_user($own, $this->user->id);
        $this->assertFalse($DB->record_exists(enforcer::TABLE, ['userenrolmentid' => $ownue->id]));
    }

    /**
     * Enrolment instance record.
     *
     * @param int $id
     * @return \stdClass
     */
    protected function get_instance(int $id): \stdClass {
        global $DB;
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Inserts an open suspension record.
     *
     * @param \stdClass $instance
     * @param \stdClass $ue
     */
    protected function insert_state(\stdClass $instance, \stdClass $ue): void {
        global $DB;
        $DB->insert_record(enforcer::TABLE, (object) [
            'enrol' => $instance->enrol,
            'userenrolmentid' => $ue->id,
            'instanceid' => $instance->id,
            'action' => enforcer::STATE_SUSPEND,
            'reason' => '',
            'timesuspended' => time(),
            'uetimemodified' => (int) $ue->timemodified,
        ]);
    }

    /**
     * Re-check tasks that are not running yet.
     *
     * @return \stdClass[] Their custom data.
     */
    protected function pending(): array {
        global $DB;
        $records = $DB->get_records_select(
            'task_adhoc',
            'classname = :classname AND timestarted IS NULL',
            ['classname' => '\\' . recheck_user::class],
            'id ASC'
        );
        return array_values(array_map(fn($r) => json_decode($r->customdata), $records));
    }

    public function test_an_event_during_the_users_running_task_is_queued_again(): void {
        global $DB;
        $this->create_instance(
            ['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => [-1]]]]],
            enforceable::CHECK_EVENTS
        );
        $DB->delete_records('task_adhoc');
        \core\event\user_updated::create_from_userid($this->user->id)->trigger();
        $this->assertCount(1, $this->pending());

        // Control: while that task is still pending, more events collapse into it.
        \core\event\user_updated::create_from_userid($this->user->id)->trigger();
        $this->assertCount(1, $this->queued());

        // The task starts: an event now must not be swallowed by the running task.
        $DB->set_field('task_adhoc', 'timestarted', time(), ['classname' => '\\' . recheck_user::class]);
        $this->assertSame([], $this->pending());
        for ($i = 0; $i < 3; $i++) {
            \core\event\user_updated::create_from_userid($this->user->id)->trigger();
        }
        $pending = $this->pending();
        $this->assertCount(1, $pending);
        $this->assertSame((int) $this->user->id, (int) $pending[0]->userid);
        $this->assertSame(0, (int) $pending[0]->depth);
        $this->assertCount(2, $this->queued());
    }

    public function test_depth_limit_holds_in_every_slot(): void {
        global $DB;
        $this->assertTrue(recheck_user::queue((int) $this->user->id, recheck_user::MAX_DEPTH));
        $DB->set_field('task_adhoc', 'timestarted', time(), ['classname' => '\\' . recheck_user::class]);
        $this->assertTrue(recheck_user::queue((int) $this->user->id, recheck_user::MAX_DEPTH));
        $this->assertFalse(recheck_user::queue((int) $this->user->id, recheck_user::MAX_DEPTH + 1));
        $this->assertCount(2, $this->queued());
        $this->assertCount(1, $this->pending());
    }

    public function test_events_during_an_upgrade_queue_nothing(): void {
        global $CFG, $DB;
        $this->create_instance(
            ['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => [-1]]]]],
            enforceable::CHECK_EVENTS
        );
        $DB->delete_records('task_adhoc');
        $CFG->upgraderunning = time();
        try {
            \core\event\user_updated::create_from_userid($this->user->id)->trigger();
        } finally {
            unset($CFG->upgraderunning);
        }
        $this->assertSame([], $this->queued());

        // Control: the same event outside the upgrade queues the re-check.
        \core\event\user_updated::create_from_userid($this->user->id)->trigger();
        $this->assertCount(1, $this->queued());
    }

    /**
     * Suspends the test user's enrolment through the enforcer and backdates it, as an old suspension.
     *
     * @param \stdClass $instance Instance whose tree the user fails.
     * @return \stdClass The open state row.
     */
    protected function owned_suspension(\stdClass $instance): \stdClass {
        global $DB;
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, (new enforcer())->check_user($instance, $this->user->id));
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $this->user->id]);
        $DB->set_field('user_enrolments', 'timemodified', time() - 100, ['id' => $ueid]);
        $DB->set_field(enforcer::TABLE, 'uetimemodified', time() - 100, ['userenrolmentid' => $ueid]);
        return $DB->get_record(enforcer::TABLE, ['userenrolmentid' => $ueid], '*', MUST_EXIST);
    }

    public function test_hook_decides_ownership_on_the_recorded_facts(): void {
        global $DB;
        $failing = ['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => [-1]]]]];

        // A teacher reactivates the suspension: no longer the enforcer's.
        $instance = $this->create_instance($failing, 0);
        $state = $this->owned_suspension($instance);
        $this->plugin->update_user_enrol($instance, $this->user->id, ENROL_USER_ACTIVE);
        $this->assertSame(enforcer::CLOSE_EXTERNAL, $DB->get_field(enforcer::TABLE, 'closereason', ['id' => $state->id]));

        // A teacher edits only the dates of an owned suspension: kept, stamped with the new timemodified.
        $instance = $this->create_instance($failing, 0);
        $state = $this->owned_suspension($instance);
        $this->plugin->update_user_enrol($instance, $this->user->id, null, time() - 50);
        $state = $DB->get_record(enforcer::TABLE, ['id' => $state->id], '*', MUST_EXIST);
        $ue = $DB->get_record('user_enrolments', ['id' => $state->userenrolmentid], '*', MUST_EXIST);
        $this->assertNull($state->timeclosed);
        $this->assertSame((int) $ue->timemodified, (int) $state->uetimemodified);
        $this->assertGreaterThan(time() - 100, (int) $state->uetimemodified);

        // A teacher edits the dates of a suspension changed behind the hooks: not reclaimed, closed.
        $instance = $this->create_instance($failing, 0);
        $state = $this->owned_suspension($instance);
        $DB->set_field('user_enrolments', 'timemodified', time() - 90, ['id' => $state->userenrolmentid]);
        $this->plugin->update_user_enrol($instance, $this->user->id, null, time() - 50);
        $this->assertSame(enforcer::CLOSE_EXTERNAL, $DB->get_field(enforcer::TABLE, 'closereason', ['id' => $state->id]));

        // The enforcer's own write: the hook leaves its row to the enforcer, which closes it as reactivated.
        $instance = $this->create_instance($failing, 0);
        $state = $this->owned_suspension($instance);
        $DB->set_field('enrol', 'customtext1', json_encode(['op' => 'and', 'children' => []]), ['id' => $instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, (new enforcer())->check_user($instance, $this->user->id));
        $this->assertSame(enforcer::CLOSE_REACTIVATED, $DB->get_field(enforcer::TABLE, 'closereason', ['id' => $state->id]));
    }
}
