# Z7: the cleanup spares only the files of the trees in force, as check_access() does.
s#return self::get_spared_fileids\(\$courseid\);#return self::get_referenced_fileids(\$courseid);# or die "Z7";
