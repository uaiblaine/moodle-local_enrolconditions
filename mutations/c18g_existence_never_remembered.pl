# C18g: records_exist() never stores an answer in the request cache.
s#\$cache->set\(\$table \. '_' \. \$id, \$exists \? 1 : 0\);## or die "C18g";
