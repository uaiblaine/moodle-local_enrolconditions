# T13a: a disabled condition type is evaluated instead of unknown.
s#if \(!isset\(\$enabled\[\$node\['type'\]\]\)\) \{\s+return null;\s+\}## or die "T13a";
