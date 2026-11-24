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
 * Strings for component 'enrolcond_date', language 'en'.
 *
 * @package    enrolcond_date
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['descfrom'] = 'From {$a}';
$string['descfromto'] = 'From {$a->from} until {$a->to}';
$string['descto'] = 'Until {$a}';
$string['from'] = 'From';
$string['from_help'] = 'The moment from which the condition is met.

* Before it nobody meets the condition; from it on everybody does, until "Until" when that is set.
* Left disabled, the condition has no start, and "Until" must be set.
* Marked "Must not be met", it works the other way round: the condition is met only before this moment.

**Example**: 1 February 2027, 08:00, to open enrolment on the first day of the enrolment week.

To open enrolment for everybody at once, the method\'s own "Start date" is simpler, and also hides the "Enrol me" link until then. A "Date" condition helps inside a group, for example to let one cohort in a week before the others.';
$string['pluginname'] = 'Date';
$string['privacy:metadata'] = 'The Date enrolment condition does not store any personal data.';
$string['required'] = 'Set a start date, an end date or both.';
$string['to'] = 'Until';
$string['to_help'] = 'The moment from which the condition is no longer met.

* The condition is met until this moment and not from it on.
* Left disabled, the condition has no end, and "From" must be set; when both are set, "From" must come first.
* Users enrolled before it keep their enrolment, unless "Check the conditions" re-checks them when they log in or periodically: then the first such re-check after this moment applies "When the conditions are no longer met". A date passing is not a change that the re-check on changes reacts to.

**Example**: 30 June 2027, 23:59, with the periodic re-check and "Suspend the enrolment", for a course funded until the end of June: enrolment closes then, and those enrolled are suspended in the following hours.';
$string['toafterfrom'] = 'The end date must be after the start date.';
