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
 * Two rules, each the only copy of itself:
 * - require_editor_access(): the configuration dialog of a condition is opened from the instance form of an
 *   enrolment method that stores condition trees; it asks what that form asks (the method's config capability).
 * - check_node(): a condition node the user adds or changes must pass base::check_access() for them; a node
 *   already stored unchanged in the instance is never asked again, so a tree an administrator wrote stays
 *   editable. The dialog, the tree editor and the save of a tree ({@see \local_enrolconditions\tree::validate()})
 *   all decide through it.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor_access {
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
     */
    public static function node_key(string $type, array $config): string {
        return sha1($type . "\n" . json_encode(self::sorted($config)));
    }

    /**
     * Keys of every condition node of a tree.
     *
     * @param array $node Root group, as \local_enrolconditions\tree::to_array() returns it.
     * @return bool[] Keys are node_key() values.
     */
    public static function get_node_keys(array $node): array {
        if (isset($node['type'])) {
            return [self::node_key((string) $node['type'], (array) ($node['config'] ?? [])) => true];
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
        if (isset($storedkeys[self::node_key($type, $config)])) {
            return null;
        }
        $condition = manager::get_condition($type);
        return $condition ? $condition->check_access($config, $context) : null;
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
