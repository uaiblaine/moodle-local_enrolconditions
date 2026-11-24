# C17d: a course is no longer referenceable through an enrolment in it (only can_view_course_info() counts).
s#return is_enrolled\(\$context, \$USER, '', true\);#return false;# or die "C17d";
