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

use enrolcond_profile\condition;
use local_enrolconditions\tree;

/**
 * Tests of the user profile condition.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(condition::class)]
final class profile_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Cases of is_met for a user with email "Ana.Silva@IFRN.edu.br" and an empty department.
     *
     * @return array
     */
    public static function is_met_provider(): array {
        return [
            'equals, ignoring case and spaces' => ['email', 'isequalto', ' ana.silva@ifrn.edu.br ', true],
            'equals, different' => ['email', 'isequalto', 'ana@ifrn.edu.br', false],
            'contains' => ['email', 'contains', '@IFRN', true],
            'contains, missing' => ['email', 'contains', '@ufrn', false],
            'does not contain' => ['email', 'doesnotcontain', '@ufrn', true],
            'does not contain, present' => ['email', 'doesnotcontain', 'silva', false],
            'starts with' => ['email', 'startswith', 'ana.', true],
            'starts with, no' => ['email', 'startswith', 'silva', false],
            'ends with' => ['email', 'endswith', '.edu.br', true],
            'ends with, no' => ['email', 'endswith', '.com', false],
            'is empty' => ['department', 'isempty', '', true],
            'is empty, filled' => ['email', 'isempty', '', false],
            'is not empty' => ['email', 'isnotempty', '', true],
            'is not empty, empty' => ['department', 'isnotempty', '', false],
            'unknown operator' => ['email', 'xor', 'x', false],
        ];
    }

    /**
     * Test is_met with standard fields.
     *
     * @param string $field
     * @param string $operator
     * @param string $value
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('is_met_provider')]
    public function test_is_met(string $field, string $operator, string $value, bool $expected): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'Ana.Silva@IFRN.edu.br', 'department' => '']);
        $config = ['field' => $field, 'operator' => $operator, 'value' => $value];
        $this->assertSame($expected, (new condition())->is_met((int) $user->id, new \stdClass(), $config));
    }

    public function test_custom_field(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'campus', 'name' => 'Campus <b>name</b>', 'defaultdata' => 'Natal',
        ]);
        $user = $this->getDataGenerator()->create_user(['profile_field_campus' => 'Mossoró']);
        $other = $this->getDataGenerator()->create_user();
        $condition = new condition();
        $config = ['field' => 'profile_field_campus', 'operator' => 'isequalto', 'value' => 'mossoró'];

        $this->assertTrue($condition->is_met((int) $user->id, new \stdClass(), $config));
        $this->assertFalse($condition->is_met((int) $other->id, new \stdClass(), $config));
        // Users without a value get the default.
        $config['value'] = 'Natal';
        $this->assertTrue($condition->is_met((int) $other->id, new \stdClass(), $config));

        $this->assertArrayHasKey('profile_field_campus', $condition->get_fields());
        $this->assertSame([], $condition->validate_config($config));
        $this->assertSame('Your Campus name is "Natal"', $condition->get_description($config, false));
    }

    /**
     * Value of a tree with one profile condition for a user, through the tree.
     *
     * @param array $config
     * @param int $userid
     * @param bool $negate
     * @return bool|null Null when unknown; the debugging message of the failed condition is consumed.
     */
    protected function tree_value(array $config, int $userid, bool $negate = false): ?bool {
        $root = ['op' => 'and', 'children' => [['type' => 'profile', 'negate' => $negate, 'config' => $config]]];
        $value = tree::from_array($root)->evaluate($userid, (object) ['id' => 0, 'courseid' => SITEID])->get_value();
        if ($value === null) {
            $this->assertDebuggingCalled();
        }
        return $value;
    }

    public function test_deleted_custom_field_is_unknown_not_unmet(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'campus', 'name' => 'Campus']);
        $user = $this->getDataGenerator()->create_user(['profile_field_campus' => 'Natal']);
        $config = ['field' => 'profile_field_campus', 'operator' => 'isequalto', 'value' => 'natal'];

        // Control: while the field exists the answer is true or false, negated or not.
        $this->assertTrue($this->tree_value($config, (int) $user->id));
        $this->assertFalse($this->tree_value($config, (int) $user->id, true));
        $this->assertFalse($this->tree_value(['value' => 'mossoró'] + $config, (int) $user->id));

        // Whatever the operator, a deleted field is unknown: "is empty" and "does not contain" are no exception.
        require_once($CFG->dirroot . '/user/profile/definelib.php');
        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'old', 'name' => 'Old',
        ]);
        $oldconfig = ['field' => 'profile_field_old', 'operator' => 'isempty', 'value' => ''];
        $this->assertTrue($this->tree_value($oldconfig, (int) $user->id));
        profile_delete_field($field->id);
        $this->assertNull($this->tree_value($oldconfig, (int) $user->id));
        $this->assertNull($this->tree_value($oldconfig, (int) $user->id, true));
        $this->assertNull($this->tree_value(['operator' => 'doesnotcontain', 'value' => 'x'] + $oldconfig, (int) $user->id, true));
        try {
            (new condition())->is_met((int) $user->id, new \stdClass(), $oldconfig);
            $this->fail('A deleted field cannot be evaluated');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingfield', $e->errorcode);
        }
    }

    public function test_field_outside_the_allowed_list_is_unknown(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'ana@example.com']);
        $config = ['field' => 'password', 'operator' => 'isnotempty', 'value' => ''];
        $this->assertNull($this->tree_value($config, (int) $user->id));
        $this->assertNull($this->tree_value($config, (int) $user->id, true));
        $this->assertNull($this->tree_value(['field' => ''] + $config, (int) $user->id));
        $this->assertNull($this->tree_value([], (int) $user->id));
        // Control: a field of the list evaluates.
        $this->assertTrue($this->tree_value(['field' => 'email'] + $config, (int) $user->id));
        $this->assertFalse($this->tree_value(['field' => 'email'] + $config, (int) $user->id, true));
    }

    public function test_description_is_escaped(): void {
        $this->resetAfterTest();
        $condition = new condition();
        $this->assertSame(
            'Your Email address contains "&lt;script&gt;"',
            $condition->get_description(['field' => 'email', 'operator' => 'contains', 'value' => '<script>'], false)
        );
        $this->assertSame(
            'Your Department is empty',
            $condition->get_summary(['field' => 'department', 'operator' => 'isempty', 'value' => ''])
        );
    }

    public function test_validate_config(): void {
        $this->resetAfterTest();
        $condition = new condition();
        $this->assertSame([], $condition->validate_config(['field' => 'email', 'operator' => 'contains', 'value' => 'x']));
        $this->assertSame([], $condition->validate_config(['field' => 'email', 'operator' => 'isempty', 'value' => '']));
        $errors = $condition->validate_config(['field' => 'password', 'operator' => 'xor', 'value' => '']);
        $this->assertArrayHasKey('field', $errors);
        $this->assertArrayHasKey('operator', $errors);
        $this->assertArrayHasKey('value', $condition->validate_config(['field' => 'email', 'operator' => 'contains']));
    }

    public function test_form(): void {
        $this->resetAfterTest();
        $condition = new condition();
        $this->assertSame(
            ['field' => 'email', 'operator' => 'isempty', 'value' => ''],
            $condition->config_from_form_data((object) ['field' => 'email', 'operator' => 'isempty', 'value' => 'ignored'])
        );
        $this->assertSame(
            ['field' => 'city', 'operator' => 'contains', 'value' => 'Natal'],
            $condition->config_from_form_data((object) ['field' => 'city', 'operator' => 'contains', 'value' => ' Natal '])
        );
        $form = new \MoodleQuickForm('test', 'post', '');
        $condition->config_form_definition($form);
        $this->assertTrue($form->elementExists('field'));
        $this->assertTrue($form->elementExists('operator'));
        $this->assertTrue($form->elementExists('value'));
        $this->assertSame([\core\event\user_updated::class], $condition->get_observed_events());
        $this->assertSame('profile', $condition->get_type());
    }

    public function test_privacy_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('enrolcond_profile'));
    }
}
