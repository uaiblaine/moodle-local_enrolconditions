# M20b: check_access() does not read the trees of disabled instances (get_course_trees()).
s#enrol_get_instances\(\$courseid, false\)( as \$instance\) \{\s+\$tree = static::get_instance_tree\(\$instance\);)#enrol_get_instances(\$courseid, true)$1# or die "M20b";
