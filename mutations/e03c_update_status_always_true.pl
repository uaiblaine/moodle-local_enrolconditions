# E03c: update_status() reports every write as applied, for both directions.
s#return \$applied !== null && \$applied\['ueid'\] === \(int\) \$ue->id && \$applied\['status'\] === \$status;#return true;# or die "E03c";
