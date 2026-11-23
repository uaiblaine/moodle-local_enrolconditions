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

use local_enrolconditions\enforceable;
use MoodleQuickForm;

/**
 * Form fields of the enforcer: the action when the conditions are no longer met, reactivation and check modes.
 *
 * Enrolment methods that implement \local_enrolconditions\enforceable add them to their instance form with
 * add_to_form() and read the submitted values with get_submitted(), then store them in their own columns.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enforcement_form {
    /** @var string Field with the action. */
    public const FIELD_ACTION = 'conditionaction';

    /** @var string Field with the reactivation. */
    public const FIELD_REACTIVATE = 'conditionreactivate';

    /** @var string Group with the check modes. */
    public const GROUP_CHECKMODES = 'checkmodes';

    /** @var array Field of each optional check mode. */
    public const CHECK_MODE_FIELDS = [
        'checkmodelogin' => enforceable::CHECK_LOGIN,
        'checkmodeperiodic' => enforceable::CHECK_PERIODIC,
        'checkmodeevents' => enforceable::CHECK_EVENTS,
    ];

    /**
     * Options of the action.
     *
     * @return array
     */
    public static function get_action_options(): array {
        return [
            enforceable::ACTION_NONE => get_string('conditionactionnone', 'local_enrolconditions'),
            enforceable::ACTION_SUSPEND => get_string('conditionactionsuspend', 'local_enrolconditions'),
            enforceable::ACTION_REMOVE => get_string('conditionactionremove', 'local_enrolconditions'),
        ];
    }

    /**
     * Adds the fields to a form.
     *
     * @param MoodleQuickForm $mform
     * @param int $action Current action, one of the enforceable::ACTION_ constants.
     * @param bool $reactivate Current reactivation.
     * @param int $modes Current check modes, bitmask of the enforceable::CHECK_ constants.
     */
    public static function add_to_form(MoodleQuickForm $mform, int $action, bool $reactivate, int $modes): void {
        $mform->addElement(
            'select',
            self::FIELD_ACTION,
            get_string('conditionaction', 'local_enrolconditions'),
            self::get_action_options()
        );
        $mform->addHelpButton(self::FIELD_ACTION, 'conditionaction', 'local_enrolconditions');
        $mform->setDefault(self::FIELD_ACTION, $action);

        $mform->addElement('advcheckbox', self::FIELD_REACTIVATE, get_string('conditionreactivate', 'local_enrolconditions'));
        $mform->addHelpButton(self::FIELD_REACTIVATE, 'conditionreactivate', 'local_enrolconditions');
        $mform->setDefault(self::FIELD_REACTIVATE, (int) $reactivate);

        $elements = [
            $mform->createElement('static', 'checkmodeenrol', '', get_string('checkmodeenrol', 'local_enrolconditions')),
        ];
        foreach (array_keys(self::CHECK_MODE_FIELDS) as $name) {
            $label = match ($name) {
                'checkmodelogin' => get_string('checkmodelogin', 'local_enrolconditions'),
                'checkmodeperiodic' => get_string('checkmodeperiodic', 'local_enrolconditions'),
                'checkmodeevents' => get_string('checkmodeevents', 'local_enrolconditions'),
            };
            $elements[] = $mform->createElement('advcheckbox', $name, '', $label);
        }
        $mform->addGroup(
            $elements,
            self::GROUP_CHECKMODES,
            get_string('checkmodes', 'local_enrolconditions'),
            '<br>',
            false
        );
        $mform->addHelpButton(self::GROUP_CHECKMODES, 'checkmodes', 'local_enrolconditions');
        foreach (self::CHECK_MODE_FIELDS as $name => $bit) {
            $mform->setDefault($name, (int) (($modes & $bit) === $bit));
        }
    }

    /**
     * Values submitted by the fields, falling back to the current ones for the fields not sent.
     *
     * The form fields are removed from $fields, so the rest can be stored as the instance.
     *
     * @param array $fields Submitted data, changed in place.
     * @param int $action Current action.
     * @param bool $reactivate Current reactivation.
     * @param int $modes Current check modes.
     * @return array ['action' => int, 'reactivate' => bool, 'modes' => int, 'sent' => bool]; modes always includes
     *     enforceable::CHECK_ENROL, and sent tells whether any field was submitted.
     */
    public static function get_submitted(array &$fields, int $action, bool $reactivate, int $modes): array {
        $sent = false;
        if (array_key_exists(self::FIELD_ACTION, $fields)) {
            $action = (int) $fields[self::FIELD_ACTION];
            $sent = true;
        }
        if (array_key_exists(self::FIELD_REACTIVATE, $fields)) {
            $reactivate = (bool) $fields[self::FIELD_REACTIVATE];
            $sent = true;
        }
        foreach (self::CHECK_MODE_FIELDS as $name => $bit) {
            if (array_key_exists($name, $fields)) {
                $modes = empty($fields[$name]) ? ($modes & ~$bit) : ($modes | $bit);
                $sent = true;
            }
        }
        unset($fields[self::FIELD_ACTION], $fields[self::FIELD_REACTIVATE]);
        foreach (array_keys(self::CHECK_MODE_FIELDS) as $name) {
            unset($fields[$name]);
        }
        return [
            'action' => $action,
            'reactivate' => $reactivate,
            'modes' => $modes | enforceable::CHECK_ENROL,
            'sent' => $sent,
        ];
    }
}
