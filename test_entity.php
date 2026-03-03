<?php
require 'vendor/autoload.php';
// Mock CI4 environment
define('HOLY_PHP', true);
$entity = new \App\Entities\UserEntity();
$entity->fill([
    'first_name' => 'John',
    'last_name' => 'Doe'
]);
print_r($entity->toArray());
