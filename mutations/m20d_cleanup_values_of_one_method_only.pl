# M20d: the cleanup reads the stored values of the instances of enrol_conditional only (get_course_stored_values()).
s#(foreach \(enrol_get_instances\(\$courseid, false\) as \$instance\) \{)(\s+\$name = \(string\) \$instance->enrol;)#$1\n            if (\$instance->enrol !== 'conditional') {\n                continue;\n            }$2# or die "M20d";
