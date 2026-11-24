# T13c: a condition whose get_description() throws is no longer caught.
s#(\} catch \(\\Throwable \$e\) \{)(\s+debugging\("Condition .{0,30}could not describe itself)#$1\n            throw \$e;$2#s or die "T13c";
