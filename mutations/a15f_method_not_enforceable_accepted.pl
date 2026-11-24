# A15f: require_editor_access() accepts a method that stores no condition trees.
s#if \(!manager::get_enforceable\(\$enrol\)\) \{#if (false) {# or die "A15f";
