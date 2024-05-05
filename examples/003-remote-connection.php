<?php

// Option: 1 Standard DSN Connection with libsql://
$db = new LibSQL("libsql:dbname=libsql://database-org.turso.io;authToken=random-token");

// Option: 2 Standard DSN Connection with https://
$db = new LibSQL("libsql:dbname=https://database-org.turso.io;authToken=random-token");
