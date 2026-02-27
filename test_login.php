<?php
// Test login script
require 'vendor/autoload.php';

$email = 'superadmin@example.com';
$password = 'Password123!';

$ch = curl_init('http://localhost:8080/api/v1/auth/login');
$data = json_encode(['email' => $email, 'password' => $password]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode
";
echo "Response: $response
";
