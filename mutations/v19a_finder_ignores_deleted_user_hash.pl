# V19a: the finder no longer matches a deleted account by the md5 of a listed name.
s#if \(md5\(\(string\) \$name\) === \$matcher\['hash'\]\) \{#if (false) {# or die "V19a";
