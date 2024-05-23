# Introduction to LibSQL PHP Extension

LibSQL PHP Extension is a powerful database connectivity tool designed to seamlessly handle both local and remote connections, offering versatility and efficiency for your application's data management needs. With an intuitive interface and flexible configuration options, LibSQL empowers developers to effortlessly integrate database operations into their PHP projects.

## Local Connection

The local connection feature of LibSQL simplifies database access within the same environment. Developers can establish connections using standard DSN strings or opt for more straightforward LibSQL connections. With support for various configurations, including encryption, LibSQL ensures secure and efficient data handling.

## Remote Connection

LibSQL extends its capabilities beyond local databases, facilitating remote connections effortlessly. Whether through the standard DSN connection with 'libsql://' or direct HTTPS access, developers can securely connect to remote databases, enhancing accessibility and scalability for their applications.

## Remote Replica Connection

For distributed environments requiring synchronization and replication, LibSQL offers a robust solution with its **Remote Replica Connection** feature. By configuring key parameters such as URL, authentication token, sync URL, and synchronization interval, developers can establish resilient connections for seamless data replication across distributed systems.

With LibSQL PHP Extension, developers can streamline database operations, ensuring reliability, security, and performance in their PHP applications. Whether managing local databases or orchestrating complex distributed systems, LibSQL empowers developers with the tools they need to build robust and scalable solutions.


## Table Of Contents
1. [Quickstart Guide](quick-start.md)
2. [LibSQL Configuration Options](000-configuration.md)
    - [Local Connection](001-local-connection.md)
    - [In-Memory Connection](002-memory-connection.md)
    - [Remote Connection](003-remote-connection.md)
    - [Remote Replica Connection](004-remote-replica-connection.md)
3. [LibSQL Class](005-LibSQL-class.md)
    - [Version](006-version.md)
    - [Changes](007-changes.md)
    - [Is Auto Commit](008-isAutocommit.md)
    - [Execute](009-execute.md)
    - [Execute Batch](010-executeBatch.md)
    - [Query](011-query.md)
    - [Transaction](012-transaction.md)
    - [Prepare](013-prepare.md)
    - [Close](014-close.md)
    - [Sync](015-sync.md)
4. [LibSQLStatement](016-LibSQLStatement.md)
5. [LibSQLTransaction](017-LibSQLTransaction.md)
