# Z4: a method that throws when asked for its tree is read as "no values".
s#(\} catch \(\\Throwable \$e\) \{\s+)return null;#${1}return [];# or die "Z4";
