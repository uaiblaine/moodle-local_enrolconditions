# T14a: evaluation without explanations no longer stops at the deciding child of an AND group.
s#(\$value = \$decisive;)\s+break;#$1\n                    if (\$node['op'] === self::OP_OR) {\n                        break;\n                    }# or die "T14a";
