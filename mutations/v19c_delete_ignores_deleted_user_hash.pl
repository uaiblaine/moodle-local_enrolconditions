# V19c: the deletes no longer remove the line of a deleted account matched by md5.
s#!usernames::names_any\(\$value, \$matchers\)#!usernames::names_any(\$value, array_filter(\$matchers, fn(array \$m): bool => \$m['username'] !== null))# or die "V19c";
