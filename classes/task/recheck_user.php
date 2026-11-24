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

use local_enrolconditions\enforceable;
use local_enrolconditions\local\enforcer;
use local_enrolconditions\observer;

/**
 * Re-checks the enrolments of one user in the instances that re-check on events.
 *
 * Queued by observer::any_event() when an event observed by the conditions happens, so the request that raised
 * the event evaluates and writes nothing. Identical pending requests (same user, same depth) collapse into one.
 * Events raised while the task works queue the next task one level deeper; beyond MAX_DEPTH nothing more is
 * queued, which bounds instances whose conditions keep changing each other. Custom data: userid, depth, slot.
 *
 * The slot keeps an event from being lost while the user's task runs: core's duplicate check matches a task of the
 * same identity that is already running, so an event that arrives after the task read the enrolments would queue
 * nothing. queue() takes the first slot whose task is not running, so a pending task still absorbs every new event
 * and a running one never does.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recheck_user extends \core\task\adhoc_task {
    /** @var int Deepest task queued from the events of another task; the first one has depth 0. */
    public const MAX_DEPTH = 5;

    /** @var int Tasks of one user and depth that may exist at once, running or pending, one per slot. */
    public const SLOTS = 3;

    /**
     * Queues a re-check of a user, unless an identical one is pending.
     *
     * When every slot runs a task at once (three workers re-checking one user), the event is left to the last
     * slot's running task; the login and periodic checks still see the change.
     *
     * @param int $userid
     * @param int $depth 0 for an event outside a re-check; the running task's depth plus one inside one.
     * @return bool False when the depth is beyond MAX_DEPTH and nothing was queued.
     */
    public static function queue(int $userid, int $depth): bool {
        if ($depth > self::MAX_DEPTH) {
            return false;
        }
        for ($slot = 0; $slot < self::SLOTS; $slot++) {
            $task = new self();
            $task->set_component('local_enrolconditions');
            $task->set_custom_data(['userid' => $userid, 'depth' => max(0, $depth), 'slot' => $slot]);
            if (!self::is_running($task)) {
                break;
            }
        }
        \core\task\manager::queue_adhoc_task($task, true);
        return true;
    }

    /**
     * Whether a task of the same identity (class and custom data) is running now.
     *
     * @param self $task
     * @return bool
     */
    protected static function is_running(self $task): bool {
        global $DB;
        $record = \core\task\manager::record_from_adhoc_task($task);
        return $DB->record_exists_select(
            'task_adhoc',
            'classname = :classname AND component = :component AND '
                . $DB->sql_compare_text('customdata', \core_text::strlen($record->customdata) + 1) . ' = :customdata'
                . ' AND timestarted IS NOT NULL',
            ['classname' => $record->classname, 'component' => $record->component, 'customdata' => $record->customdata]
        );
    }

    #[\Override]
    public function get_name() {
        return get_string('rechecktask', 'local_enrolconditions');
    }

    #[\Override]
    public function execute() {
        $data = $this->get_custom_data();
        $userid = (int) ($data->userid ?? 0);
        $depth = min(self::MAX_DEPTH, max(0, (int) ($data->depth ?? 0)));
        $user = $userid > 0 ? \core_user::get_user($userid, 'id, deleted') : false;
        if (!$user || $user->deleted) {
            return;
        }
        $enforcer = new enforcer();
        if (!$enforcer->get_plugins()) {
            return;
        }
        $trace = new \text_progress_trace();
        $limitreached = observer::during_recheck($depth, function () use ($enforcer, $userid, $trace): void {
            $enforcer->check_user_in_mode($userid, enforceable::CHECK_EVENTS, null, $trace);
        });
        if ($limitreached) {
            mtrace("Re-check depth limit (" . self::MAX_DEPTH . ") reached for user {$userid}: the changes made now "
                . 'are not re-checked on events; the login and periodic checks still see them.');
        }
    }
}
