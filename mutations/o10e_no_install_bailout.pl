# O10e: the "*" observer also works during an upgrade or the initial install.
s#if \(!empty\(\$CFG->upgraderunning\) \|\| during_initial_install\(\)\) \{\s+return;\s+\}## or die "O10e";
