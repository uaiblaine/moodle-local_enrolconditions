# E10a: decide_and_write() decides on the instance object it was given, not on the row read again under the lock.
s#\$instance = \$this->reload_instance\(\$instance\);\s+if \(\$instance === null\) \{\s+return self::OUTCOME_NOTHING;\s+\}\s+\$plugin = \$this->get_plugin\(\(string\) \$instance->enrol\);\s+## or die "E10a";
