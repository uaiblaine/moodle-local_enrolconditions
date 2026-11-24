# A15a: require_editor_access() accepts a context that is not a course.
s#if \(\$context->contextlevel !== CONTEXT_COURSE\) \{\s+throw new moodle_exception\('invalidcontext'\);\s+\}## or die "A15a";
