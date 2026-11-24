# V20d: the userlist deletes keep every name.
s#fn\(string \$name\): bool => !usernames::names_any\(core_text::strtolower\(\$name\), \$matchers\)#fn(string \$name): bool => true# or die "V20d";
