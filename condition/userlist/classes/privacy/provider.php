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

namespace enrolcond_userlist\privacy;

use core_text;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_enrolconditions\condition\manager;
use local_enrolconditions\local\usernames;
use local_enrolconditions\tree;
use local_enrolconditions\tree_writer;

/**
 * Privacy provider: the usernames a teacher types name the people they list.
 *
 * The list is kept in the condition tree that the enrolment method stores in its instance, read through
 * \local_enrolconditions\enforceable (every installed method that stores trees, enabled or not). A user is found by
 * their username in the lists of each course, or, once their account is deleted, by the md5 of a listed name that
 * core keeps in their email column ({@see usernames::matcher()}). Export gives the instances that list the user.
 *
 * Erasing removes the name from the lists and writes the tree back through \local_enrolconditions\tree_writer, when
 * the method implements it. A method that does not keeps its trees as they are: the engine has no other way to
 * write them, and the name goes with the condition or the instance when a teacher removes either.
 *
 * A stored value that is valid JSON but not a valid tree (a restore keeps one, and it evaluates to unknown) is
 * searched and erased by walking the decoded value: any nested 'children', any node of this type whose
 * 'config' holds a list of 'usernames'. A value that is not JSON holds no list.
 *
 * Search cost: every request reads each instance of those methods twice (one query each) and parses only the
 * values whose text names the condition type ({@see manager::get_trees_with_type()},
 * {@see manager::get_invalid_trees_with_type()}).
 *
 * @package    enrolcond_userlist
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Condition type. */
    protected const TYPE = 'userlist';

    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('enrol', ['usernames' => 'privacy:metadata:enrol:usernames'], 'privacy:metadata:enrol');
        return $collection;
    }

    /**
     * Listed names of every list of usernames condition of an instance's stored tree.
     *
     * @param array $found An entry of {@see self::instances()}: a parsed 'tree', or the decoded 'data' of a value
     *     that is not a valid tree.
     * @return bool[] Keys are the listed names, in lower case.
     */
    protected static function listed(array $found): array {
        $names = [];
        if (isset($found['tree'])) {
            $found['tree']->with_conditions(function (array $node) use (&$names): array {
                if ($node['type'] === self::TYPE) {
                    foreach ((array) ($node['config']['usernames'] ?? []) as $name) {
                        $names[(string) $name] = true;
                    }
                }
                return $node;
            });
            return $names;
        }
        self::walk_lists($found['data'], function (array $list) use (&$names): array {
            foreach ($list as $name) {
                if (is_scalar($name)) {
                    $names[core_text::strtolower((string) $name)] = true;
                }
            }
            return $list;
        });
        return $names;
    }

    /**
     * Visits every list of usernames of a decoded value that is not a valid tree.
     *
     * Walks any nested 'children' and takes any node whose 'type' is this condition's and whose
     * 'config'->'usernames' is a list, whatever else the value holds; the rest of the value is returned as it was.
     *
     * @param mixed $node Decoded JSON (objects as stdClass).
     * @param callable $visit Receives the list and returns it, changed or not.
     * @param int $depth Levels walked so far.
     * @return mixed The value, with the visited lists replaced.
     */
    protected static function walk_lists(mixed $node, callable $visit, int $depth = 0): mixed {
        if ($depth > 512) {
            return $node;
        }
        if (is_array($node)) {
            return array_map(fn($child) => self::walk_lists($child, $visit, $depth + 1), $node);
        }
        if (!($node instanceof \stdClass)) {
            return $node;
        }
        if (
            ($node->type ?? null) === self::TYPE && ($node->config ?? null) instanceof \stdClass
                && is_array($node->config->usernames ?? null) && array_is_list($node->config->usernames)
        ) {
            $node->config->usernames = $visit($node->config->usernames);
        }
        if (isset($node->children) && is_array($node->children)) {
            $node->children = self::walk_lists($node->children, $visit, $depth + 1);
        }
        return $node;
    }

    /**
     * Instances whose trees use the condition, optionally of one course, those that do not validate included.
     *
     * @param int|null $courseid
     * @return array[] As manager::get_trees_with_type() returns them, plus entries with 'data' instead of 'tree'
     *     for the stored values of manager::get_invalid_trees_with_type().
     */
    protected static function instances(?int $courseid = null): array {
        return manager::get_trees_with_type(self::TYPE, $courseid) + manager::get_invalid_trees_with_type(self::TYPE, $courseid);
    }

    /**
     * Course id of a course context.
     *
     * @param \context $context
     * @return int|null Null for any other context.
     */
    protected static function course_of(\context $context): ?int {
        return $context->contextlevel === CONTEXT_COURSE ? (int) $context->instanceid : null;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $matcher = usernames::matcher($userid);
        if ($matcher === null) {
            return $contextlist;
        }
        $courseids = [];
        foreach (self::instances() as $found) {
            if (usernames::find(self::listed($found), $matcher) !== null) {
                $courseids[(int) $found['instance']->courseid] = true;
            }
        }
        if ($courseids) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($courseids), SQL_PARAMS_NAMED);
            $contextlist->add_from_sql(
                "SELECT id FROM {context} WHERE contextlevel = :level AND instanceid $insql",
                $params + ['level' => CONTEXT_COURSE]
            );
        }
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $courseid = self::course_of($userlist->get_context());
        if ($courseid === null) {
            return;
        }
        $names = [];
        foreach (self::instances($courseid) as $found) {
            $names += self::listed($found);
        }
        usernames::add_listed_users($userlist, array_map('strval', array_keys($names)));
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        $matcher = usernames::matcher((int) $contextlist->get_user()->id);
        if ($matcher === null) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::course_of($context);
            if ($courseid === null) {
                continue;
            }
            $instances = [];
            $username = null;
            foreach (self::instances($courseid) as $found) {
                $name = usernames::find(self::listed($found), $matcher);
                if ($name !== null) {
                    $username = $name;
                    $instances[] = (object) [
                        'enrol' => (string) $found['instance']->enrol,
                        'instanceid' => (int) $found['instance']->id,
                        'name' => (string) ($found['instance']->name ?? ''),
                    ];
                }
            }
            if ($instances) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'enrolcond_userlist')],
                    (object) ['username' => $username, 'instances' => $instances]
                );
            }
        }
    }

    /**
     * Removes names from the lists of the instances of a course and writes back each changed tree.
     *
     * @param int $courseid
     * @param array|null $matchers Results of usernames::matcher(); null removes every name.
     */
    protected static function remove_from_lists(int $courseid, ?array $matchers): void {
        foreach (self::instances($courseid) as $found) {
            $changed = false;
            $keep = function (array $list) use ($matchers, &$changed): array {
                $list = array_values(array_map('strval', $list));
                $kept = $matchers === null ? [] : array_values(array_filter(
                    $list,
                    fn(string $name): bool => !usernames::names_any(core_text::strtolower($name), $matchers)
                ));
                $changed = $changed || count($kept) !== count($list);
                return $kept;
            };
            if (isset($found['tree'])) {
                $json = $found['tree']->with_conditions(function (array $node) use ($keep): array {
                    if ($node['type'] === self::TYPE) {
                        $node['config']['usernames'] = $keep((array) ($node['config']['usernames'] ?? []));
                    }
                    return $node;
                })->to_json();
            } else {
                // Not a valid tree, and kept as it is but for the names: the writer does not validate.
                $json = json_encode(
                    self::walk_lists($found['data'], $keep),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
                );
            }
            // A method that cannot store a tree keeps its list (see the class comment).
            if ($changed && $found['plugin'] instanceof tree_writer) {
                $found['plugin']->set_enforcement_tree($found['instance'], (string) $json);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        $courseid = self::course_of($context);
        if ($courseid !== null) {
            self::remove_from_lists($courseid, null);
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $matcher = usernames::matcher((int) $contextlist->get_user()->id);
        if ($matcher === null) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::course_of($context);
            if ($courseid !== null) {
                self::remove_from_lists($courseid, [$matcher]);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $courseid = self::course_of($userlist->get_context());
        $matchers = array_values(array_filter(array_map(
            fn($userid) => usernames::matcher((int) $userid),
            $userlist->get_userids()
        )));
        if ($courseid !== null && $matchers) {
            self::remove_from_lists($courseid, $matchers);
        }
    }
}
