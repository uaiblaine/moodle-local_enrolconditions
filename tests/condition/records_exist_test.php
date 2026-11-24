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

namespace local_enrolconditions\condition;

/**
 * Tests of base::records_exist(), the one place that decides whether the records a configuration names exist.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(base::class)]
final class records_exist_test extends \advanced_testcase {
    /**
     * Calls the protected function.
     *
     * @param string $table
     * @param array $ids
     * @return bool
     */
    protected function exist(string $table, array $ids): bool {
        $method = new \ReflectionMethod(base::class, 'records_exist');
        return $method->invoke(null, $table, $ids);
    }

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        base::reset_existence_cache();
    }

    public function test_an_empty_list_does_not_exist(): void {
        $course = $this->getDataGenerator()->create_course();
        // Before anything is cached.
        $this->assertFalse($this->exist('course', []));
        // Control: a list with a record that exists.
        $this->assertTrue($this->exist('course', [(int) $course->id]));
        // After an answer is cached, the empty list is still not that answer.
        $this->assertFalse($this->exist('course', []));
        $this->assertTrue($this->exist('course', [(int) $course->id]));
    }

    public function test_ids_that_are_not_positive_or_missing_do_not_exist(): void {
        $first = $this->getDataGenerator()->create_course();
        $second = $this->getDataGenerator()->create_course();
        $ids = [(int) $first->id, (int) $second->id];
        $this->assertTrue($this->exist('course', $ids));
        $this->assertFalse($this->exist('course', [0]));
        $this->assertFalse($this->exist('course', [-1]));
        $this->assertFalse($this->exist('course', [(int) $first->id, 0]));
        $this->assertFalse($this->exist('course', [(int) $first->id, 99999999]));
        // The answers do not leak between lists: all existing is true again.
        $this->assertTrue($this->exist('course', $ids));
        $this->assertTrue($this->exist('course', [(int) $second->id, (int) $first->id, (int) $first->id]));
    }
}
