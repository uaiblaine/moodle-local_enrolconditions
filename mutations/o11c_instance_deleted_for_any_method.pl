# O11c: enrol_instance_deleted() deletes state rows and purges the map for every enrolment method.
s#if \(self::is_enforceable\(\(string\) \(\$event->other\['enrol'\] \?\? ''\)\)\) \{(\s+\$DB->delete_records\(enforcer::TABLE, \['instanceid')#if (true) {$1# or die "O11c";
