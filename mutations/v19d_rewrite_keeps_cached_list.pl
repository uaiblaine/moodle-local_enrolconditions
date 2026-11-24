# V19d: a rewritten file leaves the cached list of its old content readable.
s#(implode\("\\n", \$kept\)\);)\s+condition::forget_usernames\(\$oldhash\);#$1# or die "V19d";
