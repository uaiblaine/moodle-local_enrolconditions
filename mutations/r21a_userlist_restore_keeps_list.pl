# R21a: a restore from another site keeps the list of usernames.
s#return \$samesite \? \$config : \['usernames' => \[\]\];#return \$config;# or die "R21a";
