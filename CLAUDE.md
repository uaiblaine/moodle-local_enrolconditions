# Claude instructions for `local_enrolconditions`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin, as rules
with a one-line reason each.

Plugin context: a Moodle **local** plugin ("Enrolment conditions") that is the condition engine of
`enrol_conditional`. It declares the `enrolcond` subplugin type (folder `condition/`, eight subplugins:
`date`, `profile`, `cohort`, `coursecompleted`, `courseenrolled`, `userlist`, `userfile`, `sqlquery`), the base class
`\local_enrolconditions\condition\base`, the hook `collect_conditions` and the enforcer that re-checks
existing enrolments (login, scheduled task, events) and suspends, removes or reactivates them. It owns one
table, `local_enrolconditions_state`, and depends on nothing. Supports Moodle **5.3 only**
(`$plugin->requires = 2026100200`, `$plugin->supported = [503, 503]`). CI is the moodle-an-hochschulen
reusable workflow, one job (`ci-503`) in `.github/workflows/ci.yml` — **update it when `supported` changes**.
Development happens on the m503 stack; this repo is mounted into m503 and m503b through `auto`, and into the
mini cluster, at `local/enrolconditions` (see `~/dev/moodle-dev/plugins.conf`). It is a local plugin because
Moodle accepts subplugins only under `mod`, `editor`, `tool` and `local`.

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-local_enrolconditions                  # one leg locally before any push
mdl phpunit m503 local_enrolconditions               # engine and condition tests (tests/ and tests/condition/)
mdl phpunit m503 enrol_conditional                   # the method's tests, after any interface change
mdl grunt m503 local/enrolconditions                 # rebuild amd/build (commit with src)
mdl purge m503                                       # after PHP changes that affect output
```

In a Claude Code cloud session none of `mdl`, the stacks or `~/dev/CLAUDE.md` exist. The cloud
environment's setup script (`moodle-dev/cloud/setup.sh`) installs `moodle-plugin-ci` and the `mpci`
runner instead: `mpci` runs every gate on the highest branch in `ci.yml`, `mpci --branch <branch>`
another one, `mpci --reuse --only phpunit --filter <test>` iterates. It cannot run Behat, PHP 8.4 or
MariaDB, so the owner runs the gate the change's tier asks (fleet `CLAUDE.md` section 4; the
matrix, `mdl ci moodle-local_enrolconditions --matrix`, for a tier-3 change) before merging: push a branch,
open the pull request, leave the merge to them. If `which mpci` prints nothing, the session was resumed on
a container that skipped the setup script: attach `moodle-dev` and run `bash cloud/setup.sh` from it.
See `moodle-dev/cloud/README.md`.

## Code layout

```
classes/condition/   base class and manager (types, enabled flags, hook collection)
classes/local/       enforcer (re-check, suspend/remove/reactivate, state rows), schema, usernames
classes/enforceable.php  interface an enrolment method implements to be re-checked
classes/tree*.php    condition tree (AND, OR, NOT) and its evaluation result
classes/task/        check_conditions (periodic re-check), recheck_user (event re-check), purge_orphan_states
db/                  install.xml (state table), caches, events, hooks, tasks, subplugins.json
condition/<name>/    the eight enrolcond subplugins, each with its own version.php, lang, tests
docs/                upstream's documentation (en, pt_BR), export-ignored
```

## Fork rules

- **This is a fork.** `origin` is `uaiblaine`, the remote `upstream` is `moodle-by-kelsoncm`. Upstream's
  copyright lines and `@author` tags stay as they are, because they are its authors' attribution; only
  new files carry `@copyright 2026 Anderson Blaine`. Reason: the licence and the attribution travel with the code.
- **Upstream's date-based version numbers are kept, never renumbered into the fleet's `20261005XX`
  namespace.** Reason: the sites already run them, and an upstream release would otherwise conflict or
  look older than what is installed. Bump the next number of upstream's own sequence.
- **The two plugins are developed together.** `local_enrolconditions` (the engine) and `enrol_conditional`
  (the method) change in step: the method implements the engine's `\local_enrolconditions\enforceable`
  interface, so an interface change needs both repositories, and the method's `version.php` names the
  engine version it needs. Reason: the dependency is by version number, and a stale pair fails at install.
- **The audit of 2026-11-23** is in `~/dev/moodle-dev/data/audits/enrol_conditional-local_enrolconditions-2026-11-23.md`
  (not versioned). Read it before touching enforcement, backup/restore or the SQL list condition.
- **The branch model is upstream's: `main` only.** Fleet work lives on feature branches and reaches upstream as pull
  requests, a separate piece of work; there is no `MOODLE_503_STABLE`. `mutations/gates.conf` holds the mutation
  gates: before a push sweep both database families with `mdl mutate <repo> mutations/gates.conf --fast --changed <base>`.
- `AGENTS.md` is upstream's file and only points here.

## Architecture gotchas

- **Conditions are stateless; their configuration is an array stored as JSON by the caller.** A condition keeps
  nothing between calls, so the same instance can be evaluated for any user, node or request.
- **`validate_config()` and `check_access()` run wherever a tree is saved** (`tree::validate()` with the course
  context and the stored tree), never only in the dialog. For a new or changed node `check_access()` is asked
  BEFORE `validate_config()` (`editor_access::validate_node()`), and a record that does not exist answers like one out
  of reach. Reason: no existence oracle for ids a user typed; a stored node whose record is gone still gets the
  precise `missingcourse` / `missingcohort`. A method that restores a tree stores it as it comes and relies on
  evaluation failing closed (an invalid tree is unknown: nobody enrols).
- **A condition provided through the hook overrides `get_type()` with an identifier prefixed with its component.**
  Reason: types of different plugins must not collide in a stored tree.
- **`is_met()` may throw when it cannot decide**: the result is *unknown*, which blocks a new enrolment and never
  suspends anybody.
- **Only suspensions recorded in `local_enrolconditions_state` are ever reactivated.** Reason: an enrolment someone
  else suspended must stay suspended; `uetimemodified` tells the enforcer the enrolment was changed behind its back.
- **The periodic check keeps its position** (`periodicposition`) in plugin config, never in a node-local file:
  a task runs on any node.
- **Ownership of a suspension is recorded only when core applied the enforcer's own write** (the
  `before_user_enrolment_updated` hook firing while the enforcer acts). Reason: the state row is the proof that the
  enforcer, and nobody else, suspended it; a guess from the current status would reactivate what someone else did.
- **The enforcer re-reads the instance, the enrolment and the open states under the lock**; the objects passed in are
  hints. Reason: a teacher may change the tree, action or status during a run, and an instance may be gone.
- **Event re-checks are queued adhoc tasks** (`\local_enrolconditions\task\recheck_user`, one per user, depth limit
  5) **that carry a slot.** Reason: an event during a running task must not be swallowed by core's de-duplication;
  the depth limit stops two interdependent instances from looping.
- **The periodic check is time-budgeted** (`checktimelimit`) **and pages by `(enrolid, userid)`**. Reason: a run
  that cannot finish in its budget resumes where it stopped, on any node.
- **A disabled or failing condition is "unknown" and never changes anyone.** Reason: an outage of a condition
  must not suspend or enrol people.
- **`check_access()` is the reach rule** (may the saving user reference what a condition names), asked through
  `editor_access::check_node()`, which `tree::validate()`, the dialog and the editor all call; a node already
  stored unchanged is not re-checked. Reason: a teacher must be able to edit a tree an administrator wrote.
- **A new `userfile` node may reference only a file its own user uploaded** and no stored tree uses. Reason: another
  teacher's unsaved file would expose or share that list.
- **`tree_writer` is the optional interface through which the engine erases usernames from a method's stored trees**
  (privacy requests); it never validates for the current user. Reason: its caller is the privacy system, with no
  user to check reach for.
- **Subplugin tests live under the root `tests/condition/`.** Reason: the plugin CI runs only the root suite.
- **`lang/pt_br`** (and `condition/<name>/lang/pt_br/`) exists, in lockstep with `lang/en`, `export-ignore`d; in
  Portuguese "inscrição", never "matrícula".

## Testing notes

`tests/fixtures/` holds the fake condition, enforceable and tree-writer methods; subplugin tests live under
`tests/condition/`. No Behat here: the method's features drive the editor.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.
