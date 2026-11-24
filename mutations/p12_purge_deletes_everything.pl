# P12: the orphan purge deletes every state row, not only those whose user enrolment is gone.
s#"NOT EXISTS \(SELECT 1 FROM \{user_enrolments\} ue WHERE ue\.id = \{\{\$table\}\}\.userenrolmentid\)"#'1 = 1'# or die "P12";
