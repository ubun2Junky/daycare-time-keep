<?php
/**
 * PIN Hash Generator
 *
 * This script generates a proper password hash for the PIN "123456"
 */

$pin = '123456';
$hash = password_hash($pin, PASSWORD_DEFAULT);

echo "PIN: " . $pin . "\n";
echo "Hash: " . $hash . "\n";
?>
