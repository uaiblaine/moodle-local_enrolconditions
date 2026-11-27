# M20e: the cleanup reads the stored values of enabled instances only (get_course_stored_values()).
s#enrol_get_instances\(\$courseid, false\)( as \$instance\) \{\s+\$name = \(string\) \$instance->enrol;)#enrol_get_instances(\$courseid, true)$1# or die "M20e";
