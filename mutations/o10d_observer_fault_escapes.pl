# O10d: a fault inside the "*" observer is rethrown into the caller of the event.
s#(\} catch \(\\Throwable \$e\) \{)(\s+debugging\('local_enrolconditions could not queue)#$1\n            throw \$e;$2# or die "O10d";
