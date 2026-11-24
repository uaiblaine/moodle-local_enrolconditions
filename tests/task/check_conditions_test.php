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

namespace local_enrolconditions\task;

use local_enrolconditions\condition\manager;
use local_enrolconditions\enforceable;
use local_enrolconditions\local\enforcer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/fake_condition.php');
require_once(__DIR__ . '/../fixtures/fake_enforceable.php');

/**
 * Tests of the periodic re-check task: what a run tells the administrator.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(check_conditions::class)]
final class check_conditions_test extends \advanced_testcase {
    /** @var \local_enrolconditions_fake_enforceable */
    protected \local_enrolconditions_fake_enforceable $plugin;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        $this->plugin = new \local_enrolconditions_fake_enforceable();
        enforcer::register_plugins_for_testing(['manual' => $this->plugin]);
        set_config(enforcer::POSITION_SETTING, '', 'local_enrolconditions');
    }

    #[\Override]
    protected function tearDown(): void {
        enforcer::register_plugins_for_testing(null);
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Creates a periodic instance whose tree nobody meets, in a new course, with one enrolled user.
     *
     * @param bool $broken Whether reading its action throws.
     * @return \stdClass
     */
    protected function create_instance(bool $broken): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->delete_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $id = $this->plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customtext1' => json_encode(['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => [-1]]]]]),
            'customchar1' => $broken ? 'throw' : '',
            'customint7' => enforceable::CHECK_PERIODIC,
            'customint8' => enforceable::ACTION_SUSPEND,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->plugin->enrol_user($instance, $this->getDataGenerator()->create_user()->id);
        return $instance;
    }

    public function test_run_reports_its_outcomes_and_its_errors(): void {
        // The rollback of the enforcer's own transaction is what a broken row exercises: no test transaction.
        $this->preventResetByRollback();
        $this->create_instance(true);
        $this->create_instance(false);

        ob_start();
        (new check_conditions())->execute();
        $output = ob_get_clean();
        $this->assertDebuggingCalled();
        $this->assertStringContainsString('2 enrolments checked (error: 1, suspended: 1); the cycle is complete.', $output);
        $this->assertStringContainsString('1 enrolments could not be checked', $output);
    }

    public function test_run_without_errors_says_where_the_next_one_continues(): void {
        $this->create_instance(false);
        $this->create_instance(false);
        set_config('checkbatchsize', 1, 'local_enrolconditions');
        set_config('checktimelimit', 0, 'local_enrolconditions');

        ob_start();
        (new check_conditions())->execute();
        $output = ob_get_clean();
        [$instanceid, $userid] = enforcer::get_position();
        $this->assertStringContainsString(
            "1 enrolments checked (suspended: 1); the next run continues at instance {$instanceid}, after user {$userid}.",
            $output
        );
        // Control: no error, no error line.
        $this->assertStringNotContainsString('could not be checked', $output);
    }
}
