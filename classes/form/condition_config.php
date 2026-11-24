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

namespace local_enrolconditions\form;

use context;
use core\url;
use local_enrolconditions\condition\base;
use local_enrolconditions\condition\editor_access;
use local_enrolconditions\condition\manager;
use local_enrolconditions\tree;
use moodle_exception;
use stdClass;

/**
 * Modal form that edits the configuration of one condition of a tree.
 *
 * It does not save anything in the instance: it returns the configuration (JSON) and its summary to the tree
 * editor, which keeps them in the field of the enrolment method form. A condition may store data of its own on
 * submission (the file of enrolcond_userfile).
 *
 * The configuration posted by the editor (FIELD_CONFIG) is the client's: it only pre-fills the form when it is
 * a node already stored in the instance or one the user may reference, and the returned configuration passes
 * editor_access::check_node() before its summary is built.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_config extends \core_form\dynamic_form {
    /** @var string Name of the hidden field with the context id. */
    protected const FIELD_CONTEXT = 'enrolcondcontextid';

    /** @var string Name of the hidden field with the condition type. */
    protected const FIELD_TYPE = 'enrolcondtype';

    /** @var string Name of the hidden field with the current configuration (JSON). */
    protected const FIELD_CONFIG = 'enrolcondconfig';

    /** @var string Name of the hidden field with the enrolment method of the instance form. */
    protected const FIELD_ENROL = 'enrolcondenrol';

    /** @var string Name of the hidden field with the enrolment instance being edited (0 for a new one). */
    protected const FIELD_INSTANCE = 'enrolcondinstanceid';

    /** @var stdClass|null|false Instance being edited; false until loaded. */
    protected $instance = false;

    /**
     * Condition being edited.
     *
     * @return base
     * @throws moodle_exception When the type is not available.
     */
    protected function get_condition(): base {
        $type = $this->optional_param(self::FIELD_TYPE, '', PARAM_ALPHANUMEXT);
        $condition = $type === '' ? null : manager::get_condition($type);
        if (!$condition) {
            throw new moodle_exception('treeunknowntype', 'local_enrolconditions', '', $type);
        }
        return $condition;
    }

    /**
     * Configuration sent by the editor.
     *
     * @return array
     */
    protected function get_current_config(): array {
        $json = $this->optional_param(self::FIELD_CONFIG, '', PARAM_NOTAGS);
        $config = $json === '' ? [] : json_decode($json, true);
        return is_array($config) ? $config : [];
    }

    #[\Override]
    protected function get_context_for_dynamic_submission(): context {
        $contextid = $this->optional_param(self::FIELD_CONTEXT, 0, PARAM_INT);
        return context::instance_by_id($contextid);
    }

    #[\Override]
    protected function check_access_for_dynamic_submission(): void {
        $this->instance = editor_access::require_editor_access(
            $this->get_context_for_dynamic_submission(),
            $this->optional_param(self::FIELD_ENROL, '', PARAM_ALPHANUMEXT),
            $this->optional_param(self::FIELD_INSTANCE, 0, PARAM_INT)
        );
        // New and changed conditions must be of an enabled type; the editor offers no other.
        $type = $this->optional_param(self::FIELD_TYPE, '', PARAM_ALPHANUMEXT);
        if (!manager::is_enabled($type)) {
            throw new moodle_exception('treeunknowntype', 'local_enrolconditions', '', $type);
        }
    }

    /**
     * Keys of the condition nodes stored in the instance being edited.
     *
     * @return bool[] editor_access::get_node_keys() of the stored tree; empty for a new instance.
     */
    protected function get_stored_keys(): array {
        if ($this->instance === false) {
            $this->check_access_for_dynamic_submission();
        }
        $tree = $this->instance ? manager::get_instance_tree($this->instance) : null;
        return $tree ? editor_access::get_node_keys($tree->to_array()) : [];
    }

    /**
     * Error of a configuration the current user may not reference.
     *
     * @param base $condition
     * @param array $config
     * @return string|null
     */
    protected function check_node(base $condition, array $config): ?string {
        return editor_access::check_node(
            $condition->get_type(),
            $config,
            $this->get_context_for_dynamic_submission(),
            $this->get_stored_keys()
        );
    }

    #[\Override]
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', self::FIELD_CONTEXT);
        $mform->setType(self::FIELD_CONTEXT, PARAM_INT);
        $mform->addElement('hidden', self::FIELD_TYPE);
        $mform->setType(self::FIELD_TYPE, PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', self::FIELD_CONFIG);
        $mform->setType(self::FIELD_CONFIG, PARAM_NOTAGS);
        $mform->addElement('hidden', self::FIELD_ENROL);
        $mform->setType(self::FIELD_ENROL, PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', self::FIELD_INSTANCE);
        $mform->setType(self::FIELD_INSTANCE, PARAM_INT);
        // Shows the error of a configuration the user may not reference; empty otherwise.
        $mform->addElement('static', editor_access::ACCESS_FIELD, '');

        $this->get_condition()->config_form_definition($mform);
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $condition = $this->get_condition();
        // The reach check answers first, as on the save of a tree: a missing record and one out of reach get the
        // same answer ({@see editor_access::validate_node()}).
        return array_merge($errors, editor_access::validate_node(
            $condition,
            $condition->get_type(),
            $condition->config_from_form_data((object) $data),
            $this->get_context_for_dynamic_submission(),
            $this->get_stored_keys(),
            fn(): array => $condition->config_form_validation($data, $files)
        ));
    }

    #[\Override]
    public function set_data_for_dynamic_submission(): void {
        $condition = $this->get_condition();
        $config = $this->get_current_config();
        if ($config && ($condition->validate_config($config) || $this->check_node($condition, $config) !== null)) {
            // The form would show the names of what the posted configuration refers to: start empty instead.
            $config = [];
        }
        $data = $condition->config_to_form_data_in_context($config, $this->get_context_for_dynamic_submission());
        $data[self::FIELD_CONTEXT] = $this->get_context_for_dynamic_submission()->id;
        $data[self::FIELD_TYPE] = $condition->get_type();
        $data[self::FIELD_CONFIG] = json_encode($config, tree::JSON_FLAGS);
        $data[self::FIELD_ENROL] = $this->optional_param(self::FIELD_ENROL, '', PARAM_ALPHANUMEXT);
        $data[self::FIELD_INSTANCE] = $this->optional_param(self::FIELD_INSTANCE, 0, PARAM_INT);
        $this->set_data($data);
    }

    #[\Override]
    public function process_dynamic_submission(): array {
        $condition = $this->get_condition();
        $data = $this->get_data();
        $config = $condition->config_after_submission(
            $condition->config_from_form_data($data),
            $data,
            $this->get_context_for_dynamic_submission()
        );
        // The summary names what the configuration refers to: check the final configuration, not only the form's.
        $error = $this->check_node($condition, $config);
        if ($error !== null) {
            throw new moodle_exception('editornotavailable', 'local_enrolconditions');
        }
        return [
            'config' => json_encode($config, tree::JSON_FLAGS),
            'summary' => $condition->get_summary($config),
        ];
    }

    #[\Override]
    protected function get_page_url_for_dynamic_submission(): url {
        $context = $this->get_context_for_dynamic_submission();
        return new url('/enrol/instances.php', ['id' => $context->instanceid]);
    }
}
