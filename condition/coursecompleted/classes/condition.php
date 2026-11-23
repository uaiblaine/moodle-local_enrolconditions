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

namespace enrolcond_coursecompleted;

use local_enrolconditions\condition\base;
use MoodleQuickForm;
use stdClass;

/**
 * Condition met by users who completed another course.
 *
 * Configuration: ['courseid' => id].
 *
 * @package    enrolcond_coursecompleted
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends base {
    #[\Override]
    public function is_met(int $userid, stdClass $instance, array $config): bool {
        global $DB;
        $courseid = (int) ($config['courseid'] ?? 0);
        if (!self::records_exist('course', [$courseid])) {
            // A deleted course has no completion rows, which is not the same as "not completed": the condition is
            // unknown (also after a restore from another site, which leaves id 0) until a teacher fixes it.
            throw new \moodle_exception('missingcourse', 'enrolcond_coursecompleted');
        }
        return $DB->record_exists_select(
            'course_completions',
            'userid = :userid AND course = :courseid AND timecompleted IS NOT NULL AND timecompleted > 0',
            ['userid' => $userid, 'courseid' => $courseid]
        );
    }

    #[\Override]
    public function get_description(array $config, bool $met): string {
        global $DB;
        $courseid = (int) ($config['courseid'] ?? 0);
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
        if (!$course) {
            return get_string('descmissing', 'enrolcond_coursecompleted', $courseid);
        }
        $name = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);
        return get_string('desc', 'enrolcond_coursecompleted', $name);
    }

    #[\Override]
    public function config_form_definition(MoodleQuickForm $mform): void {
        $mform->addElement('course', 'courseid', get_string('course'), ['multiple' => false, 'includefrontpage' => false]);
        $mform->addHelpButton('courseid', 'course', 'enrolcond_coursecompleted');
    }

    #[\Override]
    public function config_from_form_data(stdClass $data): array {
        $courseid = $data->courseid ?? 0;
        if (is_array($courseid)) {
            $courseid = reset($courseid);
        }
        return ['courseid' => (int) $courseid];
    }

    #[\Override]
    public function validate_config(array $config): array {
        global $DB;
        $courseid = (int) ($config['courseid'] ?? 0);
        if ($courseid <= 0 || $courseid == SITEID || !$DB->record_exists('course', ['id' => $courseid])) {
            return ['courseid' => get_string('invalidcourse', 'enrolcond_coursecompleted')];
        }
        return [];
    }

    /**
     * The course must be one the user may name ({@see base::can_reference_course()}).
     *
     * @param array $config
     * @param \context $context Course context.
     * @return string|null
     */
    #[\Override]
    public function check_access(array $config, \context $context): ?string {
        $courseid = (int) ($config['courseid'] ?? 0);
        if ($courseid > 0 && !self::can_reference_course($courseid)) {
            return get_string('noaccess', 'enrolcond_coursecompleted');
        }
        return null;
    }

    #[\Override]
    public function restore_config(array $config, bool $samesite): array {
        // Course ids of another site mean nothing here: the condition fails until it is edited.
        return $samesite ? $config : ['courseid' => 0];
    }

    #[\Override]
    public function get_observed_events(): array {
        return [\core\event\course_completed::class];
    }
}
