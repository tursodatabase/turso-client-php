# Quick Start Guide for LibSQL PHP Extension

1. **Local Connection:**

   Establishing a connection to a local database is straightforward with LibSQL. You have three options:

   a. **Standard DSN Connection:** If you're using a DSN string, use the following format:
      ```php
      $db = new LibSQL("libsql:dbname=database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");
      ```
      
   b. **Standard SQLite Connection:** For direct SQLite connections, simply provide the database file name:
      ```php
      $db = new LibSQL("database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");
      ```
      
   c. **Standard LibSQL Connection:** Alternatively, you can specify the file protocol explicitly:
      ```php
      $db = new LibSQL("file:database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");
      ```

2. **Remote Connection:**

   Connecting to a remote database is equally effortless. Choose between two options:

   a. **Standard DSN Connection with 'libsql://':**
      ```php
      $db = new LibSQL("libsql:dbname=libsql://database-org.turso.io;authToken=random-token");
      ```
      
   b. **Standard DSN Connection with 'https://':**
      ```php
      $db = new LibSQL("libsql:dbname=https://database-org.turso.io;authToken=random-token");
      ```

3. **Remote Replica Connection:**

   To set up a replica connection for distributed systems, follow these steps:

   a. Define the configuration array with the required parameters:
      ```php
      $config = [
         "url" => "file:database.db",
         "authToken" => "secrettoken",
         "syncUrl" => "libsql://database-org.turso.io",
         "syncInterval" => 5,
         "read_your_writes" => true,
         "encryptionKey" => "",
      ];
      ```

   b. Instantiate a new LibSQL object with the configuration array:
      ```php
      $db = new LibSQL($config);
      ```

With this Quick Start guide, you're ready to seamlessly integrate LibSQL PHP Extension into your projects, whether for local, remote, or distributed database connections. Enjoy the simplicity and power of LibSQL for efficient database management in your PHP applications!
