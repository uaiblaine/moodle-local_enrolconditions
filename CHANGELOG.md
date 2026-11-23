# Changelog

All notable changes to the Enrolment conditions plugin will be documented in this file.

## 1.2.0+uai.1 - 2026-11-23

### Added
- Setting *Time limit of a periodic check* (`checktimelimit`, default one minute): each run of the scheduled task re-checks chunks of *Enrolments per chunk* until the limit is spent or the cycle is complete.
- Scheduled task *Delete the condition records of enrolments that no longer exist* (daily), for deletions that fire no event.
- Ad hoc task `\local_enrolconditions\task\recheck_user`, which runs the re-checks on events.
- `tree::validate()` takes the course context and the stored tree: every condition that is new or changed is asked `base::check_access()`; conditions stored unchanged are not asked again.
- `tree::evaluate()` without explanations (`$explain = false`), which stops at the first child that decides a group.
- `enforcer::check_periodic()`, `enforcer::get_open_states()` and the outcomes `skipped` and `error`.

### Changed
- Re-checks on events no longer run in the request that raised the event: the observer only queues one ad hoc task per user (identical pending requests collapse), and changes made by these tasks queue further ones at most five levels deep, so two instances that depend on each other can no longer flip each other without end.
- The periodic check visits the instances by id and their enrolments by user id, so each query is served by the unique index without a sort; the position (`periodicposition`, "instance id,user id") replaces `periodiccursor`, which the upgrade deletes.
- The enforcer decides every change under a lock per user enrolment, on the row and the records read again under it, and writes the status and its record in one transaction. A check with nothing to change takes no lock.
- Only the enforcer's own change applied by core is recorded: a suspension someone else made first is never taken over, and a reactivation someone else made first closes the record without a *reactivated* event.
- The enforcer evaluates without explanations and builds the failure text only when it records a new action.
- The cache of observed events is purged only for enforceable methods and has no static acceleration; the state records are deleted on unenrolment only for enforceable methods.
- `enforcer::check_batch()` was replaced by `enforcer::check_periodic()`.

- `enrolcond_userfile`: every save of the condition dialog stores the file under a new item id, never a posted one, and the clean-up task finds the files in use through every enforceable method, not through `enrol_conditional`'s column.
- `enrolcond_userlist` and `enrolcond_userfile`: a restore from another site comes back without the list or the file, so the condition is unknown until a teacher supplies one.
- `enrolcond_userlist`: the privacy provider declares the typed usernames as configuration data of the enrolment instance; lookups use a keyed array.
- `enrolcond_sqlquery`: one cache entry per list when its query has no `:courseid`; one request refreshes an expired entry under a lock while the others serve the old one; an entry lives a day at most. The setting texts say that the keyword check is a guard against mistakes, not a security boundary, and that every teacher who can configure an instance can use every list.
- `enrolcond_profile`: the field help warns that a field the user can edit is self-declared and that the outcome tells the teacher whether a participant's field matches.
- Enabling or disabling a condition type purges the cache of observed events on every node.
- The tests of the condition subplugins live in the plugin's own suite (`tests/condition/`), the only one the plugin CI runs.
- Help texts rewritten (what the setting is, its options by label, when to use each one, an example); Brazilian Portuguese strings added for the plugin and every condition (kept out of the package).
- Fleet standards: literal string identifiers, no `html_writer`, `MOODLE_INTERNAL` guards where they belong, privacy compliance asserted for all nine components, a test that every condition's description is escaped, licence files, `uaiblaine/moodle-workflows` CI.

### Security
- A condition a teacher adds or changes may name only courses they can see and cohorts they may use in the course (`base::check_access()`); the condition dialog and the editor never show the name of anything else, and a condition already stored stays editable.
- The condition dialog asks the configuration capability of the enrolment method it is opened for, requires the instance to belong to the course and refuses disabled condition types.
- `enrolcond_userfile`: privacy requests find and erase users whose account was already deleted (matched through the MD5 of the username that core keeps), and drop the cached list of the old file content; a condition cannot reference a file another stored condition uses.

