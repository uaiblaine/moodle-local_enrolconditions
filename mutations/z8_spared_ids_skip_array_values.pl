# Z8: the walk ignores a "fileid" whose value is an array, which the condition reads as item 1.
s#if \(\$key === 'fileid' && \(int\) \$value > 0\) \{#if (\$key === 'fileid' && !is_array(\$value) && (int) \$value > 0) {# or die "Z8";
