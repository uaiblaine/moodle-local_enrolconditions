# E03b: the reactivation counts as applied whatever core did.
s#\$applied = \$this->update_status\(\$plugin, \$instance, \$ue, ENROL_USER_ACTIVE\);#\$this->update_status(\$plugin, \$instance, \$ue, ENROL_USER_ACTIVE);\n            \$applied = true;# or die "E03b";
