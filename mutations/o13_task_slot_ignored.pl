# O13: queue() always takes the first slot, so a running task of the same identity swallows the event.
s#if \(!self::is_running\(\$task\)\) \{\s+break;\s+\}#break;# or die "O13";
