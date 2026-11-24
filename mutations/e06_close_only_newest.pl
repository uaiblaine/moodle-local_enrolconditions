# E06: close_all() closes only the newest open state, leaving older duplicates open.
s#foreach \(\$opens as \$open\) \{(\s+self::close\(\$open, \$reason\);)#foreach (array_slice(\$opens, 0, 1) as \$open) {$1# or die "E06";
