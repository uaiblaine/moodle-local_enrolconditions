# R21b: a restore from another site keeps the reference to the file.
s#return \$samesite \? \$config : \['fileid' => 0, 'filename' => '', 'count' => 0\];#return \$config;# or die "R21b";
