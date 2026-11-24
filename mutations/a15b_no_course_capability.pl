# A15b: require_editor_access() no longer asks moodle/course:enrolconfig.
s#require_capability\('moodle/course:enrolconfig', \$context\);## or die "A15b";
