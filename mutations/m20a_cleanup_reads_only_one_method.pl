# M20a: the trees of the enrolment methods other than enrol_conditional are not read.
s#(foreach \(enrol_get_instances\(\$courseid, false\) as \$instance\) \{)#$1\n            if (\$instance->enrol !== 'conditional') {\n                continue;\n            }# or die "M20a";
