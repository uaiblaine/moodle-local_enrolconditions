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
 * Strings for local_enrolconditions.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cachedef_observedevents'] = 'Events observed by the conditions of the enrolment instances that re-check on events';
$string['checkbatchsize'] = 'Enrolments per chunk';
$string['checkbatchsize_desc'] = 'Number of enrolments the scheduled task reads and re-checks at a time. Each run checks chunks until its time limit is spent, and the next run continues where it stopped.';
$string['checkconditionstask'] = 'Re-check the conditions of existing enrolments';
$string['checkmodeenrol'] = 'When the user enrols (always)';
$string['checkmodeevents'] = 'When something the conditions depend on changes';
$string['checkmodelogin'] = 'When the user logs in';
$string['checkmodeperiodic'] = 'Periodically, in the scheduled task';
$string['checkmodes'] = 'Check the conditions';
$string['checkmodes_help'] = 'When the conditions are checked. They are always checked when a user enrols; the enrolments that already exist are checked again only at the moments ticked here.

* **When the user enrols (always)**: a user who does not meet the conditions cannot enrol. It cannot be turned off.
* **When the user logs in**: the user\'s enrolments through this method are checked again while they log in.
* **Periodically, in the scheduled task**: the task "Re-check the conditions of existing enrolments", once an hour by default, works through the enrolments in turns, for a limited time per run. On a large site a full round takes several runs; "Time limit of a periodic check" and "Enrolments per chunk", in the site administration, decide how many are checked per run.
* **When something the conditions depend on changes**: the user\'s enrolments are checked again in the background shortly after the change, not in the request that made it. Only four conditions react: "User profile" (the profile is updated), "Cohort" (a member is added or removed), "Course completed" (the course is completed) and "Enrolled in a course" (an enrolment is created, changed or removed). A date passing, or a list or a file of usernames changing, does not.

**When to use each one**

* Conditions on cohorts, profile fields or other courses, and a user must lose access soon after leaving the cohort: when something the conditions depend on changes.
* A "Date" condition with an end, or a "List from a query" whose data changes: periodically, in the scheduled task, or when the user logs in.
* The conditions only filter who comes in, and nobody already enrolled is ever touched: leave the other boxes unticked.

**Example**: a "Cohort" condition with "Interns 2027", with when something the conditions depend on changes and periodically ticked: an intern removed from the cohort is suspended shortly after, and the periodic task also catches the changes that raise no event.

Changing the conditions checks nobody at once: the enrolments that exist are checked against the new conditions at the next of the moments ticked here. What a failed check does is set in "When the conditions are no longer met".';
$string['checktimelimit'] = 'Time limit of a periodic check';
$string['checktimelimit_desc'] = 'How long each run of the scheduled task keeps re-checking enrolments. No new chunk starts after this time, so a run always checks at least one chunk; it stops earlier when it has gone through every enrolment, and the next run continues where it stopped.';
$string['conditionaction'] = 'When the conditions are no longer met';
$string['conditionaction_help'] = 'What happens to an enrolment through this method when a re-check finds that the user no longer meets the conditions.

* **Nothing (only record it)**: the enrolment stays as it is. The failure is recorded in the logs once, and again only after the user has met the conditions in between.
* **Suspend the enrolment**: the user can no longer enter the course, and their work in it is kept. With "Reactivate when the conditions are met again" ticked, the enrolment comes back by itself. An enrolment someone else had already suspended is left as it is.
* **Remove the enrolment**: the user is unenrolled, and some of their data in the course, such as group memberships, is deleted. The conditions never bring it back.

**When to use each one**

* You want to see who no longer qualifies before anything happens to them: nothing (only record it).
* Access follows the conditions, and a user who qualifies again must find their work where they left it: suspend the enrolment.
* Places are limited, and whoever stops qualifying must free theirs: remove the enrolment.

**Example**: suspend the enrolment, with "Reactivate when the conditions are met again" ticked, for a course open to the members of a cohort: a member removed from the cohort loses access, and gets it back with their work after rejoining.

No effect unless "Check the conditions" has a box ticked besides "When the user enrols (always)". When the result depends on a condition that cannot be evaluated (a disabled or uninstalled condition type, a missing list or file, a list that failed to load), the enrolment never changes. Changing this setting does not undo what was already done.';
$string['conditionactionnone'] = 'Nothing (only record it)';
$string['conditionactionremove'] = 'Remove the enrolment';
$string['conditionactionsuspend'] = 'Suspend the enrolment';
$string['conditiondisabled'] = 'Condition "{$a}" disabled.';
$string['conditionenabled'] = 'Condition "{$a}" enabled.';
$string['conditionnotinstalled'] = 'The condition "{$a}" is not installed.';
$string['conditionreactivate'] = 'Reactivate when the conditions are met again';
$string['conditionreactivate_help'] = 'Whether an enrolment that the conditions suspended comes back by itself when the user meets the conditions again.

