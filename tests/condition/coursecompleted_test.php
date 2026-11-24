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

namespace local_enrolconditions\condition;

use enrolcond_coursecompleted\condition;
use local_enrolconditions\tree;
/**
 * Tests of the course completed condition.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(condition::class)]
final class coursecompleted_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        parent::setUpBeforeClass();
    }

    public function test_is_met(): void {
        global $CFG;
        require_once($CFG->dirroot . '/completion/completion_completion.php');
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $completed = $this->getDataGenerator()->create_user();
        $started = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($completed->id, $course->id);
        $this->getDataGenerator()->enrol_user($started->id, $course->id);

        $sink = $this->redirectEvents();
        (new \completion_completion(['userid' => $completed->id, 'course' => $course->id]))->mark_complete();
        (new \completion_completion(['userid' => $started->id, 'course' => $course->id]))->mark_inprogress();
        $events = $sink->get_events();
        $sink->close();

        $condition = new condition();
        $config = ['courseid' => (int) $course->id];
        $this->assertTrue($condition->is_met((int) $completed->id, new \stdClass(), $config));
        $this->assertFalse($condition->is_met((int) $started->id, new \stdClass(), $config));

        // The event observed by the condition concerns the user who completed.
        $observed = array_filter($events, fn($e) => in_array(get_class($e), $condition->get_observed_events(), true));
        $this->assertCount(1, $observed);
        $this->assertEquals($completed->id, reset($observed)->relateduserid);
    }

    /**
     * Value of a tree with one course completed condition for a user, through the tree.
     *
     * @param array $config
     * @param int $userid
     * @param bool $negate
     * @return bool|null Null when unknown; the debugging message of the failed condition is consumed.
     */
    protected function tree_value(array $config, int $userid, bool $negate = false): ?bool {
        $root = ['op' => 'and', 'children' => [['type' => 'coursecompleted', 'negate' => $negate, 'config' => $config]]];
        $value = tree::from_array($root)->evaluate($userid, (object) ['id' => 0, 'courseid' => SITEID])->get_value();
        if ($value === null) {
            $this->assertDebuggingCalled();
        }
        return $value;
    }

    public function test_deleted_course_is_unknown_not_unmet(): void {
        global $CFG;
        require_once($CFG->dirroot . '/completion/completion_completion.php');
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        (new \completion_completion(['userid' => $user->id, 'course' => $course->id]))->mark_complete();
        $config = ['courseid' => (int) $course->id];

        // Control: while the course exists, completed is met and not completed is not met, negated or not.
        $this->assertTrue($this->tree_value($config, (int) $user->id));
        $this->assertFalse($this->tree_value($config, (int) $other->id));
        $this->assertFalse($this->tree_value($config, (int) $user->id, true));
        $this->assertTrue($this->tree_value($config, (int) $other->id, true));

        delete_course($course, false);
        condition::reset_existence_cache();
        $this->assertNull($this->tree_value($config, (int) $user->id));
        $this->assertNull($this->tree_value($config, (int) $other->id));
        $this->assertNull($this->tree_value($config, (int) $other->id, true));
        try {
            (new condition())->is_met((int) $user->id, new \stdClass(), $config);
            $this->fail('A deleted course cannot be evaluated');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingcourse', $e->errorcode);
        }
    }

    public function test_course_id_zero_from_another_site_is_unknown(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $restored = (new condition())->restore_config(['courseid' => 5], false);
        $this->assertSame(['courseid' => 0], $restored);

        $this->assertNull($this->tree_value($restored, (int) $user->id));
        $this->assertNull($this->tree_value($restored, (int) $user->id, true));
        $this->assertNull($this->tree_value([], (int) $user->id));
        // Control: the same site keeps the course, which evaluates.
        $course = $this->getDataGenerator()->create_course();
        $kept = (new condition())->restore_config(['courseid' => (int) $course->id], true);
        $this->assertFalse($this->tree_value($kept, (int) $user->id));
    }

    public function test_description(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Python <i>101</i>']);
        $condition = new condition();
        $this->assertSame('You have completed the course Python 101', $condition->get_summary(['courseid' => $course->id]));
        $this->assertSame(
            'You have completed a course that no longer exists (id 999999)',
            $condition->get_description(['courseid' => 999999], false)
        );
    }

    public function test_validation_and_form(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $condition = new condition();
        $this->assertSame([], $condition->validate_config(['courseid' => $course->id]));
        $this->assertArrayHasKey('courseid', $condition->validate_config(['courseid' => SITEID]));
        $this->assertArrayHasKey('courseid', $condition->validate_config(['courseid' => 999999]));
        $this->assertArrayHasKey('courseid', $condition->validate_config([]));

        $this->assertSame(['courseid' => 7], $condition->config_from_form_data((object) ['courseid' => '7']));
        $this->assertSame(['courseid' => 8], $condition->config_from_form_data((object) ['courseid' => ['8']]));
        $form = new \MoodleQuickForm('test', 'post', '');
        $condition->config_form_definition($form);
        $this->assertTrue($form->elementExists('courseid'));
        $this->assertSame('coursecompleted', $condition->get_type());
    }

    public function test_check_access_follows_course_visibility(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $hidden = $generator->create_course(['visible' => 0]);
        $visible = $generator->create_course();
        // Enrolled as a student in a hidden course: enrolled, but core's course selector does not list it.
        $studenthidden = $generator->create_course(['visible' => 0]);
        $generator->enrol_user($teacher->id, $studenthidden->id, 'student');
        // Enrolled as a teacher in a hidden course: moodle/course:viewhiddencourses there, so core lists it.
        $teacherhidden = $generator->create_course(['visible' => 0]);
        $generator->enrol_user($teacher->id, $teacherhidden->id, 'editingteacher');
        // A visible course in a category whose course list the user may not see: only the enrolment shows it.
        $category = $generator->create_category();
        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user']);
        $catcontext = \context_coursecat::instance($category->id);
        assign_capability('moodle/category:viewcourselist', CAP_PROHIBIT, $userrole, $catcontext->id, true);
        $unlisted = $generator->create_course(['category' => $category->id]);
        $enrolledunlisted = $generator->create_course(['category' => $category->id]);
        $generator->enrol_user($teacher->id, $enrolledunlisted->id, 'student');
        $condition = new condition();
        $noaccess = get_string('noaccess', 'enrolcond_coursecompleted');

        $this->setUser($teacher);
        $this->assertSame($noaccess, $condition->check_access(['courseid' => $hidden->id], $context));
        $this->assertSame($noaccess, $condition->check_access(['courseid' => 999999], $context));
        $this->assertSame($noaccess, $condition->check_access(['courseid' => $studenthidden->id], $context));
        $this->assertSame($noaccess, $condition->check_access(['courseid' => $unlisted->id], $context));
        // Controls: a visible course, a hidden course the teacher may see, a listed-by-enrolment course.
        $this->assertNull($condition->check_access(['courseid' => $visible->id], $context));
        $this->assertNull($condition->check_access(['courseid' => $teacherhidden->id], $context));
        $this->assertNull($condition->check_access(['courseid' => $enrolledunlisted->id], $context));
        $this->setAdminUser();
        $this->assertNull($condition->check_access(['courseid' => $hidden->id], $context));
    }

    public function test_privacy_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('enrolcond_coursecompleted'));
    }
}
