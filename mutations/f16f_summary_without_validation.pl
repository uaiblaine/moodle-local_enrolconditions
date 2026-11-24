# F16f: the editor summarises a new node that its condition would not validate.
s#return !\$condition->validate_config\(\$config\);#return true;# or die "F16f";
