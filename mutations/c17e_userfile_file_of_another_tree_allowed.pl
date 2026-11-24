# C17e: userfile check_access() no longer refuses a file a stored tree already refers to.
s#\s+\|\| isset\(task\\cleanup_files::get_referenced_fileids\(\(int\) \$context->instanceid\)\[\$fileid\]\)## or die "C17e";
