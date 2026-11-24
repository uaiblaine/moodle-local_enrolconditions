# M20c: the cleanup deletes on the references it read at the course's first file, not read again.
s#// The list was read before this file's turn: a tree saved meanwhile may use it now\.\s+\$referenced\[\$courseid\] = \$this->read_references\(\$courseid\);\s+if \(isset\(\$referenced\[\$courseid\]\[\(int\) \$record->itemid\]\)\) \{\s+continue;\s+\}\s+## or die "M20c";
