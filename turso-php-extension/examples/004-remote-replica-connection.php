<?php

$config = [
    "url" => "file:database.db",
    "authToken" => "secrettoken",
    "syncUrl" => "libsql://database-org.turso.io",
    "syncInterval" => 5,
    "read_your_writes" => true,
    "encryptionKey" => "",
 ];
 $db = new LibSQL($config);
