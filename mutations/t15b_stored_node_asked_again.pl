# T15b: check_node() asks check_access() even for a node stored unchanged.
s#if \(isset\(\$storedkeys\[\$key\]\)\) \{\s+return null;\s+\}## or die "T15b";
