# E07c: an unknown evaluation counts as not met (the explanation guard of decide_and_write() still refuses).
s#return \$tree->evaluate\(\$userid, \$instance, false\)->get_value\(\);#return (bool) \$tree->evaluate(\$userid, \$instance, false)->get_value();# or die "E07c";
