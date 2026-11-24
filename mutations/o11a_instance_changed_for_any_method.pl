# O11a: enrol_instance_changed() purges the event map for every enrolment method.
s#if \(self::is_enforceable\(\(string\) \(\$event->other\['enrol'\] \?\? ''\)\)\) \{(\s+enforcer::purge_event_map\(\);)#if (true) {$1# or die "O11a";
