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

use MoodleQuickForm;
use stdClass;

/**
 * Base class of every condition.
 *
 * Each subplugin enrolcond_<name> provides the class \enrolcond_<name>\condition extending this one.
 * Other plugins may provide conditions through the hook \local_enrolconditions\hook\collect_conditions;
 * their classes must override get_type() and get_name().
 * Conditions are stateless: the configuration of each use comes in $config, an array that is stored
 * as JSON in the condition tree of the enrolment instance.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Whether the user meets the condition.
     *
     * @param int $userid User being checked.
     * @param stdClass $instance Enrolment instance (record of the enrol table).
     * @param array $config Configuration of this use of the condition.
     * @return bool
     * @throws \moodle_exception When the condition cannot be evaluated (the list, file or record it names is
     *         missing): {@see \local_enrolconditions\tree} turns it into "unknown", which refuses new enrolments
     *         and never suspends, removes or reactivates anybody. Answering false instead would do all three.
     */
    abstract public function is_met(int $userid, stdClass $instance, array $config): bool;

    /**
     * Text shown to the user about this condition.
     *
     * @param array $config Configuration of this use of the condition.
     * @param bool $met Whether the user meets it; false gives the text shown when self enrolment is refused.
     * @return string Plain text or HTML already formatted for display.
     */
    abstract public function get_description(array $config, bool $met): string;

    /**
     * Adds the configuration fields of the condition to a form.
     *
     * The form is shown in a modal for one condition at a time, so the field names do not need a prefix.
     *
     * @param MoodleQuickForm $mform
     */
    abstract public function config_form_definition(MoodleQuickForm $mform): void;

    /**
     * Turns the submitted form data into the configuration stored in the tree.
     *
     * @param stdClass $data Data from the form.
     * @return array Configuration, made of scalars and arrays only (it is stored as JSON).
     */
    abstract public function config_from_form_data(stdClass $data): array;

    /**
     * Validates a configuration.
     *
     * Runs on every save, including trees sent by JavaScript or restored from a backup,
     * so it must not rely on the form validation.
     *
     * @param array $config
     * @return array<string, string> Errors keyed by form field name; empty when valid.
     */
    abstract public function validate_config(array $config): array;

    /**
     * Whether the current user may reference what a configuration names, from a course.
     *
     * validate_config() says whether a configuration is well formed; this says whether the user writing it may
     * point at the records it names (a cohort, another course, a file), since the description of the condition
     * shows their names to the teacher and to every user who fails it. It runs for every condition a user adds
     * or changes: in the configuration dialog, before its summary is returned, and on the save of a tree, for
     * each node that is not already stored unchanged ({@see editor_access::check_node()}). A node already stored
     * is never asked again, so a tree an administrator wrote stays editable by a teacher.
     *
     * Override when the configuration names records whose visibility depends on the user. It runs before
     * validate_config() for a node that is new or changed ({@see editor_access::validate_node()}), so it must cope
     * with a configuration validation would refuse, and must answer a record that does not exist exactly as one the
     * user may not reference: otherwise the two answers tell a user which ids exist.
     *
     * @param array $config Configuration, not validated yet.
     * @param \context $context Course context of the enrolment instance.
     * @return string|null Localised error when the user may not reference it; null when they may.
     */
    public function check_access(array $config, \context $context): ?string {
        return null;
    }

    /**
     * Turns a stored configuration into default values for the configuration form.
     *
     * @param array $config
     * @return array Values keyed by form field name.
     */
    public function config_to_form_data(array $config): array {
        return $config;
    }

    /**
     * Form data for a configuration, in the context of the course being edited.
     *
     * Override when the form needs the context, for example to prepare a draft file area.
     *
     * @param array $config
     * @param \context $context Course context.
     * @return array
     */
    public function config_to_form_data_in_context(array $config, \context $context): array {
        return $this->config_to_form_data($config);
    }

    /**
     * Final configuration after the form was submitted and validated.
     *
     * Called once, when the condition form is saved (not during validation). Override to persist data
     * outside the configuration, for example files.
     *
     * @param array $config Result of config_from_form_data().
     * @param stdClass $data Submitted form data.
     * @param \context $context Course context.
     * @return array
     */
    public function config_after_submission(array $config, stdClass $data, \context $context): array {
        return $config;
    }

    /**
     * Form validation of the configuration fields.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array<string, string> Errors keyed by form field name.
     */
    public function config_form_validation(array $data, array $files): array {
        return $this->validate_config($this->config_from_form_data((object) $data));
    }

    /**
     * Events that may change the result of the condition for a user.
     *
     * The enforcer observes the events itself: local_enrolconditions observes every event ('*') and re-checks
     * the user of the event in the instances that use the condition, so the plugin that provides the condition
     * does not need a db/events.php for them.
     *
     * @return string[] Fully qualified event class names.
     */
    public function get_observed_events(): array {
        return [];
    }

    /**
     * Whether the current user may name a course in a condition ({@see check_access()}).
     *
     * Mirrors the rule of core's course form element (MoodleQuickForm_course::setValue(), lib/form/course.php),
     * the element that picks courses in the dialogs: core_course_category::can_view_course_info() allows it, or the
     * course is one enrol_get_my_courses() lists, which is an active enrolment (the same test is_enrolled() makes
     * with $onlyactive) in a course that is visible or where the user holds moodle/course:viewhiddencourses
     * (lib/enrollib.php, the visibility loop at the end of enrol_get_my_courses()). Keep the three in step; the
     * login-as restriction of enrol_get_my_courses() is not mirrored, since a user logged in as another cannot
     * configure enrolment methods of other courses.
     *
     * The answer is kept for the request, per user and course ({@see records_exist()}'s request cache): a tree
     * re-rendered by the editor may name the same courses in a hundred nodes.
     *
     * @param int $courseid
     * @return bool False as well when the course does not exist.
     */
    protected static function can_reference_course(int $courseid): bool {
        global $USER;
        $cache = self::existence_cache();
        $key = 'reach_' . (int) $USER->id . '_' . $courseid;
        $known = $cache->get($key);
        if ($known === false) {
            $known = self::decide_course_reach($courseid) ? 1 : 0;
            $cache->set($key, $known);
        }
        return (bool) $known;
    }

    /**
     * The rule of can_reference_course(), without the request cache.
     *
     * @param int $courseid
     * @return bool
     */
    private static function decide_course_reach(int $courseid): bool {
        global $DB, $USER;
        $course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], 'id, category, visible') : false;
        if (!$course) {
            return false;
        }
        if (\core_course_category::can_view_course_info($course)) {
            return true;
        }
        $context = \context_course::instance($courseid);
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
            return false;
        }
        return is_enrolled($context, $USER, '', true);
    }

    /**
     * Whether every record of a table exists, answered once per request for each id.
     *
     * The periodic re-check evaluates one condition for every participant of an instance, so the existence
     * of the cohort, course or field it names is looked up once and kept in a request cache (never shared: a
     * deletion is seen by the next request). Ids that are not positive do not exist, and neither does an empty
     * list: a configuration that names no record (what a restore from another site leaves) is not one whose records
     * exist, so callers do not test for it themselves and an empty list is never cached.
     *
     * @param string $table Table without prefix, with an id column.
     * @param int[] $ids
     * @return bool False for an empty list or when any id is missing; true when every id exists.
     */
    protected static function records_exist(string $table, array $ids): bool {
        global $DB;
        if (!$ids) {
            return false;
        }
        $cache = self::existence_cache();
        $unknown = [];
        foreach (array_unique($ids) as $id) {
            if ($id <= 0) {
                return false;
            }
            $known = $cache->get($table . '_' . $id);
            if ($known === false) {
                $unknown[] = $id;
            } else if (!$known) {
                return false;
            }
        }
        if ($unknown) {
            [$insql, $params] = $DB->get_in_or_equal($unknown);
            $found = $DB->get_fieldset_select($table, 'id', "id $insql", $params);
            $found = array_map('intval', $found);
            $all = true;
            foreach ($unknown as $id) {
                $exists = in_array($id, $found, true);
                $cache->set($table . '_' . $id, $exists ? 1 : 0);
                $all = $all && $exists;
            }
            return $all;
        }
        return true;
    }

    /**
     * Forgets what {@see records_exist()} and {@see can_reference_course()} know, for code that deletes a record,
     * or changes what a user may see, and decides again in the same request.
     */
    public static function reset_existence_cache(): void {
        self::existence_cache()->purge();
    }

    /**
     * Request cache of {@see records_exist()}.
     *
     * @return \cache
     */
    private static function existence_cache(): \cache {
        return \cache::make_from_params(
            \cache_store::MODE_REQUEST,
            'local_enrolconditions',
            'recordexists',
            [],
            ['simplekeys' => true, 'simpledata' => true]
        );
    }

    /**
     * Configuration to use in a course restored from a backup.
     *
     * Override when the configuration refers to records that the backup does not carry (cohorts, other
     * courses): from another site, make the condition fail instead of pointing to an unrelated record.
     *
     * @param array $config Configuration in the backup.
     * @param bool $samesite Whether the backup comes from this site.
     * @return array
     */
    public function restore_config(array $config, bool $samesite): array {
        return $config;
    }

    /**
     * Short text that describes a configured condition in the condition editor.
     *
     * @param array $config
     * @return string Plain text or HTML already formatted for display.
     */
    public function get_summary(array $config): string {
        return $this->get_description($config, false);
    }

    /**
     * Identifier of the condition type, stored in the condition trees.
     *
     * For a subplugin it is the subplugin name without the enrolcond_ prefix. Conditions provided
     * through the hook must override it with an identifier that starts with their component name.
     *
     * @return string
     */
    public function get_type(): string {
        $namespace = explode('\\', static::class)[0];
        return str_starts_with($namespace, 'enrolcond_') ? substr($namespace, strlen('enrolcond_')) : $namespace;
    }

    /**
     * Human readable name of the condition type.
     *
     * Defaults to the pluginname string of the component of the class.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', explode('\\', static::class)[0]);
    }
}
