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

namespace local_enrolconditions\fixtures;

use MoodleQuickForm;
use stdClass;

/**
 * Condition for unit tests: the user has an active enrolment in config['courseid'].
 *
 * It observes the user enrolment events, so two instances can depend on each other's enrolments.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class active_enrolment_condition extends \local_enrolconditions\condition\base {
    #[\Override]
    public function get_type(): string {
        return 'activeenrolment';
    }

    #[\Override]
    public function get_name(): string {
        return 'Active enrolment';
    }

    #[\Override]
    public function is_met(int $userid, stdClass $instance, array $config): bool {
        global $DB;
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND ue.userid = :userid AND ue.status = :active AND e.status = :enabled",
            [
                'courseid' => (int) ($config['courseid'] ?? 0),
                'userid' => $userid,
                'active' => ENROL_USER_ACTIVE,
                'enabled' => ENROL_INSTANCE_ENABLED,
            ]
        );
    }

    #[\Override]
    public function get_description(array $config, bool $met): string {
        return $met ? 'Enrolled' : 'Not enrolled';
    }

    #[\Override]
    public function get_observed_events(): array {
        return [
            \core\event\user_enrolment_created::class,
            \core\event\user_enrolment_updated::class,
            \core\event\user_enrolment_deleted::class,
        ];
    }

    #[\Override]
    public function config_form_definition(MoodleQuickForm $mform): void {
    }

    #[\Override]
    public function config_from_form_data(stdClass $data): array {
        return [];
    }

    #[\Override]
    public function validate_config(array $config): array {
        return [];
    }
}
