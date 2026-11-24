# C17b: the coursecompleted condition's check_access() never refuses.
s#return get_string\('noaccess', 'enrolcond_coursecompleted'\);#return null;# or die "C17b";