* Only the suspensions this method made are lifted. An enrolment a teacher, another plugin or an expiry suspended stays suspended.
* A suspension is no longer lifted once someone else changes that enrolment, for example by reactivating it or changing it directly in the database. Changing only its dates, while it stays suspended, keeps it.
* It happens at a re-check, at the moments ticked in "Check the conditions", never at once.
* Removing all the conditions counts as meeting them: the suspensions they made are lifted at the next re-check.
* Ticking it later also lifts the suspensions made while it was unticked.

**Example**: ticked, with "Suspend the enrolment", for a course open to the "Nursing" department: a user who moves to another department is suspended, and gets their access and their work back on returning to Nursing.

It only matters with "Suspend the enrolment": an enrolment that was removed never comes back.';
$string['editoraddcondition'] = 'Add condition';
$string['editoraddgroup'] = 'Add group';
$string['editorall'] = 'All of these';
$string['editorany'] = 'Any of these';
$string['editorchoosetype'] = 'Condition type';
$string['editoredit'] = 'Edit';
$string['editorempty'] = 'No conditions.';
$string['editornoscript'] = 'The condition editor needs JavaScript. The saved conditions are kept unchanged.';
$string['editornot'] = 'Must not be met';
$string['editornotavailable'] = 'This condition refers to something you cannot use in this course.';
$string['editoroperator'] = 'How to combine the items of this group';
$string['editorremove'] = 'Remove';
$string['editorremovegroup'] = 'Remove group';
$string['editorunknowntype'] = 'The condition type "{$a}" is not installed. Self enrolment is refused while it is in use.';
$string['eventconditionsnotmet'] = 'Enrolment conditions no longer met';
$string['eventuserenrolmentreactivated'] = 'User enrolment reactivated by conditions';
$string['eventuserenrolmentsuspended'] = 'User enrolment suspended by conditions';
$string['failuredescription'] = 'A condition is not met.';
$string['failurenot'] = 'You must not meet this condition: {$a}';
$string['failurenotgroup'] = 'You meet a group of conditions that must not be met.';
$string['failureoneof'] = 'You must meet at least one of the following:';
$string['failureunknown'] = 'A condition can no longer be checked. Contact the course teacher.';
$string['manageconditions'] = 'Manage conditions';
$string['pluginname'] = 'Enrolment conditions';
$string['privacy:metadata:state'] = 'Actions taken when the conditions of an enrolment were no longer met. Enrolments are stored by core.';
$string['privacy:metadata:state:action'] = 'Action taken: none (only recorded) or suspend.';
$string['privacy:metadata:state:closereason'] = 'Why the record stopped applying.';
$string['privacy:metadata:state:enrol'] = 'The enrolment method.';
$string['privacy:metadata:state:reason'] = 'Conditions that were not met.';
$string['privacy:metadata:state:timeclosed'] = 'When the record stopped applying.';
$string['privacy:metadata:state:timereactivated'] = 'When the enrolment was reactivated.';
$string['privacy:metadata:state:timesuspended'] = 'When the action was taken.';
$string['privacy:metadata:state:userenrolmentid'] = 'The enrolment of the user.';
$string['privacy:path:state'] = 'Condition actions';
$string['purgeorphanstatestask'] = 'Delete the condition records of enrolments that no longer exist';
$string['rechecktask'] = 'Re-check the enrolments of a user after a change the conditions depend on';
$string['settings'] = 'Re-checks';
$string['subplugintype_enrolcond'] = 'Condition';
$string['subplugintype_enrolcond_plural'] = 'Conditions';
$string['treebadnode'] = 'Invalid node at position {$a}.';
$string['treeconditionerror'] = '{$a->name}: {$a->error}';
$string['treeinvalid'] = 'Invalid condition tree: {$a}';
$string['treeinvalidjson'] = 'The conditions are not valid JSON.';
$string['treeroot'] = 'root';
$string['treerootnotgroup'] = 'The root of the conditions must be a group.';
$string['treetoodeep'] = 'Groups can be nested at most {$a} levels deep.';
$string['treetoomanynodes'] = 'At most {$a} conditions and groups are allowed.';
$string['treeunknowntype'] = 'The condition type "{$a}" is not available.';
