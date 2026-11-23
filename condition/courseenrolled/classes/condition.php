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

namespace enrolcond_courseenrolled;

use local_enrolconditions\condition\base;
use MoodleQuickForm;
use stdClass;

/**
 * Condition met by users enrolled in other courses.
 *
 * Configuration: ['courseids' => int[], 'mode' => 'any'|'all', 'active' => 0|1].
 *
 * A course of the list that no longer exists (or an empty list, as a restore from another site leaves it) makes the
 * condition unknown in either mode, never not met: a partial list cannot be trusted, since "any" would answer for a
 * shorter list than the teacher wrote and "all" could never be met.
 *
 * @package    enrolcond_courseenrolled
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends base {
    /** @var int Most courses in one condition. */
    public const MAX_COURSES = 50;

    /** @var string The user must be enrolled in at least one of the courses. */
    public const MODE_ANY = 'any';

    /** @var string The user must be enrolled in all of the courses. */
    public const MODE_ALL = 'all';

    /**
     * Valid course ids of a configuration, without duplicates.
     *
     * @param array $config
     * @return int[]
     */
    protected function course_ids(array $config): array {
        $ids = array_map('intval', (array) ($config['courseids'] ?? []));
        return array_values(array_unique(array_filter($ids, fn(int $id) => $id > 0)));
    }

    /**
     * Mode of a configuration.
     *
     * @param array $config
     * @return string One of the MODE_* constants.
     */
    protected function mode(array $config): string {
        return ($config['mode'] ?? '') === self::MODE_ALL ? self::MODE_ALL : self::MODE_ANY;
    }

    #[\Override]
    public function is_met(int $userid, stdClass $instance, array $config): bool {
        global $DB;
        $courseids = $this->course_ids($config);
        // Any missing course makes the whole condition unknown, in both modes: with the course gone, "any"
        // would trust a shorter list than the teacher wrote and "all" could never be met, and neither
        // answer is the teacher's. Also the case of the empty list a restore from another site leaves.
        if (!$courseids || !self::records_exist('course', $courseids)) {
            throw new \moodle_exception('missingcourse', 'enrolcond_courseenrolled');
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params['userid'] = $userid;
        $where = "ue.userid = :userid AND e.courseid $insql";
        if (!empty($instance->id)) {
            // The enrolment being checked never satisfies its own condition.
            $where .= ' AND e.id <> :instanceid';
            $params['instanceid'] = (int) $instance->id;
        }
        if (!array_key_exists('active', $config) || !empty($config['active'])) {
            $where .= ' AND ue.status = :uestatus AND e.status = :estatus
                        AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)';
            $now = time();
            $params += [
                'uestatus' => ENROL_USER_ACTIVE,
                'estatus' => ENROL_INSTANCE_ENABLED,
                'now1' => $now,
                'now2' => $now,
            ];
        }
        $enrolled = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT e.courseid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE $where",
            $params
        );
        return $this->mode($config) === self::MODE_ALL ? $enrolled === count($courseids) : $enrolled > 0;
    }

    #[\Override]
    public function get_description(array $config, bool $met): string {
        global $DB;
        $courseids = $this->course_ids($config);
        $courses = $courseids ? $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname') : [];
        $names = [];
        foreach ($courseids as $id) {
            $names[] = isset($courses[$id])
                ? format_string($courses[$id]->fullname, true, ['context' => \context_course::instance($id)])
                : get_string('descmissing', 'enrolcond_courseenrolled', $id);
        }
        if (count($names) <= 1) {
            return get_string('descone', 'enrolcond_courseenrolled', $names ? $names[0] : '-');
        }
        if ($this->mode($config) === self::MODE_ALL) {
            return get_string('descall', 'enrolcond_courseenrolled', implode('; ', $names));
        }
        return get_string('descany', 'enrolcond_courseenrolled', implode('; ', $names));
    }

    #[\Override]
    public function config_form_definition(MoodleQuickForm $mform): void {
        $mform->addElement(
            'course',
            'courseids',
            get_string('courseids', 'enrolcond_courseenrolled'),
            ['multiple' => true, 'includefrontpage' => false]
        );
        $mform->addHelpButton('courseids', 'courseids', 'enrolcond_courseenrolled');

        $mform->addElement('select', 'mode', get_string('mode', 'enrolcond_courseenrolled'), [
            self::MODE_ANY => get_string('modeany', 'enrolcond_courseenrolled'),
            self::MODE_ALL => get_string('modeall', 'enrolcond_courseenrolled'),
        ]);
        $mform->addHelpButton('mode', 'mode', 'enrolcond_courseenrolled');
        $mform->setDefault('mode', self::MODE_ANY);

        $mform->addElement('advcheckbox', 'active', get_string('active', 'enrolcond_courseenrolled'));
        $mform->addHelpButton('active', 'active', 'enrolcond_courseenrolled');
        $mform->setDefault('active', 1);
    }

    #[\Override]
    public function config_from_form_data(stdClass $data): array {
        return [
            'courseids' => $this->course_ids(['courseids' => $data->courseids ?? []]),
            'mode' => $this->mode(['mode' => $data->mode ?? '']),
            'active' => empty($data->active) ? 0 : 1,
        ];
    }

    #[\Override]
    public function validate_config(array $config): array {
        global $DB;
        $courseids = $this->course_ids($config);
        if (!$courseids || in_array(SITEID, $courseids)) {
            return ['courseids' => get_string('invalidcourse', 'enrolcond_courseenrolled')];
        }
        if (count($courseids) > self::MAX_COURSES) {
            return ['courseids' => get_string('toomany', 'enrolcond_courseenrolled', self::MAX_COURSES)];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids);
        if ($DB->count_records_select('course', "id $insql", $params) !== count($courseids)) {
            return ['courseids' => get_string('invalidcourse', 'enrolcond_courseenrolled')];
        }
        return [];
    }

    /**
     * Every course must be one the user may name ({@see base::can_reference_course()}).
     *
     * @param array $config
     * @param \context $context Course context.
     * @return string|null
     */
    #[\Override]
    public function check_access(array $config, \context $context): ?string {
        // Bounded by validate_config(), but a submitted tree is checked before it is validated.
        foreach (array_slice($this->course_ids($config), 0, self::MAX_COURSES + 1) as $courseid) {
            if (!self::can_reference_course($courseid)) {
                return get_string('noaccess', 'enrolcond_courseenrolled');
            }
        }
        return null;
    }

    #[\Override]
    public function restore_config(array $config, bool $samesite): array {
        // Course ids of another site mean nothing here: the condition fails until it is edited.
        return $samesite ? $config : ['courseids' => []] + $config;
    }

    #[\Override]
    public function get_observed_events(): array {
        return [
            \core\event\user_enrolment_created::class,
            \core\event\user_enrolment_updated::class,
            \core\event\user_enrolment_deleted::class,
        ];
    }
}
