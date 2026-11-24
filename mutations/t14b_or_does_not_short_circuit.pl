# T14b: evaluation without explanations no longer stops at the deciding child of an OR group.
s#(\$value = \$decisive;)\s+break;#$1\n                    if (\$node['op'] === self::OP_AND) {\n                        break;\n                    }# or die "T14b";
