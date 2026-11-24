# E07d: unknown counts as not met AND the second look at the explanation no longer refuses unknown.
s#return \$tree->evaluate\(\$userid, \$instance, false\)->get_value\(\);#return (bool) \$tree->evaluate(\$userid, \$instance, false)->get_value();# or die "E07d value";
s#if \(!\$result->is_not_met\(\)\) \{\s+return \$result->is_unknown\(\) \? self::OUTCOME_UNKNOWN : self::OUTCOME_NOTHING;\s+\}## or die "E07d guard";
