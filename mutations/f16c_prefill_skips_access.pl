# F16c: the dialog pre-fills a posted configuration without asking the access check.
s#\(\$condition->validate_config\(\$config\) \|\| \$this->check_node\(\$condition, \$config\) !== null\)#(\$condition->validate_config(\$config))# or die "F16c";
