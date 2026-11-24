# F16b: process_dynamic_submission() no longer asks the access check on the final configuration.
s#if \(\$error !== null\) \{(\s+throw new moodle_exception\('editornotavailable')#if (false) {$1# or die "F16b";