### Fixed
- The cohort, completed course, enrolled in a course and user profile conditions are unknown, not "not met", when the cohort, a course or the custom profile field they name was deleted, or after a restore from another site left the id at 0: re-checks no longer suspend or remove the participants, and a negated condition no longer lets everybody in. The existence of those records is looked up once per request.
- A condition type disabled by the administrator is unknown, as documented: it no longer suspends, removes or reactivates anybody.
- A condition whose description throws no longer escapes into the login; a generic text is shown instead.
- An enrolment whose check throws no longer stops the periodic check, a login or a task: it is reported and the next one goes on.
- An observer fault (including a PHP `Error`) no longer aborts the request that raised the event.
- Older duplicate open records of an enrolment are all closed together.

## 1.2.0 - 2026-11-21

### Added
- Condition `enrolcond_courseenrolled`: users enrolled in other courses (at least one or all of them, up to 50), optionally only active enrolments; re-checked on `user_enrolment_created`, `user_enrolment_updated` and `user_enrolment_deleted`.

## 1.1.0 - 2026-11-18

### Added
- Interface `\local_enrolconditions\enforceable`: any enrolment method that implements it gets the re-checks of existing enrolments (at login, in the scheduled task and on the events observed by the conditions), the action when the conditions are no longer met (nothing, suspend or remove) and the reactivation of the enrolments suspended by the enforcer.
- Enforcer `\local_enrolconditions\local\enforcer`, observers, scheduled task *Re-check the conditions of existing enrolments* and cache of observed events, moved from `enrol_conditional` and made generic.
- Table `local_enrolconditions_state`, with the enrolment method of each record, and its Privacy API provider.
- Events `user_enrolment_suspended`, `user_enrolment_reactivated` and `conditions_not_met`, with the enrolment method in `other['enrol']`.
- Form helper `\local_enrolconditions\output\enforcement_form` for the action, the reactivation and the check modes.
- Settings page *Re-checks* with *Enrolments per periodic check*.
- Documentation: *Using the engine from another enrolment method*.

## 1.0.1 - 2026-11-18

### Changed
- README and documentation point to the repository renamed to `moodle-by-kelsoncm/moodle-local_enrolconditions`.
- New documentation site; the README shows the plugin icon and screenshots.
- The documentation folder (`docs/`) stays out of the plugin ZIP.

### Fixed
- The PHPDoc of `base::get_events()` says that `enrol_conditional` observes every event, so condition plugins need no `db/events.php` for them.

## 1.0.0 - 2026-11-09

### Added
- Subplugin type `enrolcond` (folder `condition/`) with plugin info, enable/disable and settings pages.
- Base class `\local_enrolconditions\condition\base` and `\local_enrolconditions\condition\manager`.
- Hook `\local_enrolconditions\hook\collect_conditions`, so other plugins can provide conditions.
- Condition management page under *Site administration > Plugins > Enrolments*.
- Conditions `enrolcond_date` (period), `enrolcond_profile` (standard or custom profile field), `enrolcond_cohort` (cohort member, re-checked on cohort membership events) and `enrolcond_coursecompleted` (another course completed, re-checked on `course_completed`).
- Conditions `enrolcond_userlist` (usernames typed in the condition), `enrolcond_userfile` (CSV or text file kept in the course, replaceable, with a daily cleanup of unused files and a privacy provider) and `enrolcond_sqlquery` (lists defined by administrators as SELECT queries returning `username`, cached, never run inside an open transaction).
- A condition that throws an exception is unknown: it blocks new self enrolments and never suspends.
- `base::config_to_form_data_in_context()` and `base::config_after_submission()`, for conditions that keep data outside their configuration.
- `base::restore_config()` and `tree::for_restore()`: on restores from another site, the cohort and course completed conditions point to nothing and fail until edited.
