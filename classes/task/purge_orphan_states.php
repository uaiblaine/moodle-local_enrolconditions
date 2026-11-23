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
 * Deletes the state records of user enrolments that no longer exist.
 *
 * The observer of user_enrolment_deleted removes them when core fires the event; deletions that fire none
 * (the uninstallation of an enrolment method, a course deleted while the method is disabled) leave rows nothing
 * else reaches. One set-based statement, idempotent, safe on any node.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_orphan_states extends \core\task\scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('purgeorphanstatestask', 'local_enrolconditions');
    }

    #[\Override]
    public function execute() {
        global $DB;
        $table = enforcer::TABLE;
        $DB->delete_records_select(
            $table,
            "NOT EXISTS (SELECT 1 FROM {user_enrolments} ue WHERE ue.id = {{$table}}.userenrolmentid)"
        );
    }
}
