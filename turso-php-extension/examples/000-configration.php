<?php

/**
 * LibSQL - Local Connection
 * 
 * @param string $config - DSN string (Required)
 * @param int $flags - the default value is LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE (Optional)
 * @param string $encryption_key - whatever you want (Optional)
 * 
 */

// Option 1: Standard DSN Connection
$db = new LibSQL("libsql:dbname=database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");

// Option 2: Standard SQLite Connection
$db = new LibSQL("database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");

// Option 3: Standard LibSQL Connection
$db = new LibSQL("file:database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");

/**
 * LibSQL - Remote Connection
 * 
 * @param string $config - DSN string (Required)
 * 
 */

// Option: 1 Standard DSN Connection with libsql://
$db = new LibSQL("libsql:dbname=libsql://database-org.turso.io;authToken=random-token");

// Option: 2 Standard DSN Connection with https://
$db = new LibSQL("libsql:dbname=https://database-org.turso.io;authToken=random-token");

/**
 * LibSQL - Remote Replica Connection
 * 
 * @param array $config - array configuration
 * 
 * Key-value (Order is does not matter):
 * - url - string value (required)
 * - authToken - string value (required)
 * - syncUrl - string value (required)
 * - syncInterval - integer value in second (optional), default: 5
 * - read_your_writes - boolean value (optional), default: true
 * - encryptionKey - string value (optional), default: empty
 * 
 * ```
 * $config = [
 *    "url" => "file:database.db",
 *    "authToken" => "secrettoken",
 *    "syncUrl" => "libsql://database-org.turso.io",
 *    "syncInterval" => 5,
 *    "read_your_writes" => true,
 *    "encryptionKey" => true,
 * ];
 * ```
 */

$config = [
   "url" => "file:database.db",
   "authToken" => "secrettoken",
   "syncUrl" => "libsql://database-org.turso.io",
   "syncInterval" => 5,
   "read_your_writes" => true,
   "encryptionKey" => "",
];
$db = new LibSQL($config);
