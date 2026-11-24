# X24: a run that ends with errors does not say how many.
s#if \(\$errors\) \{#if (false) {# or die "X24";
