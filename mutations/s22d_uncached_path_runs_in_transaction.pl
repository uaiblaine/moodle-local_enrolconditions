# S22d: run(), the one function that runs the admin's SQL, no longer refuses inside an open transaction.
s#if \(\$DB->is_transaction_started\(\)\) \{(\s+throw new \\moodle_exception\('errortransaction')#if (false) {$1# or die "S22d";
