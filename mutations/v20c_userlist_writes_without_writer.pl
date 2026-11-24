# V20c: the userlist deletes write back through a method that does not implement tree_writer.
s#if \(\$changed && \$found\['plugin'\] instanceof tree_writer\) \{#if (\$changed) {# or die "V20c";
