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

use enrolcond_userfile\condition;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrolcond_userfile\privacy\provider;

/**
 * Tests of the file of usernames condition, its cleanup task and its privacy provider.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(condition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrolcond_userfile\task\cleanup_files::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class userfile_test extends \core_privacy\tests\provider_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_enforceable.php');
        parent::setUpBeforeClass();
    }

    #[\Override]
    public function tearDown(): void {
        \local_enrolconditions\condition\manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Makes the manual instances of the site store their condition tree in customtext1.
     */
    protected function use_fake_enforceable(): void {
        \local_enrolconditions\condition\manager::register_enforceable_for_testing(
            'manual',
            new \local_enrolconditions_fake_enforceable()
        );
    }

    /**
     * Stores a tree with one userfile condition in the manual instance of a course.
     *
     * @param \stdClass $course
     * @param array $config
     */
    protected function store(\stdClass $course, array $config): void {
        global $DB;
        $DB->set_field('enrol', 'customtext1', json_encode(['op' => 'and', 'children' => [
            ['type' => 'userfile', 'config' => $config],
        ]]), ['courseid' => $course->id, 'enrol' => 'manual']);
    }

    /**
     * Puts a file in a new draft area of the current user.
     *
     * @param string $content
     * @param string $filename
     * @return int Draft item id.
     */
    protected function draft(string $content, string $filename = 'students.csv'): int {
        global $USER;
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $draftitemid;
    }

    /**
     * Saves a condition as its form would.
     *
     * @param \stdClass $course
     * @param string $content
     * @param int $fileid File id posted with the form, which must be ignored.
     * @return array Configuration.
     */
    protected function save(\stdClass $course, string $content, int $fileid = 0): array {
        $condition = new condition();
        $data = (object) ['fileid' => $fileid, 'usernamesfile' => $this->draft($content)];
        $this->assertSame([], $condition->config_form_validation((array) $data, []));
        $config = $condition->config_after_submission(
            $condition->config_from_form_data($data),
            $data,
            \context_course::instance($course->id)
        );
        $this->assertSame([], $condition->validate_config($config));
        return $config;
    }

    public function test_save_and_evaluate(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $bia = $this->getDataGenerator()->create_user(['username' => 'bia']);
        $instance = (object) ['courseid' => $course->id];
        $condition = new condition();

        $config = $this->save($course, "username,name\nANA,Ana\ncarla,Carla\n");
        $this->assertGreaterThan(0, $config['fileid']);
        $this->assertSame('students.csv', $config['filename']);
        $this->assertSame(2, $config['count']);
        $this->assertTrue($condition->is_met((int) $ana->id, $instance, $config));
        $this->assertFalse($condition->is_met((int) $bia->id, $instance, $config));
        $this->assertSame('Your username is in the file students.csv (2 users)', $condition->get_summary($config));

        // Replacing the file stores it under a new id, even when the old id is posted: the stored list is untouched.
        $config2 = $this->save($course, "bia\n", $config['fileid']);
        $this->assertNotSame($config['fileid'], $config2['fileid']);
        $this->assertTrue($condition->is_met((int) $bia->id, $instance, $config2));
        $this->assertFalse($condition->is_met((int) $ana->id, $instance, $config2));
        $this->assertTrue($condition->is_met((int) $ana->id, $instance, $config));

        // The form shows the current file.
        $data = $condition->config_to_form_data_in_context($config2, \context_course::instance($course->id));
        $this->assertArrayNotHasKey('fileid', $data);
        global $USER;
        $draftfiles = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $data['usernamesfile'],
            'id',
            false
        );
        $this->assertCount(1, $draftfiles);
        $this->assertSame("bia
", reset($draftfiles)->get_content());
    }

    public function test_missing_file_is_unknown(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        (new condition())->is_met((int) $user->id, (object) ['courseid' => $course->id], ['fileid' => 5, 'count' => 1]);
    }

    public function test_form_validation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $condition = new condition();
        $this->assertArrayHasKey('usernamesfile', $condition->config_form_validation(['usernamesfile' => 0], []));
        $this->assertArrayHasKey('usernamesfile', $condition->config_form_validation(
            ['usernamesfile' => $this->draft("username\n\n")],
            []
        ));
        $errors = $condition->config_form_validation(['usernamesfile' => $this->draft("ana\nbad user<\n")], []);
        $this->assertStringContainsString('bad user&lt;', $errors['usernamesfile']);
        $this->assertArrayHasKey('usernamesfile', $condition->validate_config(['fileid' => 0, 'count' => 3]));

        $form = new \MoodleQuickForm('test', 'post', '');
        $condition->config_form_definition($form);
        $this->assertTrue($form->elementExists('usernamesfile'));
        // The item id is never taken from the form.
        $this->assertFalse($form->elementExists('fileid'));
    }

    public function test_cleanup_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $used = $this->save($course, "ana\n");
        $unused = $this->save($course, "bia\n");
        $recent = $this->save($course, "carla\n");
        // The tree is read through the method's own API (customtext1 here), not from a fixed column.
        $this->store($course, $used);
        $DB->set_field_select(
            'files',
            'timemodified',
            time() - 2 * DAYSECS,
            'component = ? AND itemid <> ?',
            [condition::COMPONENT, $recent['fileid']]
        );

        $this->expectOutputRegex('/1 files of usernames deleted/');
        (new \enrolcond_userfile\task\cleanup_files())->execute();
        $context = \context_course::instance($course->id);
        $this->assertNotNull(condition::get_file($used, $context));
        $this->assertNull(condition::get_file($unused, $context));
        $this->assertNotNull(condition::get_file($recent, $context));
    }

    public function test_check_access_refuses_a_file_another_tree_uses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $condition = new condition();
        $stored = $this->save($course, "ana\n");
        $fresh = $this->save($course, "bia\n");
        $this->store($course, $stored);

        $this->assertSame(get_string('noaccess', condition::COMPONENT), $condition->check_access($stored, $context));
        // Control: the file the dialog has just stored, which no tree uses yet.
        $this->assertNull($condition->check_access($fresh, $context));
        // A file of another course is not found in this one.
        $other = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $this->assertNotNull($condition->check_access($fresh, $other));
    }

    public function test_restore_from_another_site_drops_the_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $instance = (object) ['courseid' => $course->id];
        $condition = new condition();
        $config = $this->save($course, "ana\n");

        // Control: from this site the configuration is kept.
        $this->assertTrue($condition->is_met((int) $ana->id, $instance, $condition->restore_config($config, true)));

        $restored = $condition->restore_config($config, false);
        $this->assertSame(['fileid' => 0, 'filename' => '', 'count' => 0], $restored);
        $this->expectException(\moodle_exception::class);
        $condition->is_met((int) $ana->id, $instance, $restored);
    }

    public function test_privacy_erases_a_deleted_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $bia = $this->getDataGenerator()->create_user(['username' => 'bia']);
        $config = $this->save($course, "username\nana\nbia\n");
        delete_user($ana);
        $hash = condition::get_file($config, $context)->get_contenthash();
        $this->assertArrayHasKey('ana', condition::get_usernames(condition::get_file($config, $context)));

        // The deleted account is found by core's md5 of its username, through the finder and the userlist.
        $contextids = provider::get_contexts_for_userid((int) $ana->id)->get_contextids();
        $this->assertEquals([$context->id], $contextids);
        $userlist = new userlist($context, condition::COMPONENT);
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$ana->id, $bia->id], $userlist->get_userids());
        $this->assertNotFalse(\cache::make(condition::COMPONENT, 'usernames')->get($hash));

        provider::delete_data_for_user(new approved_contextlist($ana, condition::COMPONENT, $contextids));
        $this->assertSame("username\nbia\n", condition::get_file($config, $context)->get_content());
        // The list of the old content is gone from the shared cache too.
        $this->assertFalse(\cache::make(condition::COMPONENT, 'usernames')->get($hash));
        $this->assertSame([], provider::get_contexts_for_userid((int) $ana->id)->get_contextids());
        // Control: the user still listed is still found.
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $bia->id)->get_contextids());
    }

    public function test_privacy_userlist_erases_a_deleted_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $bia = $this->getDataGenerator()->create_user(['username' => 'bia']);
        $config = $this->save($course, "ana\nbia\ncarla\n");
        delete_user($ana);

        provider::delete_data_for_users(new approved_userlist($context, condition::COMPONENT, [$ana->id, $bia->id]));
        $this->assertSame("carla\n", condition::get_file($config, $context)->get_content());
    }

    public function test_privacy_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant(condition::COMPONENT));
    }

    public function test_privacy(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $bia = $this->getDataGenerator()->create_user(['username' => 'bia']);
        $other = $this->getDataGenerator()->create_user(['username' => 'other']);
        $config = $this->save($course, "username\nana\nbia,B\n");

        $this->assertEquals([$context->id], provider::get_contexts_for_userid($ana->id)->get_contextids());
        $this->assertSame([], provider::get_contexts_for_userid($other->id)->get_contextids());
        $userlist = new userlist($context, condition::COMPONENT);
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$ana->id, $bia->id], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($ana, condition::COMPONENT, [$context->id]));
        $data = writer::with_context($context)->get_data([get_string('pluginname', condition::COMPONENT)]);
        $this->assertSame(['students.csv'], $data->files);

        provider::delete_data_for_user(new approved_contextlist($ana, condition::COMPONENT, [$context->id]));
        $this->assertSame("username\nbia,B\n", condition::get_file($config, $context)->get_content());

        provider::delete_data_for_users(new approved_userlist($context, condition::COMPONENT, [$bia->id]));
        $this->assertSame("username\n", condition::get_file($config, $context)->get_content());

        provider::delete_data_for_all_users_in_context($context);
        $this->assertNull(condition::get_file($config, $context));
    }
}
