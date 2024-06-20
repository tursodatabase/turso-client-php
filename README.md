<p align="center">
  <a href="https://docs.turso.tech/sdk/php/quickstart">
    <img alt="Turso + PHP" src="https://github.com/tursodatabase/turso-client-php/assets/950181/f007cbca-02f7-46c4-a502-392484e76bc7" width="1000">
    <h3 align="center">Turso + PHP</h3>
  </a>
</p>
<p align="center">
  SQLite for Production. Powered by <a href="https://turso.tech/libsql">libSQL</a>.
</p>

<p align="center">
  <a href="https://turso.tech"><strong>Turso</strong></a> ·
  <a href="https://docs.turso.tech/quickstart"><strong>Quickstart</strong></a> ·
  <a href="/examples"><strong>Examples</strong></a> ·
  <a href="https://docs.turso.tech"><strong>Docs</strong></a> ·
  <a href="https://discord.gg/turso"><strong>Discord</strong></a> ·
  <a href="https://turso.tech/blog"><strong>Blog &amp; Tutorials</strong></a>
</p>

<p align="center">
  <a href="https://discord.com/invite/4B5D7hYwub">
    <img src="https://dcbadge.vercel.app/api/server/4B5D7hYwub?style=flat" alt="discord activity" title="join us on discord" />
  </a>
</p>
    
---

## Documentation

1. [Turso Quickstart](https://docs.turso.tech/quickstart) &mdash; Learn how create and connect your first database.
2. [SDK Quickstart](https://docs.turso.tech/sdk/php/quickstart) &mdash; Learn how to install and execute queries using the libSQL client.
3. [SDK Reference](https://docs.turso.tech/sdk/php/reference) &mdash; Dive deeper with the libSQL SDK reference and examples.

### What is Turso?

[Turso](https://turso.tech) is a SQLite-compatible database built on [libSQL](https://docs.turso.tech/libsql), the Open Contribution fork of SQLite. It enables scaling to hundreds of thousands of databases per organization and supports replication to any location, including your own servers, for zero-latency reads.

Learn more about what you can do with Turso:

-   [Embedded Replicas](https://docs.turso.tech/features/embedded-replicas)
-   [Multi-DB Schemas](https://docs.turso.tech/features/multi-db-schemas)
-   [ATTACH Database](https://docs.turso.tech/features/attach-database)
-   [Platform API](https://docs.turso.tech/features/platform-api)
-   [Data Edge](https://docs.turso.tech/features/data-edge)
-   [Branching](https://docs.turso.tech/features/branching)
-   [Point-in-Time Recovery](https://docs.turso.tech/features/point-in-time-recovery)
-   [Scale to Zero](https://docs.turso.tech/features/scale-to-zero)

## Download

Download the latest build extension/driver binary you can see at [Release](https://github.com/tursodatabase/turso-client-php/releases) page. 

It's available for:

- Linux
- Mac/Darwin
- Windows (WSL) / [Windows x64](docs/WINDOWS_REQUIREMENT.md)

## PHP Versions

- PHP 8.0
- PHP 8.1
- PHP 8.2
- PHP 8.3

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

## Installer Script

Install Turso Client PHP / libSQL Extension without worry using installer script, only for Linux and MacOS [Turso PHP Installer](https://github.com/darkterminal/turso-php-installer)