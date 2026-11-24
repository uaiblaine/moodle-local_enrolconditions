# T15c: node_key() no longer sorts the keys of associative arrays.
s#if \(!array_is_list\(\$value\)\) \{\s+ksort\(\$value\);\s+\}## or die "T15c";
