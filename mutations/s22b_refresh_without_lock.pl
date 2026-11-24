# S22b: a refused refresh lock no longer serves the expired entry: the request refreshes anyway.
s#if \(!\$lock\) \{#if (false) {# or die "S22b skip";
s#\$lock->release\(\);#if (\$lock) {\n                \$lock->release();\n            }# or die "S22b release";
