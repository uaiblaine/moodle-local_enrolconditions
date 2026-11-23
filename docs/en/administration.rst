Administration
==============

Managing the condition types
----------------------------

*Site administration > Plugins > Enrolments > Enrolment conditions > Manage conditions* lists the installed condition types, with their version, an *Enabled* switch, a link to their settings (when they have any) and *Uninstall*.

.. figure:: ../img/admin-manageconditions.png
   :alt: Manage conditions page with the seven condition types enabled.
   :width: 100%

   The installed condition types.

A disabled type is no longer offered to teachers. Enrolment methods that already use it keep it in their trees, but the condition can no longer be checked: its result is *unknown*. ``enrol_conditional`` then refuses new self enrolments, and the re-checks never suspend, remove or reactivate anybody because of it.

Lists from a query
------------------

The *List from a query* condition only offers the lists that an administrator defines in its settings (*Manage conditions*, then *Settings* of *List from a query*):

Number of lists
   How many lists can be defined (default 3, at most 20).

Keep results for
   How long the usernames returned by a query are reused before it runs again (default 5 minutes; 0 runs it on every check).

Name and Query
   For each list. The name is shown to teachers and to users who do not meet the condition; an empty name disables the list. The query is a single ``SELECT`` (or ``WITH ... SELECT``) that returns a column named ``username``. Use ``{table}`` for table names; ``:courseid`` is the course of the enrolment method.

.. figure:: ../img/admin-querylists.png
   :alt: Settings of List from a query with two lists defined.
   :width: 100%

   Two lists defined by the administrator.

When the settings are saved, each query is checked and run once. Queries with ``;``, comments, statements that change data, ``INTO`` or functions that wait or read server files are refused.

.. warning::
   The queries run with the database permissions of Moodle and can read any table, including passwords and personal data. The checks catch common mistakes, not a malicious administrator. Review each query as you would review code.

Example, the users with a given institution:

.. code-block:: text

   SELECT username FROM {user} WHERE institution = 'IFRN' AND deleted = 0

Re-checks
---------

The enrolment methods that use this engine (``enrol_conditional`` and any other that implements ``\local_enrolconditions\enforceable``) choose, per instance, when existing enrolments are re-checked and what happens when the conditions are no longer met. *Site administration > Plugins > Enrolments > Enrolment conditions > Re-checks* has the settings common to all of them:

Enrolments per chunk
   Number of enrolments the scheduled task reads and re-checks at a time (default 500).

Time limit of a periodic check
   How long each run of the scheduled task keeps re-checking chunks (default one minute). A run always checks at least one chunk and stops earlier when it has gone through every enrolment; the next run continues where it stopped, on any server.

Scheduled tasks
---------------

*Re-check the conditions of existing enrolments* runs every hour and re-checks, chunk by chunk within its time limit, the enrolments of the instances with the periodic check.

*Delete the condition records of enrolments that no longer exist* runs daily and removes the records of enrolments deleted without an event (for example, when an enrolment method is uninstalled).

The re-checks on events run in the background: an event observed by the conditions queues an ad hoc task *Re-check the enrolments of a user after a change the conditions depend on*, which cron runs shortly after. Changes made by these tasks can queue further ones, at most five levels deep, so instances whose conditions depend on each other cannot keep changing each other.

*Delete files of usernames no longer used by any condition* (``enrolcond_userfile``) runs daily and deletes, after one day, the files that no condition uses any more.
