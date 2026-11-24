# E08a: a throwing row is no longer caught: it stops the batch.
s#(\} catch \(\\Throwable \$e\) \{)(\s+if \(\$DB->is_transaction_started\(\)\) \{)#$1\n                throw \$e;$2# or die "E08a";
