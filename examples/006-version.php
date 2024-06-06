<?php

// Creating a new LibSQL instance
$db = new LibSQL(":memory:");

// Retrieving the version of the LibSQL library
$version = LibSQL::version();
echo $version . PHP_EOL;
