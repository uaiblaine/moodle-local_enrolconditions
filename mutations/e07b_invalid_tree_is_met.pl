# E07b: a stored tree that no longer parses counts as met.
s#if \(\$tree === null\) \{(\s+)return null;#if (\$tree === null) {$1return true;# or die "E07b";
