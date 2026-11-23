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

    public function test_dialog_does_not_prefill_another_instance_file(): void {
        global $DB, $USER;
        $this->setUser($this->teacher);
        $context = \context_course::instance($this->course->id);
        $record = [
            'contextid' => $context->id,
            'component' => 'enrolcond_userfile',
            'filearea' => 'usernames',
            'itemid' => 77,
            'filepath' => '/',
            'filename' => 'other.csv',
        ];
        get_file_storage()->create_file_from_string($record, "ana\n");
        $config = ['fileid' => 77, 'filename' => 'other.csv', 'count' => 1];
        // The file belongs to the condition of another method of the same course.
        manager::register_enforceable_for_testing('self', new \local_enrolconditions_fake_enforceable());
        $self = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $DB->set_field('enrol', 'customtext1', json_encode(['op' => 'and', 'children' => [
            ['type' => 'userfile', 'config' => $config],
        ]]), ['id' => $self->id]);

        $draftfiles = function (condition_config $form) use ($USER): int {
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
        };
        $open = function () use ($config, $context): condition_config {
            return new condition_config(null, null, 'post', '', [], true, [
                'enrolcondcontextid' => $context->id,
                'enrolcondtype' => 'userfile',
                'enrolcondconfig' => json_encode($config),
                'enrolcondenrol' => 'manual',
                'enrolcondinstanceid' => $this->instance->id,
            ], true);
        };

        // The file is referenced by the other instance only: the dialog of this one starts empty.
        $form = $open();
        $form->set_data_for_dynamic_submission();
        $this->assertSame(0, $draftfiles($form));

        // Control: once this instance stores the node, the dialog shows its file.
        $this->store([['type' => 'userfile', 'negate' => false, 'config' => $config]]);
        $form = $open();
        $form->set_data_for_dynamic_submission();
        $this->assertSame(1, $draftfiles($form));
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
}
