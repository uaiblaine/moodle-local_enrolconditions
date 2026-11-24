# C17g: userfile check_access() no longer refuses a context that is not a course.
s#if \(\$context->contextlevel !== CONTEXT_COURSE\) \{\s+return get_string\('noaccess', self::COMPONENT\);\s+\}## or die "C17g";
