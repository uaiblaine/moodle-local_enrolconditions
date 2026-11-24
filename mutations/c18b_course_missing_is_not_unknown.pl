# C18b: a deleted course (or id 0) is "not completed" instead of unknown.
s#if \(!self::records_exist\('course', \[\$courseid\]\)\) \{#if (false) {# or die "C18b";
