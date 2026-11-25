# Z6: the read right before a deletion turns null into an empty list.
s#(// The list was read before this file's turn: a tree saved meanwhile may use it now\.\s+\$referenced\[\$courseid\] = \$this->read_references\(\$courseid\));#$1 ?? [];# or die "Z6";
