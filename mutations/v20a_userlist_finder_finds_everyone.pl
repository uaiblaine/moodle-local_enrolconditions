# V20a: the userlist finder reports every course with a list, listed or not.
s#if \(usernames::find\(self::listed\(\$found\), \$matcher\) !== null\) \{#if (true) {# or die "V20a";
