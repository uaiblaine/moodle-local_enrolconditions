# V19e: a deleted file leaves the cached list of its content readable.
s#(\$file->delete\(\);)\s+condition::forget_usernames\(\$oldhash\);(\s+continue;)#$1$2# or die "V19e";
