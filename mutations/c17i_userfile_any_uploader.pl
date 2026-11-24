# C17i: userfile check_access() accepts a file another user stored.
s#!\$file \|\| \(int\) \$file->get_userid\(\) !== \(int\) \$USER->id#!\$file# or die "C17i";
