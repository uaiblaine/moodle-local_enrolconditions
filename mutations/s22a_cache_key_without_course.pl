# S22a: the cache key of a query that uses :courseid does not carry the course.
s#return self::params\(\$sql, \$courseid\) \? sha1\(\$sql\) \. '_' \. \$courseid : sha1\(\$sql\);#return sha1(\$sql);# or die "S22a";
