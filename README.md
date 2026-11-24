<img src="docs/img/icon.svg" alt="Enrolment conditions" width="96" align="right">

moodle-local_enrolconditions
============================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-local_enrolconditions/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-local_enrolconditions/actions/workflows/ci.yml?query=branch%3Amain)

The condition engine of [`enrol_conditional`](https://github.com/uaiblaine/moodle-enrol_conditional): pluggable conditions, combined with AND, OR and NOT, that decide who may enrol and whether an existing enrolment still holds.

This plugin declares the `enrolcond` subplugin type (folder `condition/`), the base class `\local_enrolconditions\condition\base` and the hook `\local_enrolconditions\hook\collect_conditions`, through which any other plugin can provide conditions too. It also re-checks existing enrolments (at login, periodically and on events) and suspends, removes or reactivates them, for `enrol_conditional` and for any other enrolment method that implements `\local_enrolconditions\enforceable`. It is a local plugin because Moodle accepts subplugins only under `mod`, `editor`, `tool` and `local` plugins.

Documentation: <https://moodle-by-kelsoncm.github.io/moodle-local_enrolconditions/> (this engine) and <https://moodle-by-kelsoncm.github.io/moodle-enrol_conditional/> (the enrolment method).

This repository is a fork of [moodle-by-kelsoncm/moodle-local_enrolconditions](https://github.com/moodle-by-kelsoncm/moodle-local_enrolconditions).


Requirements
------------

- Moodle 5.3 LTS or later (`$plugin->requires` 2026100200; tested on Moodle 5.3).
- No other plugin is required. The enrolment method [`enrol_conditional`](https://github.com/uaiblaine/moodle-enrol_conditional) is the engine's main client and requires this plugin, not the other way round.


Installation
------------

Install the plugin like any other plugin to folder `/local/enrolconditions`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.

The eight condition subplugins ship inside this plugin, in `condition/`, and are installed with it. After installation there is no step to run: the conditions work as soon as an enrolment method uses them. Administrators enable and disable condition types in *Site administration > Plugins > Enrolments > Enrolment conditions > Manage conditions*.


Conditions included
-------------------

| Subplugin | Met by |
|---|---|
| `enrolcond_date` | the period between two dates |
| `enrolcond_profile` | a standard or custom profile field compared with a value |
| `enrolcond_cohort` | the members of a cohort |
| `enrolcond_coursecompleted` | the users who completed another course |
| `enrolcond_courseenrolled` | the users enrolled in one or all of other courses |
| `enrolcond_userlist` | the usernames typed in the condition (up to 5,000) |
| `enrolcond_userfile` | the usernames of a CSV or text file kept in the course (up to 200,000) |
| `enrolcond_sqlquery` | the usernames returned by a query defined by an administrator |

![Condition editor](docs/img/course-conditions-editor.png)

![Adding a User profile condition](docs/img/condition-profile.png)

![Manage conditions](docs/img/admin-manageconditions.png)

![List from a query](docs/img/admin-querylists.png)


Writing a condition
-------------------

Extend `\local_enrolconditions\condition\base` and implement `is_met()`, `get_description()`, `config_form_definition()`, `config_from_form_data()` and `validate_config()`. Optional methods: `config_to_form_data()`, `config_form_validation()`, `get_observed_events()` (events after which enrolments are checked again), `restore_config()` (ids that do not survive a restore on another site) and `config_after_submission()` (data kept outside the configuration, such as files).

- As a subplugin: `condition/<name>/classes/condition.php`, class `enrolcond_<name>\condition`.
- From any plugin: register a callback for `\local_enrolconditions\hook\collect_conditions` in `db/hooks.php` and call `$hook->add_condition(new \<component>\my_condition())`. The type returned by `get_type()` must start with the component.

`is_met()` may throw an exception when it cannot decide: the condition is then *unknown*, which blocks new enrolments but never suspends anybody.


Capabilities
------------

This plugin declares no capability. Who may edit the conditions of an enrolment instance is decided by the enrolment method that stores them (for `enrol_conditional`, `enrol/conditional:config`).


Settings
--------

Under *Site administration > Plugins > Enrolments > Enrolment conditions*:

- `local_enrolconditions/checkbatchsize` (default 500): the chunk size, how many enrolments the scheduled task reads at a time.
- `local_enrolconditions/checktimelimit` (default 60 seconds): how long one run of the scheduled task may work before it stops and resumes where it left off.
- The enabled flag of each condition type, set in *Manage conditions*.

*List from a query* (`enrolcond_sqlquery`), a page of its own under the same folder:

- `enrolcond_sqlquery/slots`: how many query slots the page shows.
- `enrolcond_sqlquery/cachettl` (default 5 minutes): how long the usernames a query returned are cached.
- `enrolcond_sqlquery/query<N>name` and `enrolcond_sqlquery/query<N>sql`, for each slot: the name teachers see and the SQL the administrator defines. The query runs as a read-only `SELECT` that returns usernames.


Scheduled tasks and caches
--------------------------

- `\local_enrolconditions\task\check_conditions`: runs at a random minute of every hour, re-checks the enrolments of the instances that use the periodic mode and applies the action chosen on each instance. It works instance by instance and user by user within its time limit, and keeps its position in the plugin setting `periodicposition`.
- `\local_enrolconditions\task\purge_orphan_states`: runs once a day, deletes the state records whose user enrolment or enrolment instance no longer exists.
- Adhoc task `\local_enrolconditions\task\recheck_user`: queued by the observer, one per user, when an event of the "events" check mode fires; the enrolment changes when this task runs, not in the request that fired the event. A task queued by a task goes one level deeper, up to 5, so two instances that depend on each other cannot loop.
- Locks: the enforcer takes a lock per user enrolment through `\core\lock\lock_config` before it records or reverts a suspension. A cluster needs a lock factory shared by every node, which core's default (database) factory is.
- `\enrolcond_userfile\task\cleanup_files`: runs once a day, deletes the username files that no condition refers to any more (files younger than a day are kept).
- Cache `local_enrolconditions/observedevents` (application, time to live one hour as a backstop): the events observed by the instances that re-check on events; purged when an instance changes.
- Cache `enrolcond_userfile/usernames` (application): the usernames of a file, by its content hash.
- Cache `enrolcond_sqlquery/results` (application): the usernames a query returned, for the time set above.


Data stored
-----------

**Table `local_enrolconditions_state`** records what the enforcer did when the conditions of an enrolment stopped being met: the enrolment method, the user enrolment and instance ids, the action (recorded only, or suspended), the conditions that were not met as plain text, and when it suspended, reactivated and closed the record. Only enrolments suspended by the enforcer and recorded here are ever reactivated by it. A record is kept while the enrolment exists and is removed when the user enrolment or the enrolment instance is deleted; a privacy deletion request removes it as well. It is linked to the user through the enrolment, so it is personal data and is exported and deleted through the Privacy API.

**Usernames kept by two condition types.** `enrolcond_userlist` keeps the usernames a teacher typed inside the condition, in the enrolment instance; `enrolcond_userfile` keeps the file a teacher uploaded in the course's file area (`enrolcond_userfile`, `usernames`). `enrolcond_sqlquery` keeps the SQL an administrator defined in the plugin settings, and caches the usernames it returns. These lists name people: the privacy providers of both condition types find, export and erase them. A deletion request removes the user's username from the files and, for typed lists, from the tree of the instance, which the engine writes back through the optional interface `\local_enrolconditions\tree_writer` that an enrolment method implements (`enrol_conditional` does); a method that does not implement it keeps its trees as they are.

**Course backups.** The usernames typed or uploaded are part of the condition tree of the enrolment instance, so a course backup that includes the enrolment methods carries them, and a restore writes them to the target site. The enrolment method adds the open state records to the backup when the backup includes users.

**External services.** The plugin sends no data to any external service.


Troubleshooting
---------------

- *A condition type is missing from the editor*: it is disabled or not installed; enable it in *Manage conditions*. An installed tree that uses an uninstalled type never suspends anybody.
- *Enrolments are not re-checked*: the instance must have a re-check mode turned on, the enrolment method must be enabled, and cron must be running.


Credits
-------

Created by Kelson Medeiros ([moodle-by-kelsoncm](https://github.com/moodle-by-kelsoncm)); this repository is a fork that keeps upstream's code and attribution. See `AUTHORS.md` upstream for the full list of contributors.

Issues of the upstream project: <https://github.com/moodle-by-kelsoncm/moodle-local_enrolconditions/issues>


Translations
------------

The English strings ship in the package. The Brazilian Portuguese translation is maintained in this repository (`lang/pt_br`) and reaches sites through [AMOS](https://lang.moodle.org/), as the plugins directory requires; it is left out of the release package.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Kelson Medeiros (upstream); changes in this fork: 2026 Anderson Blaine
