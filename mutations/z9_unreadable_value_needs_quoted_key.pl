# Z9: an unreadable value counts as naming a file only when the key is spelt with its quotes.
s#str_contains\(\$text, 'fileid'\)#str_contains(\$text, '"fileid"')# or die "Z9";
