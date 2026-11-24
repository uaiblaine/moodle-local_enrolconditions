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
use local_enrolconditions\form\condition_config;
use local_enrolconditions\output\tree_editor;
use local_enrolconditions\plugininfo\enrolcond;

/**
 * Refusals of the condition editor: who may open the dialog, and which names a teacher can get from it.
 *
 * A teacher who may configure an enrolment method must not learn the name of a course they cannot see (or of a
 * cohort they cannot use) by referencing it in a condition: not from the dialog, not from the editor re-rendered
 * after a failed validation. A node already stored in the instance stays readable and editable.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(editor_access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(condition_config::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(tree_editor::class)]
final class editor_access_test extends \advanced_testcase {
    /** @var \stdClass Course whose enrolment methods are edited. */
    protected \stdClass $course;

    /** @var \stdClass Hidden course the teacher cannot see. */
    protected \stdClass $hidden;

    /** @var \stdClass Visible course. */
    protected \stdClass $visible;

    /** @var \stdClass Editing teacher of $course. */
    protected \stdClass $teacher;

    /** @var \stdClass Manual enrolment instance of $course, which stores a tree in customtext1. */
    protected \stdClass $instance;

    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once(__DIR__ . '/fixtures/fake_enforceable.php');
        require_once(__DIR__ . '/fixtures/scripted_condition.php');
        require_once(__DIR__ . '/fixtures/unvalidated_condition_config.php');
        parent::setUpBeforeClass();
    }

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        manager::reset_caches();
        manager::register_enforceable_for_testing('manual', new \local_enrolconditions_fake_enforceable());
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->hidden = $generator->create_course(['fullname' => 'Secret course', 'visible' => 0]);
        $this->visible = $generator->create_course(['fullname' => 'Open course']);
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        // The fake method asks enrol/manual:config, which editing teachers lack by default.
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('enrol/manual:config', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Stores a tree in the manual instance.
     *
     * @param array $children Condition nodes of the root group.
     */
    protected function store(array $children): void {
        global $DB;
        $json = json_encode(['op' => 'and', 'negate' => false, 'children' => $children]);
        $DB->set_field('enrol', 'customtext1', $json, ['id' => $this->instance->id]);
        $this->instance->customtext1 = $json;
    }

    /**
     * Submits the dialog as the editor would.
     *
     * @param array $fields Fields of the form, besides the ones the editor sends.
     * @param array $params Parameters the editor sends, overriding the defaults.
     * @return condition_config
     */
    protected function submit(array $fields, array $params = []): condition_config {
        $formdata = condition_config::mock_ajax_submit($params + [
            'enrolcondcontextid' => \context_course::instance($this->course->id)->id,
            'enrolcondtype' => 'coursecompleted',
            'enrolcondconfig' => '',
            'enrolcondenrol' => 'manual',
            'enrolcondinstanceid' => $this->instance->id,
        ] + $fields);
        $form = new condition_config(null, null, 'post', '', [], true, $formdata, true);
        $form->set_data_for_dynamic_submission();
        return $form;
    }

    public function test_dialog_does_not_name_a_hidden_course(): void {
        $this->setUser($this->teacher);

        $form = $this->submit(['courseid' => $this->hidden->id]);
        $this->assertFalse($form->is_validated());

        // Control: a course the teacher can see is accepted and named.
        $form = $this->submit(['courseid' => $this->visible->id]);
        $this->assertTrue($form->is_validated());
        $result = $form->process_dynamic_submission();
        $this->assertSame(['courseid' => (int) $this->visible->id], json_decode($result['config'], true));
        $this->assertStringContainsString('Open course', $result['summary']);
    }

    public function test_dialog_accepts_a_stored_node_unchanged(): void {
        $this->store([['type' => 'coursecompleted', 'negate' => false, 'config' => ['courseid' => (int) $this->hidden->id]]]);
        $this->setUser($this->teacher);

        // Stored in this instance: the teacher may keep it.
        $form = $this->submit(['courseid' => $this->hidden->id]);
        $this->assertTrue($form->is_validated());
        $this->assertStringContainsString('Secret course', $form->process_dynamic_submission()['summary']);

        // Control: the same node is refused for a new instance, where nothing is stored.
        $form = $this->submit(['courseid' => $this->hidden->id], ['enrolcondinstanceid' => 0]);
        $this->assertFalse($form->is_validated());
    }

    /**
     * Stores a user file list in the course and returns its configuration.
     *
     * @param int $itemid File id.
     * @param int $userid Uploader.
     * @return array
     */
    protected function userfile_config(int $itemid, int $userid): array {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($this->course->id)->id,
            'component' => 'enrolcond_userfile',
            'filearea' => 'usernames',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => 'list' . $itemid . '.csv',
            'userid' => $userid,
        ], "ana\n");
        return ['fileid' => $itemid, 'filename' => 'list' . $itemid . '.csv', 'count' => 1];
    }

    /**
     * Files the dialog of the manual instance pre-fills for the current user when it opens with a configuration.
     *
     * @param array $config Posted configuration.
     * @return int
     */
    protected function prefilled_files(array $config): int {
        global $USER;
        $form = new condition_config(null, null, 'post', '', [], true, [
            'enrolcondcontextid' => \context_course::instance($this->course->id)->id,
            'enrolcondtype' => 'userfile',
            'enrolcondconfig' => json_encode($config),
            'enrolcondenrol' => 'manual',
            'enrolcondinstanceid' => $this->instance->id,
        ], true);
        $form->set_data_for_dynamic_submission();
        $property = new \ReflectionProperty(\moodleform::class, '_form');
        $draftitemid = (int) $property->getValue($form)->getElement('usernamesfile')->getValue();
        return count(get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false
        ));
    }

    public function test_dialog_does_not_prefill_another_instance_file(): void {
        global $DB;
        $this->setUser($this->teacher);
        // The same teacher uploaded both files, so only the reference rule can tell them apart.
        $referenced = $this->userfile_config(77, (int) $this->teacher->id);
        $own = $this->userfile_config(78, (int) $this->teacher->id);
        // The first file belongs to the condition of another method of the same course.
        manager::register_enforceable_for_testing('self', new \local_enrolconditions_fake_enforceable());
        $self = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $DB->set_field('enrol', 'customtext1', json_encode(['op' => 'and', 'children' => [
            ['type' => 'userfile', 'config' => $referenced],
        ]]), ['id' => $self->id]);

        // The file is referenced by the other instance only: the dialog of this one starts empty.
        $this->assertSame(0, $this->prefilled_files($referenced));
        // Control: the same teacher's file that no tree uses is shown.
        $this->assertSame(1, $this->prefilled_files($own));

        // Control: once this instance stores the node, the dialog shows its file.
        $this->store([['type' => 'userfile', 'negate' => false, 'config' => $referenced]]);
        $this->assertSame(1, $this->prefilled_files($referenced));
    }

    public function test_dialog_does_not_prefill_a_file_another_teacher_uploaded(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        // No tree uses either file, so only the uploader rule can tell them apart.
        $this->setUser($other);
        $theirs = $this->userfile_config(79, (int) $other->id);
        $this->setUser($this->teacher);
        $this->assertSame(0, $this->prefilled_files($theirs));
        // Control: its uploader sees it.
        $this->setUser($other);
        $this->assertSame(1, $this->prefilled_files($theirs));
    }

    public function test_dialog_requires_the_method_config_capability(): void {
        global $DB;
        $this->setUser($this->teacher);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $fields = ['courseid' => $this->visible->id];

        // Control: with enrol/manual:config the dialog opens.
        $this->assertTrue($this->submit($fields)->is_validated());

        // Prohibit the config capability of every method that stores trees (enrol_conditional may be installed).
        foreach (array_keys(manager::get_enforceable_plugins()) as $name) {
            if (get_capability_info('enrol/' . $name . ':config')) {
                assign_capability('enrol/' . $name . ':config', CAP_PROHIBIT, $roleid, \context_system::instance()->id, true);
            }
        }
        foreach (
            [
                ['enrolcondenrol' => 'manual'],
                // Leaving the method out asks the capability of some method that stores trees, never less.
                ['enrolcondenrol' => '', 'enrolcondinstanceid' => 0],
            ] as $params
        ) {
            try {
                $this->submit($fields, $params);
                $this->fail('required_capability_exception expected');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
    }

    public function test_dialog_refuses_an_instance_of_another_course_or_method(): void {
        global $DB;
        $this->setUser($this->teacher);
        $fields = ['courseid' => $this->visible->id];
        $otherinstance = $DB->get_record('enrol', ['courseid' => $this->visible->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $selfinstance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'self'], '*', MUST_EXIST);

        foreach (
            [
                ['enrolcondinstanceid' => $otherinstance->id],
                ['enrolcondinstanceid' => $selfinstance->id, 'enrolcondenrol' => ''],
                ['enrolcondinstanceid' => 0, 'enrolcondenrol' => 'self'],
                ['enrolcondinstanceid' => $this->instance->id, 'enrolcondenrol' => 'self'],
            ] as $params
        ) {
            try {
                $this->submit($fields, $params);
                $this->fail('invalidenrolinstance expected');
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidenrolinstance', $e->errorcode);
            }
        }
        // Control: the instance of this course.
        $this->assertTrue($this->submit($fields)->is_validated());
    }

    public function test_dialog_refuses_a_disabled_type(): void {
        $this->setUser($this->teacher);
        $fields = ['courseid' => $this->visible->id];
        enrolcond::enable_plugin('coursecompleted', 0);
        try {
            $this->submit($fields);
            $this->fail('treeunknowntype expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('treeunknowntype', $e->errorcode);
        }
        // Control: enabled again.
        enrolcond::enable_plugin('coursecompleted', 1);
        $this->assertTrue($this->submit($fields)->is_validated());
    }

    /**
     * Summary of the first condition of the editor rendered by add_to_form().
     *
     * @param string|null $saved Tree stored in the instance.
     * @param string|null $submitted Tree posted with the instance form, as after a failed validation.
     * @return string
     */
    protected function rendered_summary(?string $saved, ?string $submitted): string {
        global $PAGE;
        $PAGE->set_url('/enrol/instances.php', ['id' => $this->course->id]);
        $_POST = $submitted === null ? [] : ['customtext1' => $submitted, 'sesskey' => sesskey()];
        $mform = new \MoodleQuickForm('test', 'post', '');
        tree_editor::add_to_form(
            $mform,
            'customtext1',
            'Conditions',
            \context_course::instance($this->course->id),
            $saved,
            'manual',
            (int) $this->instance->id
        );
        $_POST = [];
        $html = $mform->getElement('customtext1_editor')->toHtml();
        $this->assertSame(1, preg_match('/data-config="([^"]*)"/', $html, $matches));
        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertSame('manual', $config['enrol']);
        $this->assertSame((int) $this->instance->id, $config['instanceid']);
        return $config['tree']['children'][0]['summary'];
    }

    public function test_editor_rerender_does_not_name_a_hidden_course(): void {
        $this->setUser($this->teacher);
        $tree = fn(int $courseid): string => json_encode(['op' => 'and', 'children' => [
            ['type' => 'coursecompleted', 'config' => ['courseid' => $courseid]],
        ]]);

        $summary = $this->rendered_summary(null, $tree((int) $this->hidden->id));
        $this->assertStringNotContainsString('Secret course', $summary);
        $this->assertSame(get_string('editornotavailable', 'local_enrolconditions'), $summary);

        // Controls: a visible course is named, and a node already stored stays named.
        $this->assertStringContainsString('Open course', $this->rendered_summary(null, $tree((int) $this->visible->id)));
        $saved = $tree((int) $this->hidden->id);
        $this->assertStringContainsString('Secret course', $this->rendered_summary($saved, $saved));
        $this->assertStringContainsString('Secret course', $this->rendered_summary($saved, null));
    }

    public function test_node_keys_ignore_key_order_and_negation(): void {
        $stored = editor_access::get_node_keys(['op' => 'or', 'negate' => false, 'children' => [
            ['type' => 'courseenrolled', 'negate' => true, 'config' => ['mode' => 'any', 'courseids' => [3, 4]]],
            ['op' => 'and', 'negate' => false, 'children' => [
                ['type' => 'cohort', 'negate' => false, 'config' => ['cohortid' => 9]],
            ]],
        ]]);
        $this->assertCount(2, $stored);
        $this->assertArrayHasKey(editor_access::node_key('courseenrolled', ['courseids' => [3, 4], 'mode' => 'any']), $stored);
        $this->assertArrayHasKey(editor_access::node_key('cohort', ['cohortid' => 9]), $stored);
        // Control: the order of a list is part of the configuration.
        $this->assertArrayNotHasKey(editor_access::node_key('courseenrolled', ['courseids' => [4, 3], 'mode' => 'any']), $stored);
    }

    /**
     * Ids of new visible courses.
     *
     * @param int $count
     * @return int[]
     */
    protected function visible_courses(int $count): array {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = (int) $this->getDataGenerator()->create_course(['fullname' => 'Listed course ' . $i])->id;
        }
        return $ids;
    }

    public function test_editor_summary_checks_every_course_it_names(): void {
        $this->setUser($this->teacher);
        $context = \context_course::instance($this->course->id);
        $tree = fn(array $courseids): string => json_encode(['op' => 'and', 'children' => [
            ['type' => 'courseenrolled', 'config' => ['courseids' => $courseids, 'mode' => 'any', 'active' => 1]],
        ]]);
        $visible = $this->visible_courses(51);
        $long = array_merge($visible, [(int) $this->hidden->id]);

        // 52 courses, the hidden one last: beyond what validation allows, so nothing is named.
        $summary = $this->rendered_summary(null, $tree($long));
        $this->assertStringNotContainsString('Secret course', $summary);
        $this->assertSame(get_string('editornotavailable', 'local_enrolconditions'), $summary);
        $config = ['courseids' => $long, 'mode' => 'any', 'active' => 1];
        $this->assertNotNull(editor_access::check_node('courseenrolled', $config, $context, []));
        // A list over the bound is refused outright, every course visible or not.
        $config = ['courseids' => $visible, 'mode' => 'any', 'active' => 1];
        $this->assertSame(
            get_string('toomany', 'enrolcond_courseenrolled', 50),
            editor_access::check_node('courseenrolled', $config, $context, [])
        );

        // Control: 50 visible courses are named.
        $fifty = array_slice($visible, 0, 50);
        $this->assertStringContainsString('Listed course 49', $this->rendered_summary(null, $tree($fifty)));
        $config = ['courseids' => $fifty, 'mode' => 'any', 'active' => 1];
        $this->assertNull(editor_access::check_node('courseenrolled', $config, $context, []));
    }

    public function test_editor_summary_cost_is_bounded(): void {
        global $DB;
        $this->setUser($this->teacher);
        $courseids = $this->visible_courses(50);
        // Twenty nodes naming the same fifty courses: each course's reach is decided once for the request.
        $children = [];
        for ($i = 0; $i < 20; $i++) {
            $config = ['courseids' => $courseids, 'mode' => 'any', 'active' => $i % 2];
            $children[] = ['type' => 'courseenrolled', 'config' => $config];
        }
        $submitted = json_encode(['op' => 'or', 'children' => $children]);
        \local_enrolconditions\condition\base::reset_existence_cache();

        $reads = $DB->perf_get_reads();
        $this->assertStringContainsString('Listed course 49', $this->rendered_summary(null, $submitted));
        $this->assertLessThan(400, $DB->perf_get_reads() - $reads);
    }

    public function test_editor_summary_of_a_stored_list_with_a_hidden_course_added(): void {
        $this->setUser($this->teacher);
        $tree = fn(array $courseids): string => json_encode(['op' => 'and', 'children' => [
            ['type' => 'courseenrolled', 'config' => ['courseids' => $courseids, 'mode' => 'any', 'active' => 1]],
        ]]);
        $saved = $tree([(int) $this->visible->id]);
        $submitted = $tree([(int) $this->visible->id, (int) $this->hidden->id]);

        // The key changes with the list, so every course of it is checked again.
        $summary = $this->rendered_summary($saved, $submitted);
        $this->assertStringNotContainsString('Secret course', $summary);
        $this->assertStringNotContainsString('Open course', $summary);
        // Control: the stored list itself is named.
        $this->assertStringContainsString('Open course', $this->rendered_summary($saved, $saved));
    }

    public function test_editor_summary_needs_a_valid_configuration(): void {
        manager::register_for_testing('scripted', new fixtures\scripted_condition());
        $this->setUser($this->teacher);
        $tree = fn(array $config): string => json_encode(['op' => 'and', 'children' => [
            ['type' => 'scripted', 'config' => $config],
        ]]);

        $summary = $this->rendered_summary(null, $tree(['invalid' => 'Broken']));
        $this->assertSame(get_string('editornotavailable', 'local_enrolconditions'), $summary);
        // Control: the same condition with a valid configuration is summarised.
        $this->assertSame('Scripted not met', $this->rendered_summary(null, $tree([])));
    }

    public function test_a_missing_record_and_one_out_of_reach_get_the_same_answer(): void {
        global $DB;
        $this->setUser($this->teacher);
        $context = \context_course::instance($this->course->id);
        $missingcourse = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course}') + 100;
        $invisible = $this->getDataGenerator()->create_cohort(['visible' => 0]);
        $missingcohort = (int) $DB->get_field_sql('SELECT MAX(id) FROM {cohort}') + 100;
        $answer = fn(string $type, array $config): array => tree::from_array(['op' => 'and', 'children' => [
            ['type' => $type, 'config' => $config],
        ]])->validate([], $context);
        $visible = (int) $this->visible->id;
        $hidden = (int) $this->hidden->id;
        $pairs = [
            ['coursecompleted', ['courseid' => $missingcourse], ['courseid' => $hidden]],
            ['courseenrolled', ['courseids' => [$visible, $missingcourse]], ['courseids' => [$visible, $hidden]]],
            ['cohort', ['cohortid' => $missingcohort], ['cohortid' => (int) $invisible->id]],
        ];
        foreach ($pairs as [$type, $missing, $unreachable]) {
            $errors = $answer($type, $missing);
            $this->assertCount(1, $errors, $type);
            $this->assertSame($answer($type, $unreachable), $errors, $type);
            $this->assertStringContainsString(get_string('noaccess', 'enrolcond_' . $type), $errors[0], $type);
        }

        // The dialog answers alike too.
        $form = $this->submit(['courseid' => $visible]);
        $fields = ['enrolcondtype' => 'coursecompleted'];
        $errors = $form->validation($fields + ['courseid' => $missingcourse], []);
        $this->assertSame(['enrolcondaccess' => get_string('noaccess', 'enrolcond_coursecompleted')], $errors);
        $this->assertSame($errors, $form->validation($fields + ['courseid' => $hidden], []));
        // Control: a course the teacher can see validates, on both doors.
        $this->assertSame([], $form->validation($fields + ['courseid' => $visible], []));
        $this->assertSame([], $answer('coursecompleted', ['courseid' => $visible]));

        // A node stored unchanged keeps the precise answer for the teacher who has to fix it.
        $stored = tree::from_array(['op' => 'and', 'children' => [
            ['type' => 'coursecompleted', 'config' => ['courseid' => $missingcourse]],
        ]]);
        $errors = $stored->validate([], $context, $stored);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString(get_string('missingcourse', 'enrolcond_coursecompleted'), $errors[0]);
    }

    public function test_an_unencodable_configuration_is_never_a_stored_node(): void {
        manager::register_for_testing('scripted', new fixtures\scripted_condition());
        $this->setUser($this->teacher);
        $context = \context_course::instance($this->course->id);
        // Two configurations json_encode() cannot write: without the error flag both hash like an empty one.
        $stored = editor_access::get_node_keys(['op' => 'and', 'negate' => false, 'children' => [
            ['type' => 'scripted', 'negate' => false, 'config' => ['x' => "\xB1"]],
        ]]);
        $this->assertSame(
            get_string('editornotavailable', 'local_enrolconditions'),
            editor_access::check_node('scripted', ['y' => "\xC3\x28"], $context, $stored)
        );
        $this->assertSame([], $stored);
        // Control: an encodable configuration the condition allows passes.
        $this->assertNull(editor_access::check_node('scripted', ['met' => true], $context, []));
    }

    public function test_dialog_refuses_a_context_that_is_not_a_course(): void {
        $this->setAdminUser();
        $category = \context_coursecat::instance((int) $this->course->category);
        foreach ([\context_system::instance(), $category] as $context) {
            try {
                editor_access::require_editor_access($context, 'manual', 0);
                $this->fail('invalidcontext expected');
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidcontext', $e->errorcode);
            }
        }
        // Control: the course context of the instance.
        $this->assertNull(editor_access::require_editor_access(\context_course::instance($this->course->id), 'manual', 0));
    }

    public function test_dialog_asks_enrolconfig_on_its_own(): void {
        global $DB;
        $context = \context_course::instance($this->course->id);
        $this->setUser($this->teacher);
        // Control: with both capabilities the instance is returned.
        $instance = editor_access::require_editor_access($context, 'manual', (int) $this->instance->id);
        $this->assertSame((int) $this->instance->id, (int) $instance->id);

        // The method's config capability alone is not enough.
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/course:enrolconfig', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->assertTrue(has_capability('enrol/manual:config', $context));
        try {
            editor_access::require_editor_access($context, 'manual', (int) $this->instance->id);
            $this->fail('required_capability_exception expected');
        } catch (\required_capability_exception $e) {
            $this->assertSame(get_capability_string('moodle/course:enrolconfig'), $e->a);
        }
    }

    public function test_submission_checks_access_on_its_own(): void {
        $this->setUser($this->teacher);
        $open = fn(int $courseid): condition_config => new fixtures\unvalidated_condition_config(
            null,
            null,
            'post',
            '',
            [],
            true,
            fixtures\unvalidated_condition_config::mock_ajax_submit([
                'enrolcondcontextid' => \context_course::instance($this->course->id)->id,
                'enrolcondtype' => 'coursecompleted',
                'enrolcondconfig' => '',
                'enrolcondenrol' => 'manual',
                'enrolcondinstanceid' => $this->instance->id,
                'courseid' => $courseid,
            ]),
            true
        );

        $form = $open((int) $this->hidden->id);
        $this->assertTrue($form->is_validated());
        try {
            $form->process_dynamic_submission();
            $this->fail('editornotavailable expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('editornotavailable', $e->errorcode);
        }
        // Control: a visible course is returned with its summary.
        $this->assertStringContainsString('Open course', $open((int) $this->visible->id)->process_dynamic_submission()['summary']);
    }
}
