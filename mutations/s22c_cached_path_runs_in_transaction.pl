# S22c: the cached path refreshes an expired entry inside an open transaction instead of serving it.
s#if \(\$cached !== false && \$DB->is_transaction_started\(\)\) \{#if (false) {# or die "S22c";
