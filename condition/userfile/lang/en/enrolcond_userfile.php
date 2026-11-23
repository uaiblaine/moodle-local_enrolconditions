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
 * Strings for component 'enrolcond_userfile', language 'en'.
 *
 * @package    enrolcond_userfile
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cachedef_usernames'] = 'Usernames read from the files of usernames';
$string['cleanupfilestask'] = 'Delete files of usernames no longer used by any condition';
$string['desc'] = 'Your username is in the file {$a->filename} ({$a->count} users)';
$string['empty'] = 'The file has no usernames.';
$string['file'] = 'File of usernames';
$string['file_help'] = 'A file with the usernames of the users who meet the condition.

* A CSV or text file with one username per line, in the first column, up to 200000 usernames. A first line "username" is read as a header. Upper and lower case are the same.
* The file is kept in the course, with the condition: to change the list, upload a new file here. Editing the original on your computer changes nothing.
* It travels in the course backups that include the enrolment methods. A restore on another site comes back without it: the condition then cannot be evaluated, and refuses new enrolments, until a teacher uploads the file again.
* A condition whose file is missing never suspends, removes or reactivates anybody already enrolled.

**Example**: "nursing-2027.csv", exported from the admissions system, with the usernames of the students admitted this year.

A username in the file that has no account yet is accepted: that user can enrol once the account exists. For a few users, the "List of usernames" condition is quicker.';
$string['filemissing'] = 'The file of usernames of this condition no longer exists.';
$string['invalidusernames'] = 'These are not valid usernames: {$a}';
$string['pluginname'] = 'File of usernames';
$string['privacy:metadata:core_files'] = 'Files of usernames uploaded by teachers, which list the users who meet the condition.';
$string['toomany'] = 'A file can have at most {$a} usernames.';
