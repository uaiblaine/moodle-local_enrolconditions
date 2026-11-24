# E07a: an unknown evaluation counts as met.
s#return \$tree->evaluate\(\$userid, \$instance, false\)->get_value\(\);#return \$tree->evaluate(\$userid, \$instance, false)->get_value() ?? true;# or die "E07a";
