# Mutation spec

Each gate in `gates.conf` breaks one guard of this plugin and names, in `#   ->` lines, the tests
that must go red because of it. A gate without such a line is a guard that no test is known to hold:
that is the finding, and its comment says so.

```sh
mdl mutate moodle-local_enrolconditions moodle-local_enrolconditions/mutations/gates.conf --fast --dry-run --branch MOODLE_503_STABLE   # patterns only
mdl mutate moodle-local_enrolconditions moodle-local_enrolconditions/mutations/gates.conf --fast --branch MOODLE_503_STABLE --db pgsql
mdl mutate moodle-local_enrolconditions moodle-local_enrolconditions/mutations/gates.conf --fast --branch MOODLE_503_STABLE --db mariadb
mdl mutate moodle-local_enrolconditions moodle-local_enrolconditions/mutations/gates.conf --fast --changed origin/main --list
```

Sweep both database families. The sweep takes about a minute a gate; run it from the main session in a
background command, never inside a subagent. Run `--dry-run` after any refactor that moves these files.

## Groups

- **E**: the enforcer (`classes/local/enforcer.php`): the row read under the lock, the refused lock, "core applied
  my write", `owns()`, the delegated transaction, closing every open row, unknown never acting, the periodic check
  (per-row catch, position, time budget, cycle reset).
- **O, P**: the observers, the queued re-check (dedup, depth bound, fault catch), the enforceable-method filter and the
  orphan purge.
- **T, A**: the tree (disabled and throwing conditions, three-valued short-circuit, `validate()`) and
  `editor_access` (`require_editor_access()` checks, `check_node()`, `node_key()`).
- **F**: the dialog and the editor summary asking the access check.
- **C, R, V, M, S, X**: conditions (`check_access()`, a missing record is unknown, `restore_config()`), the userfile
  privacy provider and file ids, the cleanup task's tree reading, sqlquery caching and locking, the event-map purge.

## Rules for adding one

- Add the gate in the same change as the guard and name the test that must redden. If you cannot, say so in the comment.
- Perl interpolates `$variables` on both sides of `s///`: escape every `$` (`\$`) in a pattern and a replacement.
- Use `#` as delimiter for anything containing `{}`; never use a delimiter the pattern needs literally.
- Each script ends with `or die`, so a pattern that stops matching is a hard error, not a pass.
- Mutate the value or the guard the promise rests on, and check the diff of `--dry-run` shows exactly one guard.
- This directory is not shipped: keep it `export-ignore`d in `.gitattributes`.
