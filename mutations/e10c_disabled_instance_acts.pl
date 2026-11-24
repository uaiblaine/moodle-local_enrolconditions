# E10c: an instance disabled meanwhile still acts.
s#if \(\(int\) \$fresh->status !== ENROL_INSTANCE_ENABLED\) \{\s+return null;\s+\}## or die "E10c";
