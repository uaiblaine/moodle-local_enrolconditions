# O10b: identical pending re-checks are no longer collapsed into one.
s#\\core\\task\\manager::queue_adhoc_task\(\$task, true\);#\\core\\task\\manager::queue_adhoc_task(\$task, false);# or die "O10b";
