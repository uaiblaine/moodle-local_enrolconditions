# A16a: the reach check runs only after the configuration validates (the order before the fix).
s#if \(\$context !== null\) \{(\s+\$denied = self::check_node)#if (\$context !== null && !(\$validate ? \$validate() : \$condition->validate_config(\$config))) {$1# or die "A16a";
