# E04b: the ownership predicate no longer compares the enrolment's status with suspended.
s#return \$status === ENROL_USER_SUSPENDED && (\$timemodified === \(int\) \$state->uetimemodified;)#return $1# or die "E04b";
