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

use enrolcond_cohort\condition;
use local_enrolconditions\enforceable;
use local_enrolconditions\local\enforcer;
use local_enrolconditions\tree;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/fake_enforceable.php');

/**
 * Tests of the cohort condition.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(condition::class)]
final class cohort_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        parent::setUpBeforeClass();
    }

    public function test_is_met(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);
        $condition = new condition();
        $config = ['cohortid' => (int) $cohort->id];

        $this->assertTrue($condition->is_met((int) $member->id, new \stdClass(), $config));
        $this->assertFalse($condition->is_met((int) $other->id, new \stdClass(), $config));
    }

    /**
     * Value of a tree with one cohort condition for a user, through the tree (the way callers see it).
     *
     * @param array $config
     * @param int $userid
     * @param bool $negate
     * @return bool|null Null when unknown; the debugging message of the failed condition is consumed.
     */
    protected function tree_value(array $config, int $userid, bool $negate = false): ?bool {
        $tree = tree::from_array(['op' => 'and', 'children' => [['type' => 'cohort', 'negate' => $negate, 'config' => $config]]]);
        $value = $tree->evaluate($userid, (object) ['id' => 0, 'courseid' => SITEID])->get_value();
        if ($value === null) {
            $this->assertDebuggingCalled();
        }
        return $value;
    }

    public function test_deleted_cohort_is_unknown_not_unmet(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);
        $config = ['cohortid' => (int) $cohort->id];

        // Control: while the cohort exists, the answer is met or not met, negated or not.
        $this->assertTrue($this->tree_value($config, (int) $member->id));
        $this->assertFalse($this->tree_value($config, (int) $member->id, true));

        cohort_delete_cohort($cohort);
        condition::reset_existence_cache();
        $this->assertNull($this->tree_value($config, (int) $member->id));
        // Marked "Must not be met" it must not let everybody in either.
        $this->assertNull($this->tree_value($config, (int) $member->id, true));
        try {
            (new condition())->is_met((int) $member->id, new \stdClass(), $config);
            $this->fail('A deleted cohort cannot be evaluated');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingcohort', $e->errorcode);
        }
    }

    public function test_cohort_id_zero_from_another_site_is_unknown(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $restored = (new condition())->restore_config(['cohortid' => 5], false);
        $this->assertSame(['cohortid' => 0], $restored);

        $this->assertNull($this->tree_value($restored, (int) $user->id));
        $this->assertNull($this->tree_value($restored, (int) $user->id, true));
        $this->assertNull($this->tree_value([], (int) $user->id));
        // Control: the same site keeps the cohort, which evaluates.
        $cohort = $this->getDataGenerator()->create_cohort();
        $kept = (new condition())->restore_config(['cohortid' => (int) $cohort->id], true);
        $this->assertFalse($this->tree_value($kept, (int) $user->id));
    }

    public function test_deleted_cohort_keeps_enrolments_active(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $plugin = new \local_enrolconditions_fake_enforceable();
        $enforcer = new enforcer(['manual' => $plugin]);
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();

        $instances = [];
        $cohorts = [];
        foreach (['deleted', 'control'] as $name) {
            $cohorts[$name] = $generator->create_cohort();
            cohort_add_member($cohorts[$name]->id, $user->id);
            $course = $generator->create_course();
            $DB->delete_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
            $tree = json_encode(['op' => 'and', 'children' => [
                ['type' => 'cohort', 'config' => ['cohortid' => (int) $cohorts[$name]->id]],
            ]]);
            $id = $plugin->add_instance($course, [
                'status' => ENROL_INSTANCE_ENABLED,
                'customtext1' => $tree,
                'customint7' => 0,
                'customint8' => enforceable::ACTION_SUSPEND | 16,
            ]);
            $instances[$name] = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
            $plugin->enrol_user($instances[$name], $user->id);
        }
        $status = fn(string $name): int => (int) $DB->get_field(
            'user_enrolments',
            'status',
            ['enrolid' => $instances[$name]->id, 'userid' => $user->id]
        );

        // The user is in both cohorts: nothing changes.
        $this->assertSame(enforcer::OUTCOME_NOTHING, $enforcer->check_user($instances['deleted'], $user->id));
        $this->assertSame(enforcer::OUTCOME_NOTHING, $enforcer->check_user($instances['control'], $user->id));

        cohort_delete_cohort($cohorts['deleted']);
        condition::reset_existence_cache();
        $this->assertSame(enforcer::OUTCOME_UNKNOWN, $enforcer->check_user($instances['deleted'], $user->id));
        $this->assertDebuggingCalled();
        $this->assertSame(ENROL_USER_ACTIVE, $status('deleted'));
        $this->assertSame(0, $DB->count_records(enforcer::TABLE, ['instanceid' => $instances['deleted']->id]));

        // Control: leaving a cohort that exists suspends.
        cohort_remove_member($cohorts['control']->id, $user->id);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $enforcer->check_user($instances['control'], $user->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $status('control'));
    }

    public function test_existence_is_looked_up_once_per_request(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $cohort = $generator->create_cohort();
        $users = [];
        for ($i = 0; $i < 6; $i++) {
            $users[] = (int) $generator->create_user()->id;
        }
        $condition = new condition();
        $config = ['cohortid' => (int) $cohort->id];
        $reads = function (int $userid) use ($condition, $config, $DB): int {
            $before = $DB->perf_get_reads();
            $condition->is_met($userid, new \stdClass(), $config);
            return $DB->perf_get_reads() - $before;
        };

        $first = $reads($users[0]);
        $warm = $reads($users[1]);
        // Control: the first evaluation pays for the existence check, which is a query of its own.
        $this->assertGreaterThan($warm, $first);
        // Four more users cost the membership query alone, each.
        $before = $DB->perf_get_reads();
        foreach (array_slice($users, 2) as $userid) {
            $condition->is_met($userid, new \stdClass(), $config);
        }
        $this->assertSame(4 * $warm, $DB->perf_get_reads() - $before);

        // A missing cohort is looked up once as well, and then costs no query at all.
        $missing = ['cohortid' => 999999];
        $attempt = function (int $userid) use ($condition, $missing, $DB): int {
            $before = $DB->perf_get_reads();
            try {
                $condition->is_met($userid, new \stdClass(), $missing);
            } catch (\moodle_exception $e) {
                $this->assertSame('missingcohort', $e->errorcode);
            }
            return $DB->perf_get_reads() - $before;
        };
        $this->assertGreaterThan(0, $attempt($users[0]));
        $this->assertSame(0, $attempt($users[1]));
        $this->assertSame(0, $attempt($users[2]));
    }

    public function test_description(): void {
        $this->resetAfterTest();
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Students <b>2026</b>']);
        $condition = new condition();
        $this->assertSame('You are a member of the cohort Students 2026', $condition->get_summary(['cohortid' => $cohort->id]));
        $this->assertSame(
            'You are a member of a cohort that no longer exists (id 999999)',
            $condition->get_description(['cohortid' => 999999], false)
        );
    }

    public function test_validation(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $systemcohort = $this->getDataGenerator()->create_cohort();
        $othercategory = $this->getDataGenerator()->create_category();
        $hidden = $this->getDataGenerator()->create_cohort([
            'contextid' => \context_coursecat::instance($othercategory->id)->id,
        ]);
        $PAGE->set_context(\context_course::instance($course->id));
        $condition = new condition();

        $this->assertSame([], $condition->validate_config(['cohortid' => $systemcohort->id]));
        $this->assertArrayHasKey('cohortid', $condition->validate_config(['cohortid' => 0]));
        $this->assertArrayHasKey('cohortid', $condition->validate_config(['cohortid' => 999999]));

        $options = $condition->get_cohort_options();
        $this->assertArrayHasKey($systemcohort->id, $options);
        $this->assertArrayNotHasKey($hidden->id, $options);
        $context = \context_course::instance($course->id);
        $this->assertNull($condition->check_access(['cohortid' => $systemcohort->id], $context));
        $this->assertSame(
            get_string('noaccess', 'enrolcond_cohort'),
            $condition->check_access(['cohortid' => $hidden->id], $context)
        );
    }

    public function test_check_access_follows_cohort_visibility(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $visible = $generator->create_cohort(['name' => 'Visible']);
        $invisible = $generator->create_cohort(['name' => 'Invisible', 'visible' => 0]);
        $condition = new condition();

        $this->setUser($teacher);
        $this->assertNull($condition->check_access(['cohortid' => $visible->id], $context));
        $this->assertNotNull($condition->check_access(['cohortid' => $invisible->id], $context));
        $this->assertNotNull($condition->check_access(['cohortid' => 999999], $context));

        // Control: moodle/cohort:view in the cohort's context makes the hidden cohort usable.
        $this->setAdminUser();
        $this->assertNull($condition->check_access(['cohortid' => $invisible->id], $context));
    }

    public function test_privacy_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('enrolcond_cohort'));
    }

    public function test_form(): void {
        $this->resetAfterTest();
        $condition = new condition();
        $this->assertSame(['cohortid' => 5], $condition->config_from_form_data((object) ['cohortid' => '5']));
        $form = new \MoodleQuickForm('test', 'post', '');
        $condition->config_form_definition($form);
        $this->assertTrue($form->elementExists('cohortid'));
        $this->assertTrue($form->elementExists('nocohorts'));
        $this->assertContains(\core\event\cohort_member_added::class, $condition->get_observed_events());
        $this->assertSame('cohort', $condition->get_type());
    }
}
