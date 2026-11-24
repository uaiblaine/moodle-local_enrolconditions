# C17a: the cohort condition's check_access() never refuses.
s#return get_string\('noaccess', 'enrolcond_cohort'\);#return null;# or die "C17a";
