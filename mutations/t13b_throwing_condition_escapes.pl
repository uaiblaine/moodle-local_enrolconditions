# T13b: a condition whose is_met() throws is no longer unknown: the exception escapes.
s#(\} catch \(\\Throwable \$e\) \{)(\s+// A failing condition)#$1\n            throw \$e;$2# or die "T13b";
