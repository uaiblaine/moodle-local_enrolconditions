# V19f: the item id of a saved file is the posted one when there is one.
s#\$config\['fileid'\] = \$fileid;#\$config['fileid'] = (int) (\$data->fileid ?? 0) ?: \$fileid;# or die "V19f";
