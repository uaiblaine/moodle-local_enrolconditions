# C18h: records_exist() never reads the request cache.
s#\$known = \$cache->get\(\$table \. '_' \. \$id\);#\$known = false;# or die "C18h";
