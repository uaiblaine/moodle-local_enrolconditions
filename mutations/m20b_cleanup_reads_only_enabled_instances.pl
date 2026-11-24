# M20b: the trees of disabled instances are not read.
s#enrol_get_instances\(\$courseid, false\)#enrol_get_instances(\$courseid, true)# or die "M20b";
