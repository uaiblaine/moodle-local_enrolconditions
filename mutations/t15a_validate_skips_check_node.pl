# T15a: tree::validate() no longer asks the reach check (no context reaches editor_access::validate_node()).
s#editor_access::validate_node\(\$condition, \$type, \$node\['config'\], \$context, \$stored\)#editor_access::validate_node(\$condition, \$type, \$node['config'], null, \$stored)# or die "T15a";
