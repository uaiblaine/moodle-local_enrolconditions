# C17h: an enrolment in a hidden course makes it referenceable without moodle/course:viewhiddencourses.
s#if \(!\$course->visible && !has_capability\('moodle/course:viewhiddencourses', \$context\)\) \{\s+return false;\s+\}## or die "C17h";
