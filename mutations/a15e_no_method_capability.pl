# A15e: require_editor_access() no longer asks the method's config capability.
s#if \(\$capabilities && !has_any_capability\(\$capabilities, \$context\)\) \{#if (false) {# or die "A15e";
