# C18d: records_exist() says that an empty list of ids exists, so a configuration naming no record is evaluated.
s#if \(!\$ids\) \{\s+return false;#if (false) {\n            return false;# or die "C18d";
