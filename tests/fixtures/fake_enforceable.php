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

/**
 * Manual enrolments that the enforcer re-checks, for tests.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/manual/lib.php');

/**
 * Manual enrolment method that keeps a condition tree in customtext1, the check modes in customint7 and the action
 * in customint8 (plus 16 to reactivate). It lets the enforcer select instances by loading them (no mode SQL).
 * customchar1 'throw' makes get_enforcement_action() throw, 'throwtree' makes get_enforcement_tree() throw an
 * \Error, for the tests of the error paths.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_enrolconditions_fake_enforceable extends enrol_manual_plugin implements \local_enrolconditions\enforceable {
    #[\Override]
    public function get_name() {
        return 'manual';
    }

    #[\Override]
    public function get_enforcement_tree(stdClass $instance): ?string {
        if (($instance->customchar1 ?? '') === 'throwtree') {
            throw new \TypeError('Broken enrolment method');
        }
        return $instance->customtext1 ?? null;
    }

    #[\Override]
    public function get_enforcement_check_modes(stdClass $instance): int {
        return (int) $instance->customint7 | self::CHECK_ENROL;
    }

    #[\Override]
    public function get_enforcement_action(stdClass $instance): int {
        if (($instance->customchar1 ?? '') === 'throw') {
            throw new \moodle_exception('error');
        }
        return (int) $instance->customint8 & 15;
    }

    #[\Override]
    public function get_enforcement_reactivate(stdClass $instance): bool {
        return (bool) ((int) $instance->customint8 & 16);
    }

    #[\Override]
    public function get_enforcement_mode_sql(int $mode): ?array {
        return null;
    }
}
