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

namespace local_enrolconditions\task;

use local_enrolconditions\local\enforcer;

/**
 * Re-checks the enrolments of the instances with the periodic check mode, in chunks, within a time budget.
 *
 * Each run continues the cycle where the previous one stopped (the position is in the plugin settings, shared by
 * every node) and stops when the budget (checktimelimit) is spent or the cycle is complete.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_conditions extends \core\task\scheduled_task {
    /** @var int Default chunk size. */
    public const DEFAULT_CHUNK = 500;

    /** @var int Default time budget of a run, in seconds. */
    public const DEFAULT_TIME_LIMIT = 60;

    #[\Override]
    public function get_name() {
        return get_string('checkconditionstask', 'local_enrolconditions');
    }

    #[\Override]
    public function execute() {
        $enforcer = new enforcer();
        if (!$enforcer->get_plugins()) {
            mtrace('No enabled enrolment method re-checks conditions.');
            return;
        }
        $chunk = (int) get_config('local_enrolconditions', 'checkbatchsize') ?: self::DEFAULT_CHUNK;
        $timelimit = get_config('local_enrolconditions', 'checktimelimit');
        $timelimit = ($timelimit === false || $timelimit === '') ? self::DEFAULT_TIME_LIMIT : max(0, (int) $timelimit);
        $outcomes = $enforcer->check_periodic($chunk, $timelimit, new \text_progress_trace());
        [$instanceid, $userid] = enforcer::get_position();
        $where = $instanceid ? "the next run continues at instance {$instanceid}, after user {$userid}" : 'the cycle is complete';
        mtrace(count($outcomes) . " enrolments checked; {$where}.");
    }
}
