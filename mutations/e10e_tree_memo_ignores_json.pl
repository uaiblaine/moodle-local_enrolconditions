# E10e: the parsed tree is kept per instance whatever the stored JSON is now.
s#if \(!isset\(\$this->trees\[\$id\]\) \|\| \$this->trees\[\$id\]\['json'\] !== \$json\) \{#if (!isset(\$this->trees[\$id])) {# or die "E10e";
