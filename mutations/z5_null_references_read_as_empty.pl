# Z5: the references of a course that cannot be read are returned as an empty list, never as null.
s#return self::get_spared_fileids\(\$courseid\);#return self::get_spared_fileids(\$courseid) ?? [];# or die "Z5";
