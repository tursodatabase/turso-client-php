<?php

use Doctrine\DBAL\DriverManager;

require_once __DIR__ . '/../vendor/autoload.php';

$params = [
    "url"               => "database.db",
    "auth_token"        => "",
    "sync_url"          => "",
    "sync_interval"     => 5,
    "read_your_writes"  => true,
    "encryption_key"    => "",
    "remote_only"       => true,
    'driverClass'       => \Darkterminal\LibSQL\DBAL\Driver::class,
];

$db = DriverManager::getConnection($params);

$result = $db->executeQuery("SELECT name, userId, email FROM users LIMIT 2")->fetchAllAssociativeIndexed();
var_dump($result);
$db->close();
