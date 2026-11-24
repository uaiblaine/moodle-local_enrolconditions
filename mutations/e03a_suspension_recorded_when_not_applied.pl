# E03a: record the suspension and fire the event although core did not apply the write.
s#if \(!\$this->update_status\(\$plugin, \$instance, \$ue, ENROL_USER_SUSPENDED\)\) \{#if (\$this->update_status(\$plugin, \$instance, \$ue, ENROL_USER_SUSPENDED) && false) {# or die "E03a";
