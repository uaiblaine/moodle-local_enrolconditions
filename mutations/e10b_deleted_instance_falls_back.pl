# E10b: an instance deleted meanwhile falls back to the object loaded earlier.
s#if \(!\$fresh\) \{\s+return null;\s+\}#if (!\$fresh) {\n            return \$instance;\n        }# or die "E10b";
