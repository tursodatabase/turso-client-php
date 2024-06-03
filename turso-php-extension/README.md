<p align="center">
    <img src="art/elpha-cover.png" width="1000" />
</p>

<p align="center" style="font-size: 24px;font-weight:bold;margin:0;">Native <a href="https://turso.tech/libsql">libSQL</a> Driver for PHP</p>
<p align="center">
    Build by Handsome Person from 🇮🇩 <br /> 
    <a href="https://twitter.com/panggilmeiam" target="_blank">@panggilmeiam</a> or <a href="https://github.com/darkterminal" target="_blank">.darkterminal</a>
</p>

<p align="center">
  <a href="https://turso.tech"><strong>Turso</strong></a> ·
  <a href="https://darkterminal.mintlify.app/dark-extensions/introduction#quickstart"><strong>Quickstart</strong></a> ·
  <a href="https://darkterminal.mintlify.app/dark-extensions/local-connection#usage-example"><strong>Examples</strong></a> ·
  <a href="https://darkterminal.mintlify.app/dark-extensions/introduction"><strong>Docs</strong></a> ·
  <a href="https://discord.com/invite/4B5D7hYwub"><strong>Discord</strong></a> ·
  <a href="https://blog.turso.tech/"><strong>Blog &amp; Tutorials</strong></a>
</p>

---

LibSQL PHP Driver/Extension/Whatever designed to seamlessly handle local, remote, and remote replica/embedded replica connections, offering versatility and efficiency for your application's data management needs. With an intuitive interface and flexible configuration options, LibSQL empowers developers to effortlessly integrate database operations into your PHP projects.

## Download

Download the latest build extension/driver binary you can see at [Release](https://github.com/darkterminal/libsql-extension/releases) page. It's available for:
- Linux
- Mac/Darwin
- Windows (still struggle)

## Installation

- Extract the archive
- Locate somewhere in your machine
- Copy a relative path that address that extension/driver
- Open `php.ini` search `;extension` if you using `nano` (`ctrl+w`) then searching for it
- add in the next-line `extension=liblibsql_php.so` (in Linux) without `;` at the begining

Check on your console/terminal

```bash
php --m | grep libsql
```

## Quickstart

Remember, this is not a library or ORM, this is the native extension for LibSQL.

```php
<?php

// Instanciate
$db = new LibSQL(":memory:");

// Create table
$sql = "CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, age INTEGER)";
$db->execute($sql);

// Insert data
$db->execute("INSERT INTO users (name. age) VALUES ('Diana Hooggan', 24)");

// Read data
$results = $db->query("SELECT * FROM users");

// Display data
foreach ($results['rows'] as $row) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Age: " . $row['age'] . "\n";
}

// Close database
$db->close();
```

How easy is that!?
