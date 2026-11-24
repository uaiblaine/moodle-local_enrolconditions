# E07e: the explanation built under the lock is trusted without asking whether it is still "not met".
s#if \(!\$result->is_not_met\(\)\) \{\s+return \$result->is_unknown\(\) \? self::OUTCOME_UNKNOWN : self::OUTCOME_NOTHING;\s+\}## or die "E07e";
