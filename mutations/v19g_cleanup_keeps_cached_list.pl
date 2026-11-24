# V19g: a file the cleanup deletes leaves the cached list of its content readable.
s#condition::forget_usernames\(\$hash\);\s+(\$deleted\+\+;)#$1# or die "V19g";
