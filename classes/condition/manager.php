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

use coding_exception;
use local_enrolconditions\enforceable;
use local_enrolconditions\hook\collect_conditions;
use local_enrolconditions\plugininfo\enrolcond;
use local_enrolconditions\tree;

/**
 * Finds the available conditions (enrolcond subplugins and conditions provided through the hook) and the
 * enrolment methods that store condition trees (implementations of \local_enrolconditions\enforceable).
 *
 * Subplugins can be disabled by the administrator. Conditions provided through the hook follow the
 * plugin that provides them: they are available while that plugin is installed.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var array<string, base|null> Subplugin conditions already loaded, keyed by type. */
    protected static array $subplugins = [];

    /** @var array<string, base>|null Conditions provided through the hook, keyed by type. */
    protected static ?array $hooked = null;

    /** @var array<string, base> Conditions registered by unit tests, keyed by type. */
    protected static array $testconditions = [];

    /** @var array<string, enforceable> Enrolment methods registered by unit tests, keyed by name. */
    protected static array $testenforceables = [];

    /**
     * Condition of the given type.
     *
     * Disabled conditions are returned too: trees that already use them must still be evaluated.
     *
     * @param string $type Condition type.
     * @return base|null Null when no installed plugin provides the type.
     */
    public static function get_condition(string $type): ?base {
        if (isset(static::$testconditions[$type])) {
            return static::$testconditions[$type];
        }
        $hooked = static::get_hooked_conditions();
        if (isset($hooked[$type])) {
            return $hooked[$type];
        }
        return static::get_subplugin_condition($type);
    }

    /**
     * Types of every available condition, enabled or not.
     *
     * @return string[]
     */
    public static function get_installed_types(): array {
        $types = array_merge(
            array_keys(\core_plugin_manager::instance()->get_installed_plugins('enrolcond')),
            array_keys(static::get_hooked_conditions()),
            array_keys(static::$testconditions),
        );
        return array_values(array_unique($types));
    }

    /**
     * Types of the conditions that may be added to new trees.
     *
     * @return string[]
     */
    public static function get_enabled_types(): array {
        $types = array_merge(
            array_keys(enrolcond::get_enabled_plugins()),
            array_keys(static::get_hooked_conditions()),
            array_keys(static::$testconditions),
        );
        return array_values(array_unique($types));
    }

    /**
     * Whether conditions of this type may be added to new trees.
     *
     * @param string $type
     * @return bool
     */
    public static function is_enabled(string $type): bool {
        return in_array($type, static::get_enabled_types(), true);
    }

    /**
     * Registers a condition for unit tests, without an installed plugin.
     *
     * @param string $type
     * @param base $condition
     * @throws coding_exception Outside unit tests.
     */
    public static function register_for_testing(string $type, base $condition): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new coding_exception('register_for_testing() is only allowed in unit tests');
        }
        static::$testconditions[$type] = $condition;
    }

    /**
     * Registers an enrolment method that stores condition trees, for unit tests, without an installed plugin.
     *
     * It replaces the installed method of the same name for every lookup of this class.
     *
     * @param string $name Enrolment method name, as in the enrol column of the enrol table.
     * @param enforceable $plugin
     * @throws coding_exception Outside unit tests.
     */
    public static function register_enforceable_for_testing(string $name, enforceable $plugin): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new coding_exception('register_enforceable_for_testing() is only allowed in unit tests');
        }
        static::$testenforceables[$name] = $plugin;
    }

    /**
     * Forgets the loaded conditions and the conditions and methods registered by unit tests.
     */
    public static function reset_caches(): void {
        static::$subplugins = [];
        static::$hooked = null;
        static::$testconditions = [];
        static::$testenforceables = [];
    }

    /**
     * Installed enrolment methods that store condition trees, enabled or not.
     *
     * Disabled methods are included: their instances keep their trees (and the files those trees refer to) and
     * can be enabled again.
     *
     * @return array<string, enforceable> Keyed by enrolment method name.
     */
    public static function get_enforceable_plugins(): array {
        $plugins = [];
        foreach (enrol_get_plugins(false) as $name => $plugin) {
            if ($plugin instanceof enforceable) {
                $plugins[$name] = $plugin;
            }
        }
        return static::$testenforceables + $plugins;
    }

    /**
     * Installed enrolment method that stores condition trees, by name.
     *
     * @param string $name Enrolment method name.
     * @return enforceable|null Null when no such method is installed or it does not store condition trees.
     */
    public static function get_enforceable(string $name): ?enforceable {
        if (isset(static::$testenforceables[$name])) {
            return static::$testenforceables[$name];
        }
        $plugin = $name === '' ? null : enrol_get_plugin($name);
        return $plugin instanceof enforceable ? $plugin : null;
    }

    /**
     * Stored condition tree of an enrolment instance.
     *
     * @param \stdClass $instance Record of the enrol table.
     * @return tree|null Null when its method does not store condition trees or the stored tree is not valid.
     */
    public static function get_instance_tree(\stdClass $instance): ?tree {
        $plugin = static::get_enforceable((string) $instance->enrol);
        if (!$plugin) {
            return null;
        }
        try {
            return tree::from_json($plugin->get_enforcement_tree($instance));
        } catch (\local_enrolconditions\invalid_tree_exception $e) {
            return null;
        }
    }

    /**
     * Stored condition trees of every instance of a course, through the methods' own API.
     *
     * Instances of every method that stores condition trees count, enabled or not.
     *
     * @param int $courseid
     * @return array<int, tree> Keyed by enrolment instance id; instances without a valid tree are left out.
     */
    public static function get_course_trees(int $courseid): array {
        $trees = [];
        foreach (enrol_get_instances($courseid, false) as $instance) {
            $tree = static::get_instance_tree($instance);
            if ($tree && !$tree->is_empty()) {
                $trees[(int) $instance->id] = $tree;
            }
        }
        return $trees;
    }

    /**
     * Instances whose stored tree uses a condition type, with their tree, through the methods' own API.
     *
     * Every installed method that stores condition trees counts, enabled or not, and every instance of it (one
     * query, read as a recordset). The stored text is searched for the type as a JSON string before it is parsed,
     * so a site with thousands of instances pays one pass over their rows and a string search each, and parses only
     * the trees that may use the type.
     *
     * @param string $type Condition type.
     * @param int|null $courseid Only the instances of this course.
     * @return array[] ['instance' => enrol record, 'plugin' => enforceable, 'tree' => tree], by instance id; only
     *     trees that parse and have a node of the type.
     */
    public static function get_trees_with_type(string $type, ?int $courseid = null): array {
        $found = [];
        foreach (static::read_candidates($type, $courseid) as $id => [$instance, $plugin, $json]) {
            try {
                $tree = tree::from_json($json);
            } catch (\local_enrolconditions\invalid_tree_exception $e) {
                continue;
            }
            if (in_array($type, $tree->get_types(), true)) {
                $found[$id] = ['instance' => $instance, 'plugin' => $plugin, 'tree' => $tree];
            }
        }
        return $found;
    }

    /**
     * Instances whose stored value names a condition type but is not a valid tree, with the value decoded.
     *
     * A restore keeps a tree that does not validate (it evaluates to unknown until a teacher fixes it), and it
     * keeps the names a list of usernames holds. The privacy providers read those lists from the decoded value,
     * since {@see tree::from_json()} refuses it. A value that is not JSON holds no list and is not returned.
     *
     * @param string $type Condition type.
     * @param int|null $courseid Only the instances of this course.
     * @return array[] ['instance' => enrol record, 'plugin' => enforceable, 'data' => decoded JSON (objects as
     *     stdClass)], by instance id.
     */
    public static function get_invalid_trees_with_type(string $type, ?int $courseid = null): array {
        $found = [];
        foreach (static::read_candidates($type, $courseid) as $id => [$instance, $plugin, $json]) {
            try {
                tree::from_json($json);
                continue;
            } catch (\local_enrolconditions\invalid_tree_exception $e) {
                $data = json_decode($json);
            }
            if (is_object($data) || is_array($data)) {
                $found[$id] = ['instance' => $instance, 'plugin' => $plugin, 'data' => $data];
            }
        }
        return $found;
    }

    /**
     * Stored values of the instances of the methods that store trees, that name a condition type.
     *
     * One query, read as a recordset; the text is searched for the type as a JSON string before anything parses it.
     *
     * @param string $type Condition type.
     * @param int|null $courseid Only the instances of this course.
     * @return array[] [enrol record, enforceable, stored text], by instance id.
     */
    protected static function read_candidates(string $type, ?int $courseid): array {
        global $DB;
        $plugins = static::get_enforceable_plugins();
        if (!$plugins) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('strval', array_keys($plugins)), SQL_PARAMS_NAMED);
        $where = "enrol $insql";
        if ($courseid !== null) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        $needle = json_encode($type);
        $candidates = [];
        $rs = $DB->get_recordset_select('enrol', $where, $params, 'id');
        foreach ($rs as $instance) {
            $json = $plugins[$instance->enrol]->get_enforcement_tree($instance);
            if ($json !== null && str_contains($json, $needle)) {
                $candidates[(int) $instance->id] = [$instance, $plugins[$instance->enrol], $json];
            }
        }
        $rs->close();
        return $candidates;
    }

    /**
     * Condition class of an installed enrolcond subplugin.
     *
     * @param string $type Subplugin name without the enrolcond_ prefix.
     * @return base|null
     */
    protected static function get_subplugin_condition(string $type): ?base {
        if (array_key_exists($type, static::$subplugins)) {
            return static::$subplugins[$type];
        }
        $condition = null;
        $installed = \core_plugin_manager::instance()->get_installed_plugins('enrolcond');
        if (isset($installed[$type])) {
            $classname = '\\enrolcond_' . $type . '\\condition';
            if (class_exists($classname) && is_subclass_of($classname, base::class)) {
                $condition = new $classname();
            } else {
                debugging("enrolcond_{$type} does not provide {$classname} extending " . base::class, DEBUG_DEVELOPER);
            }
        }
        static::$subplugins[$type] = $condition;
        return $condition;
    }

    /**
     * Conditions provided by other plugins through the hook.
     *
     * Types that clash with an installed subplugin are ignored, with a debugging message.
     *
     * @return array<string, base> Keyed by type.
     */
    protected static function get_hooked_conditions(): array {
        if (static::$hooked !== null) {
            return static::$hooked;
        }
        $hook = new collect_conditions();
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $installed = \core_plugin_manager::instance()->get_installed_plugins('enrolcond');
        static::$hooked = [];
        foreach ($hook->get_conditions() as $type => $condition) {
            if (isset($installed[$type])) {
                debugging("Condition type '{$type}' clashes with the subplugin enrolcond_{$type}", DEBUG_DEVELOPER);
                continue;
            }
            static::$hooked[$type] = $condition;
        }
        return static::$hooked;
    }
}
