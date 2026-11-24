# C18f: a field outside the allowed list is read from the user row instead of unknown.
s#if \(!in_array\(\$field, self::STANDARD_FIELDS, true\)\) \{\s+return null;\s+\}## or die "C18f";
