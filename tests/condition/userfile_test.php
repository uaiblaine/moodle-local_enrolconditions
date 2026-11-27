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

        $context = \context_course::instance($course->id);
        $unusedhash = condition::get_file($unused, $context)->get_contenthash();
        // Another test may have read the same content in this process: start from an empty memory.
        condition::forget_usernames($unusedhash);
        condition::get_usernames(condition::get_file($unused, $context));
        $this->assertNotFalse(\cache::make(condition::COMPONENT, 'usernames')->get($unusedhash));

        $this->expectOutputRegex('/1 files of usernames deleted/');
        (new \enrolcond_userfile\task\cleanup_files())->execute();
        $this->assertNotNull(condition::get_file($used, $context));
        $this->assertNull(condition::get_file($unused, $context));
        $this->assertNotNull(condition::get_file($recent, $context));
        // The list of the deleted file is gone from the shared cache too.
        $this->assertFalse(\cache::make(condition::COMPONENT, 'usernames')->get($unusedhash));
    }

    public function test_cleanup_reads_the_references_again_before_deleting(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $first = $this->save($course, "ana\n");
        $second = $this->save($course, "bia\n");
        $DB->set_field('files', 'timemodified', time() - 2 * DAYSECS, ['component' => condition::COMPONENT]);
        // A teacher saves a tree that uses the second file after the task read the references of the course.
        $task = new class (fn() => $this->store($course, $second)) extends \enrolcond_userfile\task\cleanup_files {
            /** @var int Calls of read_references(). */
            public int $reads = 0;

            /**
             * Constructor.
             *
             * @param \Closure $save Stores the tree, as the teacher does.
             */
            public function __construct(
                /** @var \Closure */
                protected \Closure $save,
            ) {
            }

            /**
             * Reads the references, and stores the teacher's tree right after the first read.
             *
             * @param int $courseid
             * @return bool[]
             */
            protected function read_references(int $courseid): array {
                $ids = parent::read_references($courseid);
                if (++$this->reads === 1) {
                    ($this->save)();
                }
                return $ids;
            }
        };

        $this->expectOutputRegex('/1 files of usernames deleted/');
        $task->execute();
        $context = \context_course::instance($course->id);
        $this->assertNotNull(condition::get_file($second, $context));
        // Control: the file no tree uses was deleted.
        $this->assertNull(condition::get_file($first, $context));
    }

    public function test_cleanup_keeps_a_file_only_a_disabled_instance_uses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $used = $this->save($course, "ana\n");
        $unused = $this->save($course, "bia\n");
        $this->store($course, $used);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $course->id, 'enrol' => 'manual']);
        $DB->set_field('files', 'timemodified', time() - 2 * DAYSECS, ['component' => condition::COMPONENT]);

        $this->expectOutputRegex('/1 files of usernames deleted/');
        (new \enrolcond_userfile\task\cleanup_files())->execute();
        $context = \context_course::instance($course->id);
        $this->assertNotNull(condition::get_file($used, $context));
        // Control: the file no instance uses goes.
        $this->assertNull(condition::get_file($unused, $context));
    }

    /**
     * Makes every file of usernames old enough for the cleanup task.
     */
    protected function age_files(): void {
        global $DB;
        $DB->set_field('files', 'timemodified', time() - 2 * DAYSECS, ['component' => condition::COMPONENT]);
    }

    /**
     * Runs the cleanup task and returns what it printed.
     *
     * @param \enrolcond_userfile\task\cleanup_files|null $task A subclass to run instead of the task.
     * @return string
     */
    protected function run_cleanup(?\enrolcond_userfile\task\cleanup_files $task = null): string {
        ob_start();
        ($task ?? new \enrolcond_userfile\task\cleanup_files())->execute();
        return ob_get_clean();
    }

    /**
     * JSON of a valid tree with one userfile condition.
     *
     * @param int $fileid
     * @return string
     */
    protected function tree_naming(int $fileid): string {
        return json_encode(['op' => 'and', 'children' => [
            ['type' => 'userfile', 'config' => ['fileid' => $fileid]],
        ]]);
    }

    /**
     * Adds an enrolment instance of a method no plugin provides.
     *
     * @param \stdClass $course
     * @param array $columns Custom columns to fill.
     */
    protected function add_missing_method_instance(\stdClass $course, array $columns): void {
        global $DB;
        $this->assertNull(enrol_get_plugin('zzmissing'));
        $DB->insert_record('enrol', (object) ($columns + [
            'enrol' => 'zzmissing',
            'status' => ENROL_INSTANCE_ENABLED,
            'courseid' => $course->id,
            'sortorder' => 99,
            'timecreated' => time(),
            'timemodified' => time(),
        ]));
    }

    public function test_cleanup_keeps_a_file_a_tree_that_does_not_validate_names(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $named = $this->save($course, "ana\n");
        $unnamed = $this->save($course, "bia\n");
        // An unknown operator: the value is JSON and names the file, but tree::from_json() refuses it.
        $DB->set_field('enrol', 'customtext1', json_encode(['op' => 'xor', 'children' => [
            ['type' => 'userfile', 'config' => ['fileid' => $named['fileid']]],
        ]]), ['courseid' => $course->id, 'enrol' => 'manual']);
        $this->assertSame([], \local_enrolconditions\condition\manager::get_course_trees((int) $course->id));
        $this->age_files();

        $this->assertMatchesRegularExpression('/^1 files of usernames deleted\./', $this->run_cleanup());
        $context = \context_course::instance($course->id);
        $this->assertNotNull(condition::get_file($named, $context));
        // Control: the old file nothing names goes.
        $this->assertNull(condition::get_file($unnamed, $context));
    }

    public function test_cleanup_keeps_a_file_an_instance_of_a_missing_method_names(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $named = $this->save($course, "ana\n");
        $unnamed = $this->save($course, "bia\n");
        $this->add_missing_method_instance($course, ['customtext2' => $this->tree_naming($named['fileid'])]);
        $this->age_files();

        $this->assertMatchesRegularExpression('/^1 files of usernames deleted\./', $this->run_cleanup());
        $context = \context_course::instance($course->id);
        $this->assertNotNull(condition::get_file($named, $context));
        // Control: the old file nothing names goes.
        $this->assertNull(condition::get_file($unnamed, $context));
    }

    public function test_cleanup_ignores_the_columns_of_an_installed_method_without_trees(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertNull(\local_enrolconditions\condition\manager::get_enforceable('manual'));
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $file = $this->save($course, "ana\n");
        $control = $this->save($other, "bia\n");
        // The manual method is installed and stores no trees: its columns protect nothing.
        $DB->set_field(
            'enrol',
            'customtext1',
            $this->tree_naming($file['fileid']),
            ['courseid' => $course->id, 'enrol' => 'manual']
        );
        // Control: the same value in a column of a method that is not installed keeps the file.
        $this->add_missing_method_instance($other, ['customtext1' => $this->tree_naming($control['fileid'])]);
        $this->age_files();

        $this->assertMatchesRegularExpression('/^1 files of usernames deleted\./', $this->run_cleanup());
        $this->assertNull(condition::get_file($file, \context_course::instance($course->id)));
        $this->assertNotNull(condition::get_file($control, \context_course::instance($other->id)));
    }

    public function test_cleanup_deletes_nothing_of_a_course_whose_stored_value_cannot_be_read(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $named = $this->save($course, "ana\n");
        $unnamed = $this->save($course, "bia\n");
        $control = $this->save($other, "carla\n");
        // A truncated tree: it names a file and is not JSON.
        $truncated = substr($this->tree_naming($named['fileid']), 0, -8);
        $this->assertStringContainsString('"fileid"', $truncated);
        $this->assertNull(json_decode($truncated, true));
        $DB->set_field('enrol', 'customtext1', $truncated, ['courseid' => $course->id, 'enrol' => 'manual']);
        $this->age_files();

        $output = $this->run_cleanup();
        $this->assertStringContainsString('1 files of usernames deleted.', $output);
        $this->assertStringContainsString('Files of 1 courses kept: a stored condition tree could not be read.', $output);
        $context = \context_course::instance($course->id);
        $this->assertNotNull(condition::get_file($named, $context));
        $this->assertNotNull(condition::get_file($unnamed, $context));
        // Control: the old file of another course, which nothing names, goes in the same run.
        $this->assertNull(condition::get_file($control, \context_course::instance($other->id)));

        // The teacher fixes the value: the next run deletes what nothing names.
        $this->store($course, $named);
        $output = $this->run_cleanup();
        $this->assertStringContainsString('1 files of usernames deleted.', $output);
        $this->assertStringNotContainsString('kept', $output);
        $this->assertNotNull(condition::get_file($named, $context));
        $this->assertNull(condition::get_file($unnamed, $context));
    }

    public function test_cleanup_deletes_nothing_of_a_course_whose_value_names_a_file_only_escaped(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $named = $this->save($course, "ana\n");
        $control = $this->save($other, "carla\n");
        // A tree encoded twice: it decodes to a string, and its key is spelt \"fileid\", never "fileid".
        $encoded = json_encode($this->tree_naming($named['fileid']));
        $this->assertIsString(json_decode($encoded, true));
        $this->assertStringNotContainsString('"fileid"', $encoded);
        $DB->set_field('enrol', 'customtext1', $encoded, ['courseid' => $course->id, 'enrol' => 'manual']);
        $this->age_files();

        $output = $this->run_cleanup();
        $this->assertStringContainsString('Files of 1 courses kept: a stored condition tree could not be read.', $output);
        $this->assertNotNull(condition::get_file($named, \context_course::instance($course->id)));
        // Control: the old file of another course, which nothing names, goes in the same run.
        $this->assertNull(condition::get_file($control, \context_course::instance($other->id)));
    }

    public function test_cleanup_deletes_nothing_of_a_course_whose_method_cannot_be_asked(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $unnamed = $this->save($course, "ana\n");
        $control = $this->save($other, "bia\n");
        $DB->set_field('enrol', 'customchar1', 'throwtree', ['courseid' => $course->id, 'enrol' => 'manual']);
        try {
            \local_enrolconditions\condition\manager::get_course_stored_values((int) $course->id);
            $this->fail('The method of the instance should have thrown.');
        } catch (\TypeError $e) {
            $this->assertSame('Broken enrolment method', $e->getMessage());
        }
        $this->age_files();

        $output = $this->run_cleanup();
        $this->assertStringContainsString('1 files of usernames deleted.', $output);
        $this->assertStringContainsString('Files of 1 courses kept: a stored condition tree could not be read.', $output);
        $this->assertNotNull(condition::get_file($unnamed, \context_course::instance($course->id)));
        // Control: the old file of another course, which nothing names, goes in the same run.
        $this->assertNull(condition::get_file($control, \context_course::instance($other->id)));

        // The method answers again: the file nothing names goes.
        $DB->set_field('enrol', 'customchar1', '', ['courseid' => $course->id, 'enrol' => 'manual']);
        $this->assertStringContainsString('1 files of usernames deleted.', $this->run_cleanup());
        $this->assertNull(condition::get_file($unnamed, \context_course::instance($course->id)));
    }

    public function test_cleanup_keeps_a_file_that_becomes_unreadable_before_its_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $file = $this->save($course, "ana\n");
        $control = $this->save($other, "bia\n");
        $this->age_files();
        // The stored value of the first course is cut after the task read it, before the deletion reads it again.
        $break = fn() => $DB->set_field(
            'enrol',
            'customtext1',
            '{"op":"and","children":[{"type":"userfile","config":{"fileid":',
            ['courseid' => $course->id, 'enrol' => 'manual']
        );
        $task = new class ((int) $course->id, $break) extends \enrolcond_userfile\task\cleanup_files {
            /** @var array The answers given for the watched course. */
            public array $answers = [];

            /**
             * Constructor.
             *
             * @param int $watched Course whose stored value is cut after its first read.
             * @param \Closure $break Cuts the stored value.
             */
            public function __construct(
                /** @var int */
                protected int $watched,
                /** @var \Closure */
                protected \Closure $break,
            ) {
            }

            /**
             * Reads the references, and cuts the watched course's stored value right after the first read.
             *
             * @param int $courseid
             * @return bool[]|null
             */
            protected function read_references(int $courseid): ?array {
                $ids = parent::read_references($courseid);
                if ($courseid === $this->watched) {
                    $this->answers[] = $ids;
                    if (count($this->answers) === 1) {
                        ($this->break)();
                    }
                }
                return $ids;
            }
        };

        $output = $this->run_cleanup($task);
        // The first read answered, the second could not.
        $this->assertSame([[], null], $task->answers);
        $this->assertStringContainsString('1 files of usernames deleted.', $output);
        $this->assertStringContainsString('Files of 1 courses kept', $output);
        $this->assertNotNull(condition::get_file($file, \context_course::instance($course->id)));
        // Control: the old file of another course, which nothing names, goes.
        $this->assertNull(condition::get_file($control, \context_course::instance($other->id)));
    }

    public function test_cleanup_reads_a_file_id_as_the_condition_does(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $unnamed = $this->save($course, "ana\n");
        $viastring = $this->save($course, "bia\n");
        // A file of item 1, which the condition reads for an id that is an array: (int) of a non-empty array is 1.
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => condition::COMPONENT,
            'filearea' => condition::FILEAREA,
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'students.csv',
            'userid' => (int) $USER->id,
        ], "carla\n");
        $viaarray = ['fileid' => [7], 'filename' => 'students.csv', 'count' => 1];
        $this->assertNotNull(condition::get_file($viaarray, $context));
        $this->assertSame(1, (int) condition::get_file($viaarray, $context)->get_itemid());
        // The tree layer checks only the structure of a node, so the value is a tree the engine evaluates.
        $DB->set_field('enrol', 'customtext1', json_encode(['op' => 'and', 'children' => [
            ['type' => 'userfile', 'config' => $viaarray],
            ['type' => 'userfile', 'config' => ['fileid' => (string) $viastring['fileid']]],
        ]]), ['courseid' => $course->id, 'enrol' => 'manual']);
        $this->age_files();

        $this->assertMatchesRegularExpression('/^1 files of usernames deleted\./', $this->run_cleanup());
        $this->assertNotNull(condition::get_file($viaarray, $context));
        $this->assertNotNull(condition::get_file($viastring, $context));
        // Control: the old file nothing names goes.
        $this->assertNull(condition::get_file($unnamed, $context));
    }

    public function test_check_access_ignores_a_stored_value_that_does_not_validate(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $condition = new condition();
        $named = $this->save($course, "ana\n");
        $fresh = $this->save($course, "bia\n");
        $DB->set_field('enrol', 'customtext1', json_encode(['op' => 'xor', 'children' => [
            ['type' => 'userfile', 'config' => ['fileid' => $named['fileid']]],
        ]]), ['courseid' => $course->id, 'enrol' => 'manual']);

        // The rule of check_access() is the trees in force: a value that does not validate is not one.
        $this->assertSame([], \enrolcond_userfile\task\cleanup_files::get_referenced_fileids((int) $course->id));
        $this->assertNull($condition->check_access($fresh, $context));
        // Control: a valid tree naming the file makes it refused.
        $this->store($course, $named);
        $this->assertSame(get_string('noaccess', condition::COMPONENT), $condition->check_access($named, $context));
    }

    public function test_check_access_refuses_a_file_a_disabled_instance_uses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $condition = new condition();
        $stored = $this->save($course, "ana\n");
        $fresh = $this->save($course, "bia\n");
        $this->store($course, $stored);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $course->id, 'enrol' => 'manual']);

        // A disabled instance keeps its tree, which is in force again once it is enabled.
        $this->assertSame(get_string('noaccess', condition::COMPONENT), $condition->check_access($stored, $context));
        // Control: the file the dialog has just stored, which no tree uses yet.
        $this->assertNull($condition->check_access($fresh, $context));
    }

    public function test_check_access_refuses_a_file_another_teacher_uploaded(): void {
        $this->resetAfterTest();
        $this->use_fake_enforceable();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $uploader = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $condition = new condition();

        // The dialog stored the file for its uploader; no tree uses it yet.
        $this->setUser($uploader);
        $config = $this->save($course, "ana\n");
        $this->assertSame((int) $uploader->id, (int) condition::get_file($config, $context)->get_userid());

        $this->setUser($other);
        $this->assertSame(get_string('noaccess', condition::COMPONENT), $condition->check_access($config, $context));
        // Control: its uploader may reference it.
        $this->setUser($uploader);
        $this->assertNull($condition->check_access($config, $context));
    }

    public function test_check_access_refuses_a_context_that_is_not_a_course(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        // A file of this component in a category context, uploaded by this user and used by no tree.
        get_file_storage()->create_file_from_string([
            'contextid' => $catcontext->id,
            'component' => condition::COMPONENT,
            'filearea' => condition::FILEAREA,
            'itemid' => 55,
            'filepath' => '/',
            'filename' => 'list.csv',
            'userid' => $USER->id,
        ], "ana\n");
        $config = ['fileid' => 55, 'filename' => 'list.csv', 'count' => 1];
        $condition = new condition();

        $this->assertSame(get_string('noaccess', condition::COMPONENT), $condition->check_access($config, $catcontext));
        // Control: the same kind of file in a course is accepted.
        $course = $this->getDataGenerator()->create_course();
        $this->assertNull($condition->check_access($this->save($course, "ana\n"), \context_course::instance($course->id)));
    }

    public function test_privacy_delete_of_a_context_forgets_the_cached_lists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $config = $this->save($course, "ana\nbia\n");
        $hash = condition::get_file($config, $context)->get_contenthash();
        condition::forget_usernames($hash);
        condition::get_usernames(condition::get_file($config, $context));
        $cache = \cache::make(condition::COMPONENT, 'usernames');
        $this->assertNotFalse($cache->get($hash));

        provider::delete_data_for_all_users_in_context($context);
        $this->assertNull(condition::get_file($config, $context));
        $this->assertFalse($cache->get($hash));
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
