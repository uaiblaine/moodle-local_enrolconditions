# Z3: a value that names a file and cannot be decoded is read as "names no file".
s#(if \(str_contains\(\$text, '"fileid"'\)\) \{\s+)return null;#${1}continue;# or die "Z3";
