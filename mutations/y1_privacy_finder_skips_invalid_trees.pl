# Y1: the privacy provider no longer asks for the stored values that are valid JSON but not a valid tree.
s#\+ manager::get_invalid_trees_with_type\(self::TYPE, \$courseid\)#+ []# or die "y1: pattern did not match\n";
