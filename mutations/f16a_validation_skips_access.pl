# F16a: the dialog's validation no longer asks the access check (no context reaches validate_node()).
s#(\$condition->config_from_form_data\(\(object\) \$data\),\s+)\$this->get_context_for_dynamic_submission\(\),#$1null,# or die "F16a";
