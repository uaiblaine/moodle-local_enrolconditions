# E12: an own write resets the marks on its way out instead of restoring the outer write's.
s#\[self::\$acting, self::\$actingue, self::\$actingstatus, self::\$applied\] = \$previous;#[self::\$acting, self::\$actingue, self::\$actingstatus, self::\$applied] = [false, null, null, null];# or die "E12";
