# E10d: an instance whose row now belongs to a method this enforcer does not handle is treated as the old method's.
s#if \(\$fresh->enrol !== \$instance->enrol \|\| !\$this->get_plugin\(\(string\) \$fresh->enrol\)\) \{\s+return null;\s+\}#\$fresh->enrol = \$instance->enrol;# or die "E10d";
