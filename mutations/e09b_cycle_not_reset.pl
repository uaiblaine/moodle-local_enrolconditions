# E09b: a finished cycle leaves the position where it was instead of starting over.
s#self::set_position\(0, 0\);(\s+return \$outcomes;)#$1# or die "E09b";
