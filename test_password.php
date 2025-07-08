<?php
$password = 'leejaeyun123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password\n";
echo "Hash: $hash\n";
echo "Verify: " . (password_verify($password, $hash) ? 'TRUE' : 'FALSE') . "\n";
?> 