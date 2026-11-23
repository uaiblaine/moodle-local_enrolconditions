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
 * queued, which bounds instances whose conditions keep changing each other. Custom data: userid, depth.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recheck_user extends \core\task\adhoc_task {
    /** @var int Deepest task queued from the events of another task; the first one has depth 0. */
    public const MAX_DEPTH = 5;

    /**
     * Queues a re-check of a user, unless an identical one is pending.
     *
     * @param int $userid
     * @param int $depth 0 for an event outside a re-check; the running task's depth plus one inside one.
     * @return bool False when the depth is beyond MAX_DEPTH and nothing was queued.
     */
    public static function queue(int $userid, int $depth): bool {
        if ($depth > self::MAX_DEPTH) {
            return false;
        }
        $task = new self();
        $task->set_component('local_enrolconditions');
        $task->set_custom_data(['userid' => $userid, 'depth' => max(0, $depth)]);
        \core\task\manager::queue_adhoc_task($task, true);
        return true;
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
