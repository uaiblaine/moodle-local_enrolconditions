# E08b: inside the caller's transaction the failure is swallowed instead of rethrown.
s#if \(\$DB->is_transaction_started\(\)\) \{(\s+throw \$e;)#if (false) {$1# or die "E08b";
