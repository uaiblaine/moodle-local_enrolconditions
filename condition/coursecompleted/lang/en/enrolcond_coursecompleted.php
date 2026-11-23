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
 * Strings for component 'enrolcond_coursecompleted', language 'en'.
 *
 * @package    enrolcond_coursecompleted
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['course'] = 'Course';
$string['course_help'] = 'The course the user must have completed to meet the condition.

* The condition is met once that course records the user\'s course completion. Course completion must be enabled, with criteria, in that course, or nobody ever meets it.
* Only courses you can see are offered and accepted.
* Marked "Must not be met", it lets in those who have not completed the course.
* If the course is deleted, or is restored from another site (it comes back with no course chosen here), the condition cannot be evaluated: it refuses new enrolments until a teacher chooses the course again, whether or not it is marked "Must not be met". Nobody already enrolled is suspended, removed or reactivated because of it.

**Example**: "Basic safety", so that only those who completed the safety course can enrol in the advanced one.

When "Check the conditions" includes "When something the conditions depend on changes", completing that course re-checks the user\'s enrolments shortly after.';
$string['desc'] = 'You have completed the course {$a}';
$string['descmissing'] = 'You have completed a course that no longer exists (id {$a})';
$string['invalidcourse'] = 'Choose a course.';
$string['missingcourse'] = 'The course of this condition no longer exists; a teacher must choose another one.';
$string['noaccess'] = 'You cannot use a course that you cannot see. Choose one of the courses offered.';
$string['pluginname'] = 'Course completed';
$string['privacy:metadata'] = 'The Course completed enrolment condition does not store any personal data.';
