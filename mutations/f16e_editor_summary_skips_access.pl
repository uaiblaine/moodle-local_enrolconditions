# F16e: the editor names what a node refers to without asking the access check.
s#if \(editor_access::check_node\(\$type, \$config, \$this->context, \$this->storedkeys\) !== null\) \{\s+return false;\s+\}## or die "F16e";
