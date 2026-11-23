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
 * Condition for unit tests whose every answer comes from its configuration.
 *
 * Configuration: met (bool), the result of is_met(); describethrows (bool), whether get_description() throws;
 * denied (string), the error check_access() returns. It counts the calls of is_met().
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scripted_condition extends \local_enrolconditions\condition\base {
    /** @var int Calls of is_met() since the last reset. */
    public static int $calls = 0;

    #[\Override]
    public function get_type(): string {
        return 'scripted';
    }

    #[\Override]
    public function get_name(): string {
        return 'Scripted condition';
    }

    #[\Override]
    public function is_met(int $userid, stdClass $instance, array $config): bool {
        self::$calls++;
        return !empty($config['met']);
    }

    #[\Override]
    public function get_description(array $config, bool $met): string {
        if (!empty($config['describethrows'])) {
            throw new \coding_exception('Cannot describe');
        }
        return $met ? 'Scripted met' : 'Scripted not met';
    }

    #[\Override]
    public function check_access(array $config, \context $context): ?string {
        return isset($config['denied']) ? (string) $config['denied'] : null;
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
