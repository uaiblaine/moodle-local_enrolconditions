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
 * Strings for component 'enrolcond_userlist', language 'en'.
 *
 * @package    enrolcond_userlist
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['desc'] = 'Your username is in a list of {$a} users';
$string['emptylist'] = 'The list of usernames is empty; a teacher must type it again.';
$string['invalidusernames'] = 'These are not valid usernames: {$a}';
$string['pluginname'] = 'List of usernames';
$string['privacy:metadata:enrol'] = 'The enrolment instance keeps its conditions in the enrol table, as configuration data of the enrolment method.';
$string['privacy:metadata:enrol:usernames'] = 'Usernames typed by a teacher in a List of usernames condition. They are configuration data of the enrolment instance, kept until the condition or the instance is removed. A listed user\'s name is exported on request and erased on request when the enrolment method can store the changed list.';
$string['toomany'] = 'A list can have at most {$a} usernames. Use a file of usernames for longer lists.';
$string['usernames'] = 'Usernames';
$string['usernames_help'] = 'The usernames of the users who meet the condition, typed here.

* One username per line, or separated by spaces, commas or semicolons, up to 5000. Upper and lower case are the same.
* A username that has no account yet is accepted: that user can enrol once the account exists.
* The list is kept in the course, with the conditions of this enrolment method, and travels in the course backups that include the enrolment methods. A restore on another site comes back without it: the condition then cannot be evaluated, and refuses new enrolments, until a teacher types the list again. Nobody already enrolled is suspended, removed or reactivated because of it.

**Example**: "ana.souza, bruno.lima, carla.reis", for a pilot run with three invited users.

For longer lists, or a list exported from another system, the "File of usernames" condition is easier to keep up to date.';
