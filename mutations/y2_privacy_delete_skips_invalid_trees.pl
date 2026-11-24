# Y2: the erasure of a value that is not a valid tree writes it back unchanged.
s#self::walk_lists\(\$found\['data'\], \$keep\)#\$found['data']# or die "y2: pattern did not match\n";
