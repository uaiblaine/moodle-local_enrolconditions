# O12: the before_user_enrolment_updated hook treats every change by someone else as keeping ownership.
s#\$stillowned = enforcer::suspension_still_owned\(\$state, \(int\) \$ue->status, \(int\) \$ue->timemodified\);#\$stillowned = true;# or die "O12";
