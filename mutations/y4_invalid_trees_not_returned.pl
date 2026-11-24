# Y4: the manager returns no stored value that is valid JSON but not a valid tree.
s#if \(is_object\(\$data\) \|\| is_array\(\$data\)\) \{#if (false) {# or die "y4: pattern did not match\n";
