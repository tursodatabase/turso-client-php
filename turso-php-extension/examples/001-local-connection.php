<?php

// Option: 1 Standard DSN Connection
$db = new LibSQL("libsql:dbname=database.db");

// Option: 2 Standard SQLite Connection
$db = new LibSQL("database.db");

// Option: 3 Standard LibSQL Connection
$db = new LibSQL("file:database.db");

// Error: PHP Fatal error:  Uncaught Exception: Failed to parse DSN
$db = new LibSQL("libsql:database.db");
$db = new LibSQL("");
