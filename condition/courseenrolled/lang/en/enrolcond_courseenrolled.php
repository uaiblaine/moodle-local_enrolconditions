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
 * Strings for component 'enrolcond_courseenrolled', language 'en'.
 *
 * @package    enrolcond_courseenrolled
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['active'] = 'Only active enrolments';
$string['active_help'] = 'Whether only an enrolment that gives access to the course right now counts.

* Ticked (the default): the enrolment must not be suspended, its enrolment method must be enabled, and it must be within its start and end dates.
* Unticked: any enrolment in the course counts, suspended, not yet started or expired, as long as it exists.

**Example**: unticked, for a follow-up course open to everybody who was ever enrolled in the original course and was not unenrolled from it.

A start or end date of those enrolments passing re-checks nobody by itself: the change is seen at the next login or periodic re-check, if "Check the conditions" includes them.';
$string['courseids'] = 'Courses';
$string['courseids_help'] = 'The courses in which the user must be enrolled to meet the condition, up to 50.

* An enrolment through any enrolment method counts, except one through this same enrolment method.
* "Required enrolments" decides whether one of the courses is enough or every one is needed, and "Only active enrolments" whether a suspended or expired enrolment counts.
* Only courses you can see are offered and accepted.
* If any of the courses is deleted, or the courses are restored from another site (they come back with no course chosen here), the condition cannot be evaluated, in either "Required enrolments" option: it refuses new enrolments until a teacher chooses the courses again, whether or not it is marked "Must not be met". Nobody already enrolled is suspended, removed or reactivated because of it.

**Example**: "Algebra I" and "Geometry I", with "Required enrolments" set to "All of the courses", for a revision workshop open to those taking both.

When "Check the conditions" includes "When something the conditions depend on changes", an enrolment created, changed or removed in those courses re-checks the user\'s enrolments shortly after.';
$string['descall'] = 'You are enrolled in all of these courses: {$a}';
$string['descany'] = 'You are enrolled in at least one of these courses: {$a}';
$string['descmissing'] = 'course that no longer exists (id {$a})';
$string['descone'] = 'You are enrolled in the course {$a}';
$string['invalidcourse'] = 'Choose one or more courses.';
$string['missingcourse'] = 'A course of this condition no longer exists; a teacher must choose the courses again.';
$string['mode'] = 'Required enrolments';
$string['mode_help'] = 'In how many of the chosen "Courses" the user must be enrolled.

* **At least one of the courses**: an enrolment in any one of them is enough.
* **All of the courses**: the user must be enrolled in every one of them.

**When to use each one**

* Several classes of the same prerequisite course, and any of them qualifies: at least one of the courses.
* A final project open only to those taking every module of the programme: all of the courses.

**Example**: "Python - class A" and "Python - class B", with at least one of the courses, for a lab open to the students of either class.

With a single course, both options are the same.';
$string['modeall'] = 'All of the courses';
$string['modeany'] = 'At least one of the courses';
$string['noaccess'] = 'You cannot use a course that you cannot see. Choose courses among those offered.';
$string['pluginname'] = 'Enrolled in a course';
$string['privacy:metadata'] = 'The Enrolled in a course enrolment condition does not store any personal data.';
$string['toomany'] = 'Choose at most {$a} courses.';
