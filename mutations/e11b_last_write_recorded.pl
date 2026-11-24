# E11b: record_applied() records every write while acting, the last one winning (the code before the fix).
s#if \(\s+self::\$applied === null\s+&& self::is_acting_on\(\(int\) \$ue->id\)\s+&& self::\$actingstatus === \(int\) \$ue->status\s+\) \{#if (self::\$acting) {# or die "E11b";
