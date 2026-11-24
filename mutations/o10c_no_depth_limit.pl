# O10c: recheck_user::queue() no longer refuses a depth beyond MAX_DEPTH.
s#if \(\$depth > self::MAX_DEPTH\) \{\s+return false;\s+\}## or die "O10c";
