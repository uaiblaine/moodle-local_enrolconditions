# E01: decide on the snapshot passed in instead of the row read again under the lock.
s#(protected \?\\core\\lock\\lock_factory \$lockfactory = null;)#$1\n\n    protected ?\\stdClass \$snapshot = null;# or die "E01 declare";
s#return \$this->decide_and_write\(\$plugin, \$instance, \(int\) \$ue->id\);#\$this->snapshot = \$ue;\n        return \$this->decide_and_write(\$plugin, \$instance, (int) \$ue->id);# or die "E01 remember";
s#\$ue = \$DB->get_record\('user_enrolments', \['id' => \$ueid\]\);#\$ue = \$this->snapshot ?? \$DB->get_record('user_enrolments', ['id' => \$ueid]);# or die "E01 use";
