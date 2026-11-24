# E11a: the hook treats any row written as the enforcer's own (and skips its ownership rule).
s#if \(enforcer::is_acting_on\(\(int\) \$ue->id\)\) \{#if (enforcer::is_acting_on((int) \$ue->id) || true) {# or die "E11a";
