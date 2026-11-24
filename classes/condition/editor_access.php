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

use context;
use moodle_exception;
use required_capability_exception;
use stdClass;

/**
 * Who may edit a condition tree, and which nodes of a tree the current user may reference.
 *
 * Three rules, each the only copy of itself:
 * - require_editor_access(): the configuration dialog of a condition is opened from the instance form of an
 *   enrolment method that stores condition trees; it asks what that form asks (the method's config capability).
 * - check_node(): a condition node the user adds or changes must pass base::check_access() for them; a node
 *   already stored unchanged in the instance is never asked again, so a tree an administrator wrote stays
 *   editable. The dialog, the tree editor and the save of a tree ({@see \local_enrolconditions\tree::validate()})
 *   all decide through it.
 * - validate_node(): the errors of a node, the reach check first, so a record that does not exist and one the user
 *   may not reference get the same answer on every door (the dialog and the save of a tree).
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor_access {
    /** @var string Field of the configuration dialog that shows the reach error. */
    public const ACCESS_FIELD = 'enrolcondaccess';

    /**
     * Checks that the current user may edit the condition tree of an enrolment instance in a course.
     *
     * The method decides the capability: the instance row when there is one (its course must be the course of
     * the context), else the posted method name. Without either, the user must hold the config capability of at
     * least one installed method that stores condition trees, so leaving the name out never asks less.
     *
     * @param context $context Course context of the instance.
     * @param string $enrol Enrolment method name sent by the form; '' when unknown.
     * @param int $instanceid Enrolment instance being edited; 0 for a new one.
     * @return stdClass|null The instance record, when $instanceid was given.
     * @throws moodle_exception When the context, the instance or the method do not fit.
     * @throws required_capability_exception When the user lacks a capability.
     */
    public static function require_editor_access(context $context, string $enrol, int $instanceid): ?stdClass {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE) {
            throw new moodle_exception('invalidcontext');
        }
        require_capability('moodle/course:enrolconfig', $context);

        $instance = null;
        if ($instanceid > 0) {
            $instance = $DB->get_record('enrol', ['id' => $instanceid]);
            if (!$instance || (int) $instance->courseid !== (int) $context->instanceid) {
                throw new moodle_exception('invalidenrolinstance', 'enrol');
            }
            if ($enrol !== '' && $enrol !== $instance->enrol) {
                throw new moodle_exception('invalidenrolinstance', 'enrol');
            }
            $enrol = (string) $instance->enrol;
        }

        if ($enrol !== '') {
            if (!manager::get_enforceable($enrol)) {
                throw new moodle_exception('invalidenrolinstance', 'enrol');
            }
            $names = [$enrol];
        } else {
            $names = array_keys(manager::get_enforceable_plugins());
        }
        $capabilities = [];
        foreach ($names as $name) {
            $capability = 'enrol/' . $name . ':config';
            if (get_capability_info($capability)) {
                $capabilities[] = $capability;
            }
        }
        if ($capabilities && !has_any_capability($capabilities, $context)) {
            throw new required_capability_exception($context, reset($capabilities), 'nopermissions', '');
        }
        return $instance;
    }

    /**
     * Key that identifies a condition node by its type and configuration.
     *
     * Two nodes with the same key are the same condition; the key ignores negation and the key order of
     * associative arrays (a client may serialise them in another order).
     *
     * @param string $type
     * @param array $config
     * @return string
     * @throws \JsonException When the configuration cannot be encoded (invalid UTF-8, a non-finite number): such a
     *     node has no key, so it is never a stored node.
     */
    public static function node_key(string $type, array $config): string {
        return sha1($type . "\n" . json_encode(self::sorted($config), JSON_THROW_ON_ERROR));
    }

    /**
     * Keys of every condition node of a tree.
     *
     * A node whose configuration cannot be encoded has no key and is left out: it is never "stored unchanged".
     *
     * @param array $node Root group, as \local_enrolconditions\tree::to_array() returns it.
     * @return bool[] Keys are node_key() values.
     */
    public static function get_node_keys(array $node): array {
        if (isset($node['type'])) {
            try {
                return [self::node_key((string) $node['type'], (array) ($node['config'] ?? [])) => true];
            } catch (\JsonException $e) {
                return [];
            }
        }
        $keys = [];
        foreach ($node['children'] ?? [] as $child) {
            $keys += self::get_node_keys((array) $child);
        }
        return $keys;
    }

    /**
     * Whether the current user may reference what a condition node names.
     *
     * @param string $type
     * @param array $config
     * @param context $context Course context of the instance.
     * @param array $storedkeys get_node_keys() of the tree stored in the instance; nodes found there pass.
     * @return string|null Localised error; null when the node is stored unchanged, its type is not installed
     *     (the tree validation refuses that on its own) or its condition allows it.
     */
    public static function check_node(string $type, array $config, context $context, array $storedkeys): ?string {
        try {
            $key = self::node_key($type, $config);
        } catch (\JsonException $e) {
            // Not stored (it has no key) and not allowed: no condition can describe what it names.
            return get_string('editornotavailable', 'local_enrolconditions');
        }
        if (isset($storedkeys[$key])) {
            return null;
        }
        $condition = manager::get_condition($type);
        return $condition ? $condition->check_access($config, $context) : null;
    }

    /**
     * Errors of a condition node a user saves, the reach check before the validation of the configuration.
     *
     * For a node that is new or changed, the reach check answers first: base::check_access() refuses a record that
     * does not exist with the same text as one the user may not reference, so the validation (which names a
     * missing record precisely) never tells the two apart for ids the user typed. A node stored unchanged passes
     * the reach check and gets the precise validation, so a teacher learns that a record of a stored tree is gone.
     *
     * @param base $condition
     * @param string $type Type of the node.
     * @param array $config
     * @param context|null $context Course context of the instance; null skips the reach check.
     * @param array $storedkeys get_node_keys() of the tree stored in the instance.
     * @param callable|null $validate Returns the validation errors keyed by field; defaults to validate_config().
     * @return array<string, string> Errors keyed by form field; the reach error under ACCESS_FIELD; empty when valid.
     */
    public static function validate_node(
        base $condition,
        string $type,
        array $config,
        ?context $context,
        array $storedkeys,
        ?callable $validate = null
    ): array {
        if ($context !== null) {
            $denied = self::check_node($type, $config, $context, $storedkeys);
            if ($denied !== null) {
                return [self::ACCESS_FIELD => $denied];
            }
        }
        return $validate ? $validate() : $condition->validate_config($config);
    }

    /**
     * Copy of a value with the keys of every associative array sorted.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function sorted($value) {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
        return array_map([self::class, 'sorted'], $value);
    }
}
