# E05: status and state row are written without the delegated transaction, so a failing insert keeps the suspension.
s#\$transaction = \$DB->start_delegated_transaction\(\);\s+try \{\s+\$outcome = (\$this->write\([^;]*\));\s+\$transaction->allow_commit\(\);\s+\} catch \(\\Throwable \$e\) \{\s+\$transaction->rollback\(\$e\);\s+\}#\$outcome = $1;# or die "E05";
