# E02: a refused lock no longer skips the enrolment: the check goes on without holding it.
s#if \(!\$lock\) \{\s+return self::OUTCOME_SKIPPED;\s+\}\s+try \{#try {# or die "E02 skip";
s#\$lock->release\(\);#if (\$lock) {\n                \$lock->release();\n            }# or die "E02 release";
