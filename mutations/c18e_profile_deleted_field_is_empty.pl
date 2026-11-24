# C18e: a deleted custom profile field reads as an empty value instead of unknown.
s#return array_key_exists\(\$shortname, \$values\) \? \(string\) \$values\[\$shortname\] : null;#return (string) (\$values[\$shortname] ?? '');# or die "C18e";
