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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrolcond_userlist\condition;
use enrolcond_userlist\privacy\provider;

/**
 * Tests of the list of usernames condition.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(condition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrolcond_userlist\privacy\provider::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_enrolconditions\local\usernames::class)]
final class userlist_test extends \core_privacy\tests\provider_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_tree_writer.php');
        parent::setUpBeforeClass();
    }

    #[\Override]
    public function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Stores a tree with one list of usernames in the manual instance of a new course.
     *
     * @param string[] $usernames
     * @return \stdClass The course.
     */
    protected function course_with_list(array $usernames): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('enrol', 'customtext1', json_encode(['op' => 'and', 'children' => [
            ['type' => 'userlist', 'config' => ['usernames' => $usernames]],
        ]]), ['courseid' => $course->id, 'enrol' => 'manual']);
        return $course;
    }

    /**
     * Usernames stored in the list of a course.
     *
     * @param \stdClass $course
     * @return string[]
     */
    protected function stored_list(\stdClass $course): array {
        global $DB;
        $json = $DB->get_field('enrol', 'customtext1', ['courseid' => $course->id, 'enrol' => 'manual']);
        return json_decode($json, true)['children'][0]['config']['usernames'];
    }

    public function test_is_met(): void {
        $this->resetAfterTest();
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $bia = $this->getDataGenerator()->create_user(['username' => 'bia']);
        $condition = new condition();
        $config = $condition->config_from_form_data((object) ['usernames' => " ANA, carla;\n\ndavi  ana"]);

        $this->assertSame(['usernames' => ['ana', 'carla', 'davi']], $config);
        $this->assertTrue($condition->is_met((int) $ana->id, new \stdClass(), $config));
        $this->assertFalse($condition->is_met((int) $bia->id, new \stdClass(), $config));
        $this->assertFalse($condition->is_met(999999, new \stdClass(), $config));
    }

    public function test_restore_from_another_site_is_unknown(): void {
        $this->resetAfterTest();
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $condition = new condition();
        $config = ['usernames' => ['ana', 'bia']];

        // Control: from this site the list is kept.
        $this->assertSame($config, $condition->restore_config($config, true));
        $this->assertTrue($condition->is_met((int) $ana->id, new \stdClass(), $condition->restore_config($config, true)));

        $restored = $condition->restore_config($config, false);
        $this->assertSame(['usernames' => []], $restored);
        try {
            $condition->is_met((int) $ana->id, new \stdClass(), $restored);
            $this->fail('An empty list must be unknown');
        } catch (\moodle_exception $e) {
            $this->assertSame('emptylist', $e->errorcode);
        }
    }

    public function test_privacy(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('enrolcond_userlist'));
        $collection = \enrolcond_userlist\privacy\provider::get_metadata(
            new \core_privacy\local\metadata\collection('enrolcond_userlist')
        );
        $items = $collection->get_collection();
        $this->assertCount(1, $items);
        $this->assertSame('enrol', $items[0]->get_name());
        $this->assertSame(['usernames' => 'privacy:metadata:enrol:usernames'], $items[0]->get_privacy_fields());
    }

    public function test_description_and_form(): void {
        $condition = new condition();
        $this->assertSame('Your username is in a list of 2 users', $condition->get_summary(['usernames' => ['a', 'b']]));
        $this->assertSame(['usernames' => "a\nb"], $condition->config_to_form_data(['usernames' => ['a', 'b']]));
        $form = new \MoodleQuickForm('test', 'post', '');
        $condition->config_form_definition($form);
        $this->assertTrue($form->elementExists('usernames'));
        $this->assertSame([], $condition->get_observed_events());
    }

    public function test_validate_config(): void {
        $condition = new condition();
        $this->assertSame([], $condition->validate_config(['usernames' => ['ana', 'joao.silva', '2080882']]));
        $this->assertArrayHasKey('usernames', $condition->validate_config(['usernames' => []]));
        $this->assertArrayHasKey('usernames', $condition->validate_config([]));
        $errors = $condition->validate_config(['usernames' => ['ana', 'bad<user>']]);
        $this->assertStringContainsString('bad&lt;user&gt;', $errors['usernames']);
        $many = array_map(fn($i) => 'u' . $i, range(1, condition::MAX_USERNAMES + 1));
        $this->assertArrayHasKey('usernames', $condition->validate_config(['usernames' => $many]));
    }

    public function test_parse_csv(): void {
        $content = "\xEF\xBB\xBFusername,firstname\r\n\"Ana\",Ana\r\nbia;x\n\n carla\tC\nana\n";
        $this->assertSame(['ana', 'bia', 'carla'], \local_enrolconditions\local\usernames::parse_csv($content));
        $this->assertSame(['x'], \local_enrolconditions\local\usernames::parse_csv("x\n"));
    }

    public function test_privacy_finds_exports_and_erases_a_listed_user(): void {
        $this->resetAfterTest();
        manager::register_enforceable_for_testing('manual', new \local_enrolconditions_fake_tree_writer());
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $bia = $this->getDataGenerator()->create_user(['username' => 'bia']);
        $dora = $this->getDataGenerator()->create_user(['username' => 'dora']);
        $course = $this->course_with_list(['ana', 'bia', 'carla']);
        $other = $this->course_with_list(['bia']);
        $context = \context_course::instance($course->id);

        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $ana->id)->get_contextids());
        // Control: a user no list names is found nowhere.
        $this->assertSame([], provider::get_contexts_for_userid((int) $dora->id)->get_contextids());
        $userlist = new userlist($context, 'enrolcond_userlist');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$ana->id, $bia->id], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($ana, 'enrolcond_userlist', [$context->id]));
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'enrolcond_userlist')]);
        $this->assertSame('ana', $data->username);
        $this->assertCount(1, $data->instances);

        // Erased through the contexts the finder gave.
        $contextids = provider::get_contexts_for_userid((int) $ana->id)->get_contextids();
        provider::delete_data_for_user(new approved_contextlist($ana, 'enrolcond_userlist', $contextids));
        $this->assertSame(['bia', 'carla'], $this->stored_list($course));
        $this->assertSame([], provider::get_contexts_for_userid((int) $ana->id)->get_contextids());

        provider::delete_data_for_users(new approved_userlist($context, 'enrolcond_userlist', [$bia->id]));
        $this->assertSame(['carla'], $this->stored_list($course));
        // Control: the list of another course keeps the name.
        $this->assertSame(['bia'], $this->stored_list($other));

        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame([], $this->stored_list($course));
        $this->assertSame(['bia'], $this->stored_list($other));
    }

    public function test_privacy_erases_a_deleted_user(): void {
        $this->resetAfterTest();
        manager::register_enforceable_for_testing('manual', new \local_enrolconditions_fake_tree_writer());
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $bia = $this->getDataGenerator()->create_user(['username' => 'bia']);
        $course = $this->course_with_list(['ana', 'bia']);
        $context = \context_course::instance($course->id);
        delete_user($ana);

        // The deleted account is found by core's md5 of its username, through the finder and the userlist.
        $contextids = provider::get_contexts_for_userid((int) $ana->id)->get_contextids();
        $this->assertEquals([$context->id], $contextids);
        $userlist = new userlist($context, 'enrolcond_userlist');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$ana->id, $bia->id], $userlist->get_userids());

        provider::delete_data_for_user(new approved_contextlist($ana, 'enrolcond_userlist', $contextids));
        $this->assertSame(['bia'], $this->stored_list($course));
        $this->assertSame([], provider::get_contexts_for_userid((int) $ana->id)->get_contextids());
        // Control: the user still listed is still found.
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $bia->id)->get_contextids());
    }

    public function test_privacy_leaves_the_list_of_a_method_that_cannot_write_it(): void {
        $this->resetAfterTest();
        manager::register_enforceable_for_testing('manual', new \local_enrolconditions_fake_enforceable());
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $course = $this->course_with_list(['ana', 'bia']);
        $context = \context_course::instance($course->id);

        // Found and exported all the same.
        $contextids = provider::get_contexts_for_userid((int) $ana->id)->get_contextids();
        $this->assertEquals([$context->id], $contextids);
        provider::delete_data_for_user(new approved_contextlist($ana, 'enrolcond_userlist', $contextids));
        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(['ana', 'bia'], $this->stored_list($course));
    }

    /**
     * Stores any text as the tree of the manual instance of a new course.
     *
     * @param string $text Stored value.
     * @return \stdClass The course.
     */
    protected function course_with_text(string $text): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('enrol', 'customtext1', $text, ['courseid' => $course->id, 'enrol' => 'manual']);
        return $course;
    }

    /**
     * Stored value of the manual instance of a course.
     *
     * @param \stdClass $course
     * @return string
     */
    protected function stored_text(\stdClass $course): string {
        global $DB;
        return (string) $DB->get_field('enrol', 'customtext1', ['courseid' => $course->id, 'enrol' => 'manual']);
    }

    public function test_privacy_finds_and_erases_names_in_a_tree_that_does_not_validate(): void {
        $this->resetAfterTest();
        manager::register_enforceable_for_testing('manual', new \local_enrolconditions_fake_tree_writer());
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $bia = $this->getDataGenerator()->create_user(['username' => 'bia']);
        $dora = $this->getDataGenerator()->create_user(['username' => 'dora']);
        // An unknown operator at the root: a restore keeps such a tree, and it still lists names.
        $stored = '{"op":"xor","note":"kept","children":[{"type":"userlist","config":{"usernames":["ana","bia"]}},'
            . '{"op":"and","children":[{"type":"userlist","negate":true,"config":{"usernames":["ana","carla"],"x":{}}},'
            . '{"type":"userlist","config":{"usernames":"ana"}}]}]}';
        $course = $this->course_with_text($stored);
        $context = \context_course::instance($course->id);
        try {
            \local_enrolconditions\tree::from_json($stored);
            $this->fail('The value must not be a valid tree.');
        } catch (\local_enrolconditions\invalid_tree_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $ana->id)->get_contextids());
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $bia->id)->get_contextids());
        // Control: a user no list names is found nowhere.
        $this->assertSame([], provider::get_contexts_for_userid((int) $dora->id)->get_contextids());
        $userlist = new userlist($context, 'enrolcond_userlist');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$ana->id, $bia->id], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($ana, 'enrolcond_userlist', [$context->id]));
        $exported = writer::with_context($context)->get_data([get_string('pluginname', 'enrolcond_userlist')]);
        $this->assertSame('ana', $exported->username);

        // Control first: an unlisted user changes nothing, not a byte of the stored value.
        provider::delete_data_for_user(new approved_contextlist($dora, 'enrolcond_userlist', [$context->id]));
        $this->assertSame($stored, $this->stored_text($course));

        provider::delete_data_for_user(new approved_contextlist($ana, 'enrolcond_userlist', [$context->id]));
        $expected = '{"op":"xor","note":"kept","children":[{"type":"userlist","config":{"usernames":["bia"]}},'
            . '{"op":"and","children":[{"type":"userlist","negate":true,"config":{"usernames":["carla"],"x":{}}},'
            . '{"type":"userlist","config":{"usernames":"ana"}}]}]}';
        $this->assertSame($expected, $this->stored_text($course));
        $this->assertSame([], provider::get_contexts_for_userid((int) $ana->id)->get_contextids());
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $bia->id)->get_contextids());

        provider::delete_data_for_users(new approved_userlist($context, 'enrolcond_userlist', [$bia->id]));
        $this->assertStringNotContainsString('bia', $this->stored_text($course));
        $this->assertStringContainsString('carla', $this->stored_text($course));

        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(
            '{"op":"xor","note":"kept","children":[{"type":"userlist","config":{"usernames":[]}},'
            . '{"op":"and","children":[{"type":"userlist","negate":true,"config":{"usernames":[],"x":{}}},'
            . '{"type":"userlist","config":{"usernames":"ana"}}]}]}',
            $this->stored_text($course)
        );
    }

    public function test_privacy_leaves_the_names_of_other_conditions_in_a_tree_that_does_not_validate(): void {
        $this->resetAfterTest();
        manager::register_enforceable_for_testing('manual', new \local_enrolconditions_fake_tree_writer());
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $dora = $this->getDataGenerator()->create_user(['username' => 'dora']);
        // Another condition keeps a list under the same key, and a list that is not a list is none.
        $stored = '{"op":"xor","children":[{"type":"userlist","config":{"usernames":["dora"]}},'
            . '{"type":"other","config":{"usernames":["ana"]}},{"type":"userlist","config":{"usernames":{"0":"ana"}}}]}';
        $course = $this->course_with_text($stored);
        $context = \context_course::instance($course->id);

        $this->assertSame([], provider::get_contexts_for_userid((int) $ana->id)->get_contextids());
        provider::delete_data_for_user(new approved_contextlist($ana, 'enrolcond_userlist', [$context->id]));
        $this->assertSame($stored, $this->stored_text($course));

        // Control: the list of the condition itself is found and erased in the same value.
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $dora->id)->get_contextids());
        provider::delete_data_for_user(new approved_contextlist($dora, 'enrolcond_userlist', [$context->id]));
        $this->assertStringNotContainsString('dora', $this->stored_text($course));
    }

    public function test_privacy_finds_no_list_in_a_value_that_is_not_json(): void {
        $this->resetAfterTest();
        manager::register_enforceable_for_testing('manual', new \local_enrolconditions_fake_tree_writer());
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $stored = 'not json: "userlist" ["ana"]';
        $course = $this->course_with_text($stored);
        $context = \context_course::instance($course->id);

        $this->assertSame([], provider::get_contexts_for_userid((int) $ana->id)->get_contextids());
        provider::delete_data_for_user(new approved_contextlist($ana, 'enrolcond_userlist', [$context->id]));
        $this->assertSame($stored, $this->stored_text($course));
    }

    public function test_the_userfile_provider_matches_names_through_the_shared_matcher(): void {
        global $CFG;
        $path = '/local/enrolconditions/condition/userfile/classes/privacy/provider.php';
        $source = (string) file_get_contents($CFG->dirroot . $path);
        $this->assertStringContainsString('usernames::matcher(', $source);
        $this->assertStringContainsString('usernames::names_any(', $source);
        // The third copy of the rule hashed names itself.
        $this->assertStringNotContainsString('md5(', $source);
    }
}
