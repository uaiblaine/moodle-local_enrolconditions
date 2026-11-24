# V19b: get_users_in_context() no longer lists deleted accounts matched by md5.
s#\$userlist->add_from_sql\('id', "SELECT id FROM \{user\} WHERE deleted = 1 AND email \$insql", \$params\);## or die "V19b";
