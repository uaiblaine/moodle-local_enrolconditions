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
 * Strings for component 'enrolcond_profile', language 'en'.
 *
 * @package    enrolcond_profile
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['desc_contains'] = 'Your {$a->field} contains "{$a->value}"';
$string['desc_doesnotcontain'] = 'Your {$a->field} does not contain "{$a->value}"';
$string['desc_endswith'] = 'Your {$a->field} ends with "{$a->value}"';
$string['desc_isempty'] = 'Your {$a->field} is empty';
$string['desc_isequalto'] = 'Your {$a->field} is "{$a->value}"';
$string['desc_isnotempty'] = 'Your {$a->field} is not empty';
$string['desc_startswith'] = 'Your {$a->field} starts with "{$a->value}"';
$string['field'] = 'User profile field';
$string['field_help'] = 'The field of the user\'s profile that the condition compares with "Value", a standard field or a custom one.

* A field the user can edit in their own profile is self-declared: the user can change it to meet the condition. When the condition must be trusted, use a field that only administrators or an authentication method fill in, such as "ID number" or a locked custom field.
* The outcome of the condition tells the teacher whether a participant\'s field matches the value, even for a field the teacher cannot see in the profile.
* The comparison ignores upper and lower case and the spaces around the value.
* If a custom field is deleted, the condition cannot be evaluated: it refuses new enrolments until a teacher chooses another field, whether or not it is marked "Must not be met". Nobody already enrolled is suspended, removed or reactivated because of it.

**Example**: "Department", "is equal to", "Nursing", with the department filled in by the staff directory and locked, lets in the users of the Nursing department.

When "Check the conditions" includes "When something the conditions depend on changes", an update of the user\'s profile re-checks their enrolments shortly after.';
$string['invalidfield'] = 'Choose a profile field.';
$string['invalidoperator'] = 'Choose a comparison.';
$string['missingfield'] = 'The profile field of this condition no longer exists; a teacher must choose another one.';
$string['op_contains'] = 'contains';
$string['op_doesnotcontain'] = 'does not contain';
$string['op_endswith'] = 'ends with';
$string['op_isempty'] = 'is empty';
$string['op_isequalto'] = 'is equal to';
$string['op_isnotempty'] = 'is not empty';
$string['op_startswith'] = 'starts with';
$string['operator'] = 'Comparison';
$string['pluginname'] = 'User profile';
$string['privacy:metadata'] = 'The User profile enrolment condition does not store any personal data.';
$string['value'] = 'Value';
