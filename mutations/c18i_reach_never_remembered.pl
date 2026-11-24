# C18i: can_reference_course() never reads its request memo, so every node pays every course again.
s#\$known = \$cache->get\(\$key\);#\$known = false;# or die "C18i";
