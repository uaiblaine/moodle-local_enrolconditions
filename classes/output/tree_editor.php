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

namespace local_enrolconditions\output;

use context;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_enrolconditions\condition\editor_access;
use local_enrolconditions\condition\manager;
use local_enrolconditions\tree;
use MoodleQuickForm;

/**
 * Editor of a condition tree, kept in a hidden field of a form.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tree_editor implements renderable, templatable {
    /** @var string Id of the editor container. */
    protected string $uniqid;

    /** @var bool[] Keys of the condition nodes stored in the instance ({@see editor_access::get_node_keys()}). */
    protected array $storedkeys;

    /**
     * Constructor.
     *
     * @param tree $tree Tree being edited.
     * @param string $fieldname Name of the hidden form field that receives the JSON.
     * @param context $context Course context, checked by the condition forms.
     * @param tree|null $stored Tree stored in the instance; null when nothing is stored, so every node of $tree is
     *     shown only when the current user may reference it.
     * @param string $enrol Enrolment method of the form, sent to the condition dialog; '' when unknown.
     * @param int $instanceid Enrolment instance being edited, sent to the condition dialog; 0 for a new one.
     */
    public function __construct(
        /** @var tree Tree being edited. */
        protected tree $tree,
        /** @var string Name of the hidden form field. */
        protected string $fieldname,
        /** @var context Course context. */
        protected context $context,
        ?tree $stored = null,
        /** @var string Enrolment method of the form. */
        protected string $enrol = '',
        /** @var int Enrolment instance being edited. */
        protected int $instanceid = 0,
    ) {
        $this->uniqid = 'local_enrolconditions_editor_' . random_string(12);
        $this->storedkeys = $stored ? editor_access::get_node_keys($stored->to_array()) : [];
    }

    /**
     * Id of the editor container.
     *
     * @return string
     */
    public function get_uniqid(): string {
        return $this->uniqid;
    }

    /**
     * Adds the hidden field and the editor to a form.
     *
     * The value of the field is the JSON of the tree (escaped by tree::JSON_FLAGS, so PARAM_NOTAGS keeps it).
     * Without JavaScript the editor shows a message and the field keeps its value.
     *
     * When the form comes back with validation errors, the editor shows the tree that was submitted, so
     * the changes are not lost; if that tree is not valid, it shows the saved one. A submitted node that is not
     * stored in the instance shows its summary only when the current user may reference what it names.
     *
     * Pass the method name and the instance id: the condition dialog asks the method's config capability and
     * recognises the stored nodes from them.
     *
     * @param MoodleQuickForm $mform
     * @param string $fieldname Name of the hidden field.
     * @param string $label Label shown next to the editor.
     * @param context $context Course context.
     * @param string|null $json Saved value, as the instance stores it.
     * @param string $enrol Enrolment method name (enrol_plugin::get_name()); '' when unknown.
     * @param int $instanceid Enrolment instance being edited; 0 for a new one.
     */
    public static function add_to_form(
        MoodleQuickForm $mform,
        string $fieldname,
        string $label,
        context $context,
        ?string $json,
        string $enrol = '',
        int $instanceid = 0,
    ): void {
        global $OUTPUT, $PAGE;

        $candidates = [$json];
        $submitted = optional_param($fieldname, null, PARAM_NOTAGS);
        if ($submitted !== null && data_submitted()) {
            array_unshift($candidates, $submitted);
        }
        $tree = tree::empty();
        foreach ($candidates as $candidate) {
            try {
                $tree = tree::from_json($candidate);
                break;
            } catch (\local_enrolconditions\invalid_tree_exception $e) {
                continue;
            }
        }
        try {
            $stored = tree::from_json($json);
        } catch (\local_enrolconditions\invalid_tree_exception $e) {
            $stored = null;
        }
        $editor = new self($tree, $fieldname, $context, $stored, $enrol, $instanceid);
        $mform->addElement('hidden', $fieldname);
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->addElement('static', $fieldname . '_editor', $label, $OUTPUT->render($editor));
        $PAGE->requires->js_call_amd('local_enrolconditions/tree_editor', 'init', ['#' . $editor->get_uniqid()]);
    }

    /**
     * Data for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $types = [];
        foreach (manager::get_enabled_types() as $type) {
            $condition = manager::get_condition($type);
            if ($condition) {
                $types[] = ['type' => $type, 'name' => $condition->get_name()];
            }
        }
        usort($types, fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        $config = [
            'fieldname' => $this->fieldname,
            'contextid' => $this->context->id,
            'enrol' => $this->enrol,
            'instanceid' => $this->instanceid,
            'maxdepth' => tree::MAX_DEPTH,
            'types' => $types,
            'tree' => $this->with_summaries($this->tree->to_array()),
        ];
        return [
            'uniqid' => $this->uniqid,
            'config' => json_encode($config, tree::JSON_FLAGS),
        ];
    }

    /**
     * Adds the name and the summary of each condition, for display only.
     *
     * The summary names what the condition refers to, so a node that is not stored in the instance (a submitted
     * tree comes from the client) gets a generic text unless the current user may reference what it names and its
     * configuration is valid: the reach check covers only what validation allows (a list within its bound), and a
     * summary must never name more than the check saw. A stored node is summarised as it is.
     *
     * @param array $node
     * @return array
     */
    protected function with_summaries(array $node): array {
        if (isset($node['type'])) {
            $condition = manager::get_condition($node['type']);
            $node['name'] = $condition ? $condition->get_name() : $node['type'];
            if (!$condition) {
                $node['summary'] = get_string('editorunknowntype', 'local_enrolconditions', $node['type']);
            } else if ($this->may_summarise($condition, $node['type'], $node['config'])) {
                $node['summary'] = $condition->get_summary($node['config']);
            } else {
                $node['summary'] = get_string('editornotavailable', 'local_enrolconditions');
            }
            $node['known'] = (bool) $condition;
            return $node;
        }
        $node['children'] = array_map([$this, 'with_summaries'], $node['children']);
        return $node;
    }

    /**
     * Whether the summary of a node may be shown to the current user.
     *
     * @param \local_enrolconditions\condition\base $condition
     * @param string $type
     * @param array $config
     * @return bool True for a node stored unchanged, or a new one that passes the reach check and the validation.
     */
    protected function may_summarise(\local_enrolconditions\condition\base $condition, string $type, array $config): bool {
        if (editor_access::check_node($type, $config, $this->context, $this->storedkeys) !== null) {
            return false;
        }
        try {
            if (isset($this->storedkeys[editor_access::node_key($type, $config)])) {
                return true;
            }
        } catch (\JsonException $e) {
            return false;
        }
        return !$condition->validate_config($config);
    }
}
