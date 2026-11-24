# C18a: a deleted cohort (or id 0) is "not a member" instead of unknown.
s#if \(!self::records_exist\('cohort', \[\$cohortid\]\)\) \{#if (false) {# or die "C18a";
