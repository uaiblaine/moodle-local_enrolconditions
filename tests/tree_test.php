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

use local_enrolconditions\condition\editor_access;
use local_enrolconditions\condition\manager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_condition.php');
require_once(__DIR__ . '/fixtures/scripted_condition.php');

/**
 * Tests of the condition tree: structure, AND/OR/NOT evaluation, unknown types and validation.
 *
 * The fake condition is met when the user id is in config['userids'].
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(tree_result::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(invalid_tree_exception::class)]
final class tree_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        manager::register_for_testing('scripted', new fixtures\scripted_condition());
        fixtures\scripted_condition::$calls = 0;
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Condition node of the fake type.
     *
     * @param int[] $userids Users that meet it.
     * @param bool $negate
     * @return array
     */
    protected static function cond(array $userids, bool $negate = false): array {
        return ['type' => 'fake', 'negate' => $negate, 'config' => ['userids' => $userids]];
    }

    /**
     * Group node.
     *
     * @param string $op
     * @param array $children
     * @param bool $negate
     * @return array
     */
    protected static function group(string $op, array $children, bool $negate = false): array {
        return ['op' => $op, 'negate' => $negate, 'children' => $children];
    }

    /**
     * Trees and the expected result for user 1.
     *
     * @return array
     */
    public static function evaluation_provider(): array {
        $yes = self::cond([1]);
        $no = self::cond([2]);
        $unknown = ['type' => 'missing', 'negate' => false, 'config' => []];
        return [
            'empty tree' => [self::group('and', []), true],
            'and all met' => [self::group('and', [$yes, $yes]), true],
            'and one not met' => [self::group('and', [$yes, $no]), false],
            'or one met' => [self::group('or', [$no, $yes]), true],
            'or none met' => [self::group('or', [$no, $no]), false],
            'negated condition met' => [self::group('and', [self::cond([1], true)]), false],
            'negated condition not met' => [self::group('and', [self::cond([2], true)]), true],
            'negated group' => [self::group('and', [$yes, $yes], true), false],
            'negated or group' => [self::group('or', [$no, $no], true), true],
            'nested' => [self::group('and', [$yes, self::group('or', [$no, self::group('and', [$yes, $yes])])]), true],
            'nested not met' => [self::group('and', [$yes, self::group('or', [$no, self::group('and', [$yes, $no])])]), false],
            'unknown alone' => [self::group('and', [$unknown]), null],
            'unknown and false' => [self::group('and', [$unknown, $no]), false],
            'unknown and true' => [self::group('and', [$unknown, $yes]), null],
            'unknown or true' => [self::group('or', [$unknown, $yes]), true],
            'unknown or false' => [self::group('or', [$unknown, $no]), null],
            'negated unknown stays unknown' => [self::group('and', [self::group('and', [$unknown], true)]), null],
        ];
    }

    /**
     * Evaluation with three values.
     *
     * @param array $root
     * @param bool|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('evaluation_provider')]
    public function test_evaluate(array $root, ?bool $expected): void {
        $result = tree::from_array($root)->evaluate(1, (object) ['id' => 1, 'courseid' => 1]);
        $this->assertSame($expected, $result->get_value());
        $this->assertSame($expected === true, $result->is_met());
        $this->assertSame($expected === false, $result->is_not_met());
        $this->assertSame($expected === null, $result->is_unknown());
        if ($expected === true) {
            $this->assertSame([], $result->get_failures());
        } else {
            $this->assertNotEmpty($result->get_failures());
        }
    }

    public function test_failure_messages(): void {
        $instance = (object) ['id' => 1, 'courseid' => 1];

        $result = tree::from_array(self::group('and', [self::cond([2]), self::cond([1])]))->evaluate(1, $instance);
        $this->assertSame([['message' => 'Not a listed user', 'children' => []]], $result->get_failures());

        $result = tree::from_array(self::group('and', [self::cond([1], true)]))->evaluate(1, $instance);
        $this->assertSame(
            get_string('failurenot', 'local_enrolconditions', 'Listed user'),
            $result->get_failures()[0]['message']
        );

        $result = tree::from_array(self::group('or', [self::cond([2]), self::cond([3])]))->evaluate(1, $instance);
        $failures = $result->get_failures();
        $this->assertCount(1, $failures);
        $this->assertSame(get_string('failureoneof', 'local_enrolconditions'), $failures[0]['message']);
        $this->assertCount(2, $failures[0]['children']);
    }

    public function test_json_round_trip(): void {
        $root = self::group('and', [self::cond([1]), self::group('or', [self::cond([2], true)], true)]);
        $json = tree::from_array($root)->to_json();
        $this->assertSame($root, tree::from_json($json)->to_array());

        // Characters that HTML or PARAM_NOTAGS would touch are escaped.
        $tree = tree::from_array(self::group('and', [['type' => 'fake', 'config' => ['text' => '<b>"x" & \'y\'</b>']]]));
        $json = $tree->to_json();
        $this->assertDoesNotMatchRegularExpression('/[<>&\']/', $json);
        $this->assertSame($json, clean_param($json, PARAM_NOTAGS));
        $this->assertSame('<b>"x" & \'y\'</b>', tree::from_json($json)->to_array()['children'][0]['config']['text']);
    }

    public function test_empty(): void {
        $this->assertTrue(tree::from_json(null)->is_empty());
        $this->assertTrue(tree::from_json('')->is_empty());
        $this->assertNull(tree::from_json('{"op":"and","children":[]}')->to_json());
        $this->assertTrue(tree::empty()->evaluate(1, (object) ['id' => 1])->is_met());
    }

    /**
     * Invalid trees.
     *
     * @return array
     */
    public static function invalid_provider(): array {
        return [
            'not json' => ['{'],
            'json scalar' => ['5'],
            'root is a condition' => ['{"type":"fake","config":{}}'],
            'bad operator' => ['{"op":"xor","children":[]}'],
            'negate not bool' => ['{"op":"and","negate":"yes","children":[]}'],
            'children not list' => ['{"op":"and","children":{"a":1}}'],
            'bad type' => ['{"op":"and","children":[{"type":"Fake!","config":{}}]}'],
            'config not object' => ['{"op":"and","children":[{"type":"fake","config":"x"}]}'],
            'extra key' => ['{"op":"and","children":[],"evil":1}'],
            'too deep' => ['{"op":"and","children":[{"op":"and","children":[{"op":"and","children":[{"op":"and","children":'
                . '[{"op":"and","children":[{"op":"and","children":[]}]}]}]}]}]}'],
        ];
    }

    /**
     * Invalid structures throw.
     *
     * @param string $json
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_provider')]
    public function test_invalid_structure(string $json): void {
        $this->expectException(invalid_tree_exception::class);
        tree::from_json($json);
    }

    public function test_too_many_nodes(): void {
        $children = array_fill(0, tree::MAX_NODES, self::cond([1]));
        $this->expectException(invalid_tree_exception::class);
        tree::from_array(self::group('and', $children));
    }

    public function test_validate(): void {
        $valid = tree::from_array(self::group('and', [self::cond([1])]));
        $this->assertSame([], $valid->validate());

        // Configuration errors come from the condition.
        $badconfig = tree::from_array(self::group('and', [self::cond([])]));
        $this->assertCount(1, $badconfig->validate());

        // Unknown types are refused, unless they were already in the saved tree.
        $unknown = tree::from_array(self::group('and', [['type' => 'missing', 'config' => []]]));
        $this->assertSame([get_string('treeunknowntype', 'local_enrolconditions', 'missing')], $unknown->validate());
        $this->assertSame([], $unknown->validate(['missing']));
    }

    public function test_types_and_events(): void {
        $tree = tree::from_array(self::group('and', [self::cond([1]), self::group('or', [self::cond([2])]),
            ['type' => 'missing', 'config' => []]]));
        $this->assertEqualsCanonicalizing(['fake', 'missing'], $tree->get_types());
        $this->assertSame([\core\event\user_updated::class], $tree->get_observed_events());
    }

    public function test_for_restore(): void {
        $this->resetAfterTest();
        $tree = tree::from_array(['op' => 'and', 'children' => [
            ['type' => 'cohort', 'config' => ['cohortid' => 5]],
            ['op' => 'or', 'negate' => true, 'children' => [
                ['type' => 'coursecompleted', 'config' => ['courseid' => 7]],
                ['type' => 'missing', 'config' => ['x' => 1]],
                ['type' => 'fake', 'config' => ['userids' => [3]]],
            ]],
        ]]);

        $this->assertSame($tree->to_array(), $tree->for_restore(true)->to_array());

        $other = $tree->for_restore(false)->to_array();
        $this->assertSame(['cohortid' => 0], $other['children'][0]['config']);
        $this->assertTrue($other['children'][1]['negate']);
        $this->assertSame(['courseid' => 0], $other['children'][1]['children'][0]['config']);
        $this->assertSame(['x' => 1], $other['children'][1]['children'][1]['config']);
        $this->assertSame(['userids' => [3]], $other['children'][1]['children'][2]['config']);
    }

    /**
     * Evaluation without explanations gives the same three values.
     *
     * @param array $root
     * @param bool|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('evaluation_provider')]
    public function test_evaluate_without_explanations(array $root, ?bool $expected): void {
        $result = tree::from_array($root)->evaluate(1, (object) ['id' => 1, 'courseid' => 1], false);
        $this->assertSame($expected, $result->get_value());
        $this->assertSame([], $result->get_failures());
    }

    /**
     * Scripted condition node.
     *
     * @param bool $met
     * @param array $extra More configuration.
     * @return array
     */
    protected static function scripted(bool $met, array $extra = []): array {
        return ['type' => 'scripted', 'negate' => false, 'config' => ['met' => $met] + $extra];
    }

    public function test_evaluation_without_explanations_stops_at_the_deciding_child(): void {
        $instance = (object) ['id' => 1, 'courseid' => 1];

        $this->assertFalse(tree::from_array(self::group('and', [self::cond([2]), self::scripted(true)]))
            ->evaluate(1, $instance, false)->get_value());
        $this->assertTrue(tree::from_array(self::group('or', [self::cond([1]), self::scripted(false)]))
            ->evaluate(1, $instance, false)->get_value());
        $this->assertSame(0, fixtures\scripted_condition::$calls);

        // Controls: a child that does not decide lets the next one run, and the full evaluation runs all.
        $this->assertTrue(tree::from_array(self::group('and', [self::cond([1]), self::scripted(true)]))
            ->evaluate(1, $instance, false)->get_value());
        $this->assertSame(1, fixtures\scripted_condition::$calls);
        tree::from_array(self::group('and', [self::cond([2]), self::scripted(true)]))->evaluate(1, $instance);
        $this->assertSame(2, fixtures\scripted_condition::$calls);

        // An unknown child does not decide: a later false still makes AND false.
        $unknown = ['type' => 'missing', 'negate' => false, 'config' => []];
        $this->assertFalse(tree::from_array(self::group('and', [$unknown, self::scripted(false)]))
            ->evaluate(1, $instance, false)->get_value());
    }

    public function test_disabled_type_is_unknown(): void {
        $instance = (object) ['id' => 1, 'courseid' => 1];
        $tree = tree::from_array(self::group('and', [['type' => 'date', 'config' => ['to' => time() - HOURSECS]]]));
        $this->assertFalse($tree->evaluate(1, $instance)->get_value());

        set_config('disabled', 1, 'enrolcond_date');
        $this->assertNull($tree->evaluate(1, $instance)->get_value());
        $this->assertNull($tree->evaluate(1, $instance, false)->get_value());
        $this->assertSame(
            get_string('failureunknown', 'local_enrolconditions'),
            $tree->evaluate(1, $instance)->get_failures()[0]['message']
        );

        // A condition provided without a switch (here registered by the test) cannot be disabled.
        $this->assertTrue(tree::from_array(self::group('and', [self::cond([1])]))->evaluate(1, $instance)->is_met());
    }

    public function test_description_that_throws_does_not_escape(): void {
        $instance = (object) ['id' => 1, 'courseid' => 1];
        $result = tree::from_array(self::group('and', [self::scripted(false, ['describethrows' => true])]))
            ->evaluate(1, $instance);
        $this->assertDebuggingCalled();
        $this->assertFalse($result->get_value());
        $this->assertSame(get_string('failuredescription', 'local_enrolconditions'), $result->get_failures()[0]['message']);

        // Control: the description of a condition that does not throw is shown.
        $result = tree::from_array(self::group('and', [self::scripted(false)]))->evaluate(1, $instance);
        $this->assertSame('Scripted not met', $result->get_failures()[0]['message']);
    }

    public function test_validate_asks_access_only_for_new_conditions(): void {
        $context = \context_system::instance();
        $denied = self::scripted(true, ['denied' => 'Not yours']);
        $tree = tree::from_array(self::group('and', [self::cond([1]), $denied]));

        $a = ['name' => 'Scripted condition', 'error' => 'Not yours'];
        $error = get_string('treeconditionerror', 'local_enrolconditions', $a);
        $this->assertSame([$error], $tree->validate([], $context));

        // Without a context the access is not checked.
        $this->assertSame([], $tree->validate());

        // A condition stored unchanged, wherever it moved and whatever its negation, is not asked again.
        $stored = tree::from_array(self::group('or', [self::group('and', [['type' => 'scripted', 'negate' => true,
            'config' => ['denied' => 'Not yours', 'met' => true]]])]));
        $this->assertSame([], $tree->validate([], $context, $stored));

        // A changed configuration is new.
        $changed = tree::from_array(self::group('and', [self::scripted(false, ['denied' => 'Not yours'])]));
        $this->assertSame([$error], $changed->validate([], $context, $stored));
    }

    public function test_validate_and_editor_decide_alike_on_a_stored_node(): void {
        $context = \context_system::instance();
        $config = ['met' => true, 'denied' => 'Not yours'];
        $stored = tree::from_array(self::group('and', [['type' => 'scripted', 'negate' => false, 'config' => $config]]));
        $keys = editor_access::get_node_keys($stored->to_array());

        // Moved into a nested group, negated and with its config keys reordered: still the stored node for both doors.
        $moved = self::group('or', [self::group('and', [['type' => 'scripted', 'negate' => true,
            'config' => ['denied' => 'Not yours', 'met' => true]]])]);
        $this->assertSame([], tree::from_array($moved)->validate([], $context, $stored));
        $this->assertNull(editor_access::check_node('scripted', ['denied' => 'Not yours', 'met' => true], $context, $keys));

        // Control: the same node with a changed value is new for both doors.
        $changed = ['met' => true, 'denied' => 'Other'];
        $newtree = tree::from_array(self::group('and', [['type' => 'scripted', 'negate' => false, 'config' => $changed]]));
        $this->assertCount(1, $newtree->validate([], $context, $stored));
        $this->assertSame('Other', editor_access::check_node('scripted', $changed, $context, $keys));
    }
}
