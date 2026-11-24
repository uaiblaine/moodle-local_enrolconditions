# A15g: node_key() hashes a configuration json_encode() cannot write as if it were empty.
s#json_encode\(self::sorted\(\$config\), JSON_THROW_ON_ERROR\)#(string) json_encode(self::sorted(\$config))# or die "A15g";
