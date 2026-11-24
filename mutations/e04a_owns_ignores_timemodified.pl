# E04a: the ownership predicate no longer compares the enrolment's timemodified with the recorded one.
s#return \$status === ENROL_USER_SUSPENDED && \$timemodified === \(int\) \$state->uetimemodified;#return \$status === ENROL_USER_SUSPENDED;# or die "E04a";
