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
 * Tests of the task that deletes the state records of enrolments that no longer exist.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(purge_orphan_states::class)]
final class purge_orphan_states_test extends \advanced_testcase {
    /**
     * Inserts a state record.
     *
     * @param int $ueid
     * @param int $instanceid
     * @return int
     */
    protected function insert_state(int $ueid, int $instanceid): int {
        global $DB;
        return (int) $DB->insert_record(enforcer::TABLE, (object) [
            'enrol' => 'manual',
            'userenrolmentid' => $ueid,
            'instanceid' => $instanceid,
            'action' => enforcer::STATE_SUSPEND,
            'reason' => '',
            'timesuspended' => time(),
            'uetimemodified' => 0,
        ]);
    }

    public function test_deletes_only_orphans(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $ue = $DB->get_record_sql(
            'SELECT ue.* FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = ? AND ue.userid = ?',
            [$course->id, $user->id],
            MUST_EXIST
        );
        $kept = $this->insert_state((int) $ue->id, (int) $ue->enrolid);
        $orphan = $this->insert_state((int) $DB->get_field_sql('SELECT MAX(id) FROM {user_enrolments}') + 1000, (int) $ue->enrolid);

        (new purge_orphan_states())->execute();

        $this->assertFalse($DB->record_exists(enforcer::TABLE, ['id' => $orphan]));
        $this->assertTrue($DB->record_exists(enforcer::TABLE, ['id' => $kept]));
        $this->assertNotEmpty((new purge_orphan_states())->get_name());
    }
}
