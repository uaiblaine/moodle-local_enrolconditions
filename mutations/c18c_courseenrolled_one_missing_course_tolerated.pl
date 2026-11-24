# C18c: only the first course of the list must exist for the condition to be known.
s#!self::records_exist\('course', \$courseids\)#!self::records_exist('course', array_slice(\$courseids, 0, 1))# or die "C18c";
