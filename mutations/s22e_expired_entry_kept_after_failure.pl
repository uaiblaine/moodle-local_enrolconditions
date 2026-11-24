# S22e: an expired entry stays in the cache after the refresh failed.
s#\$cache->delete\(\$cachekey\);(\s+throw \$e;)#$1# or die "S22e";
