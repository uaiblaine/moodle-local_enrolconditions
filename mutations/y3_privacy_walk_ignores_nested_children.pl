# Y3: the walk of a value that is not a valid tree does not descend into 'children'.
s#if \(isset\(\$node->children\) && is_array\(\$node->children\)\) \{#if (false) {# or die "y3: pattern did not match\n";
