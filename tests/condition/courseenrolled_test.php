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

namespace enrolcond_courseenrolled;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Tests of the enrolled in a course condition.
 *
 * @package    enrolcond_courseenrolled
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(condition::class)]
final class condition_test extends \advanced_testcase {
    public function test_any_and_all(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $c1 = $gen->create_course();
        $c2 = $gen->create_course();
        $both = $gen->create_user();
        $one = $gen->create_user();
        $none = $gen->create_user();
        $gen->enrol_user($both->id, $c1->id);
        $gen->enrol_user($both->id, $c2->id);
        $gen->enrol_user($one->id, $c1->id);

        $condition = new condition();
        $instance = new \stdClass();
        $any = ['courseids' => [$c1->id, $c2->id], 'mode' => 'any', 'active' => 1];
        $all = ['mode' => 'all'] + $any;
        $this->assertTrue($condition->is_met((int) $both->id, $instance, $any));
        $this->assertTrue($condition->is_met((int) $one->id, $instance, $any));
        $this->assertFalse($condition->is_met((int) $none->id, $instance, $any));
        $this->assertTrue($condition->is_met((int) $both->id, $instance, $all));
        $this->assertFalse($condition->is_met((int) $one->id, $instance, $all));
        $this->assertFalse($condition->is_met((int) $both->id, $instance, []));
    }

    public function test_active_enrolments(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $suspended = $gen->create_user();
        $expired = $gen->create_user();
        $future = $gen->create_user();
        $disabled = $gen->create_user();
        $gen->enrol_user($suspended->id, $course->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $gen->enrol_user($expired->id, $course->id, null, 'manual', time() - 7200, time() - 3600);
        $gen->enrol_user($future->id, $course->id, null, 'manual', time() + 3600);
        $gen->enrol_user($disabled->id, $course->id, null, 'self');
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $self->id]);

        $condition = new condition();
        $active = ['courseids' => [$course->id], 'active' => 1];
        $anyenrolment = ['courseids' => [$course->id], 'active' => 0];
        foreach ([$suspended, $expired, $future, $disabled] as $user) {
            $this->assertFalse($condition->is_met((int) $user->id, new \stdClass(), $active));
            $this->assertTrue($condition->is_met((int) $user->id, new \stdClass(), $anyenrolment));
        }
        // Without the option (configuration saved by hand), only active enrolments count.
        $this->assertFalse($condition->is_met((int) $suspended->id, new \stdClass(), ['courseids' => [$course->id]]));
    }

    public function test_own_instance_and_deleted_course(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, null, 'manual');
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);

        $condition = new condition();
        $config = ['courseids' => [$course->id]];
        $this->assertTrue($condition->is_met((int) $user->id, new \stdClass(), $config));
        // The enrolment being checked does not satisfy its own condition.
        $this->assertFalse($condition->is_met((int) $user->id, $manual, $config));

        $gen->enrol_user($user->id, $other->id);
        $config = ['courseids' => [$course->id, $other->id], 'mode' => 'all'];
        $this->assertTrue($condition->is_met((int) $user->id, new \stdClass(), $config));
        delete_course($other, false);
        $this->assertFalse($condition->is_met((int) $user->id, new \stdClass(), $config));
        $this->assertTrue($condition->is_met((int) $user->id, new \stdClass(), ['mode' => 'any'] + $config));
    }

    public function test_description(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $c1 = $gen->create_course(['fullname' => 'Python <i>101</i>']);
        $c2 = $gen->create_course(['fullname' => 'Java']);
        $condition = new condition();
        $this->assertSame('You are enrolled in the course Python 101', $condition->get_summary(['courseids' => [$c1->id]]));
        $this->assertSame(
            'You are enrolled in all of these courses: Python 101; Java',
            $condition->get_summary(['courseids' => [$c1->id, $c2->id], 'mode' => 'all'])
        );
        $this->assertSame(
            'You are enrolled in at least one of these courses: Python 101; course that no longer exists (id 999999)',
            $condition->get_description(['courseids' => [$c1->id, 999999]], false)
        );
    }

    public function test_validation_form_and_restore(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $condition = new condition();
        $this->assertSame([], $condition->validate_config(['courseids' => [$course->id]]));
        $this->assertArrayHasKey('courseids', $condition->validate_config(['courseids' => [SITEID]]));
        $this->assertArrayHasKey('courseids', $condition->validate_config(['courseids' => [$course->id, 999999]]));
        $this->assertArrayHasKey('courseids', $condition->validate_config([]));
        $toomany = range(2, 2 + condition::MAX_COURSES);
        $this->assertArrayHasKey('courseids', $condition->validate_config(['courseids' => $toomany]));

        $this->assertSame(
            ['courseids' => [7, 8], 'mode' => 'all', 'active' => 0],
            $condition->config_from_form_data((object) ['courseids' => ['7', '8', '7', '0'], 'mode' => 'all', 'active' => '0'])
        );
        $this->assertSame(
            ['courseids' => [], 'mode' => 'any', 'active' => 1],
            $condition->config_from_form_data((object) ['mode' => 'x', 'active' => '1'])
        );
        $form = new \MoodleQuickForm('test', 'post', '');
        $condition->config_form_definition($form);
        $this->assertTrue($form->elementExists('courseids'));
        $this->assertTrue($form->elementExists('mode'));
        $this->assertTrue($form->elementExists('active'));
        $this->assertSame('courseenrolled', $condition->get_type());

        $config = ['courseids' => [$course->id], 'mode' => 'all', 'active' => 1];
        $this->assertSame($config, $condition->restore_config($config, true));
        $this->assertSame(['courseids' => [], 'mode' => 'all', 'active' => 1], $condition->restore_config($config, false));
    }

    public function test_observed_events(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $sink = $this->redirectEvents();
        $gen->enrol_user($user->id, $course->id);
        $events = $sink->get_events();
        $sink->close();

        $condition = new condition();
        $observed = array_filter($events, fn($e) => in_array(get_class($e), $condition->get_observed_events(), true));
        $this->assertCount(1, $observed);
        $this->assertEquals($user->id, reset($observed)->relateduserid);
    }
}
