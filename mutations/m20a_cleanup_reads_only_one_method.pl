# M20a: check_access() reads the trees of the enrolment methods other than enrol_conditional no more (get_course_trees()).
s#(foreach \(enrol_get_instances\(\$courseid, false\) as \$instance\) \{)(\s+\$tree = static::get_instance_tree\(\$instance\);)#$1\n            if (\$instance->enrol !== 'conditional') {\n                continue;\n            }$2# or die "M20a";
