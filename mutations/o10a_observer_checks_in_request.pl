# O10a: the "*" observer evaluates and writes inside the request instead of only queueing.
s#(\s+)if \(!recheck_user::queue\(#$1(new enforcer())->check_user_in_mode(\$userid, \\local_enrolconditions\\enforceable::CHECK_EVENTS);$1if (!recheck_user::queue(# or die "O10a";
