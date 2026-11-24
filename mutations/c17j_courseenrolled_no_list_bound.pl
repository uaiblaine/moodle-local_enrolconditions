# C17j: courseenrolled check_access() checks a list longer than validation allows.
s#if \(count\(\$courseids\) > self::MAX_COURSES\) \{\s+return get_string\('toomany', 'enrolcond_courseenrolled', self::MAX_COURSES\);\s+\}## or die "C17j";
