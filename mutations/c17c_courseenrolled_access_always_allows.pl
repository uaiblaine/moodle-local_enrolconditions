# C17c: the courseenrolled condition's check_access() never refuses.
s#return get_string\('noaccess', 'enrolcond_courseenrolled'\);#return null;# or die "C17c";
