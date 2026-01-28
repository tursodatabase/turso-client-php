<p align="center">
  <a href="https://discord.gg/turso">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://i.imgur.com/UhuW3zm.png">
      <source media="(prefers-color-scheme: light)" srcset="https://i.imgur.com/vljWbfr.png">
      <img alt="Shows a black logo in light color mode and a white one in dark color mode." src="https://i.imgur.com/vGCC0I4.png">
    </picture>
  </a>
</p>

<p align="center">
  <img alt="Turso + PHP" src="https://i.imgur.com/zRVfWL3.png" width="1000">
    <h1 align="center" style="border: 0;margin-bottom: 0;">Turso + PHP (Community SDK)</h1>
    <p align="center">
      SQLite for Production. Powered by <a href="https://turso.tech/libsql">libSQL</a>.
    </p>
</p>

<p align="center">
  <a href="LICENSE">
    <picture>
      <img src="https://img.shields.io/github/license/tursodatabase/turso-client-php?color=01c3b0" alt="MIT License" />
    </picture>
  </a>
  <a href="https://tur.so/discord-php">
    <picture>
      <img src="https://img.shields.io/discord/933071162680958986?color=01c3b0" alt="Discord" />
    </picture>
  </a>
  <a href="#contributors">
    <picture>
      <img src="https://img.shields.io/github/contributors/tursodatabase/turso-client-php?color=01c3b0" alt="Contributors" />
    </picture>
  </a>
  <a href="https://github.com/tursodatabase/turso-client-php/releases">
    <picture>
      <img src="https://img.shields.io/github/downloads/tursodatabase/turso-client-php/total.svg?color=01c3b0" alt="Total downloads" />
    </picture>
  </a>
  <a href="https://github.com/tursodatabase/turso-client-php/releases">
    <picture>
      <img src="https://img.shields.io/github/v/release/tursodatabase/turso-client-php?color=01c3b0" alt="Release" />
    </picture>
  </a>
</p>

## PHP Versions

| PHP Versions  | Build Versions  |
| ------------- | ----------------|
| 8.1           | TS / NTS        |
| 8.2           | TS / NTS        |
| 8.3           | TS / NTS        |
| 8.4           | TS / NTS        |
| 8.5           | TS / NTS        |

**Note**: TS (Thread Safe), NTS (Non Thread-Safe). **Support for:** Linux/ Mac/Darwin / Windows/WSL

---

## Installation

Installing the extension should be not complecated, it's easy and like using **Composer Package Installer**.

<details>
  <summary><b>Auto Installer</b></summary>
  <code>turso-php-installer</code> is a Composer package capable of executing various commands related to libSQL, simplifying the development process, and making it easier to simulate in a local environment.

  ```bash
  composer global require darkterminal/turso-php-installer
  ```
  Add to `PATH` variable:
  ```bash
  export COMPOSER_BIN_DIR=$(composer config --global home)/vendor/bin
  ```

  We have two options to install the extension using the installer:

  **Interactive Mode**
  ```bash
  turso-php-installer install
  ```
  <img src="https://i.imgur.com/DCqTg3l.gif" />

  ---

  **Non-interactive Mode**
  ```bash
  turso-php-installer install -n --php-version=8.3
  ```
  <img src="https://i.imgur.com/s60hh5T.gif" />
</details>

<details>
  <summary><b>Manual Installation<b></summary>

  Download the latest build extension/driver binary you can see at <a href="https://github.com/tursodatabase/turso-client-php/releases">release</a> page.

  - Extract the archive
  - Locate somewhere in your machine
  - Copy a relative path that address that extension/driver
  - Open `php.ini` search `;extension` if you using `nano` (`ctrl+w`) then searching for it
  - add in the next-line `extension=liblibsql_php.so` (in Linux) without `;` at the begining

  Check on your console/terminal

  ```bash
  php --m | grep libsql
  ```
</details>

## Development


### Requirements

- Unix/Unix Like
- PHP >= 8.1
- [Rustlang](https://www.rust-lang.org/tools/install)
- Git
- Docker & Docker Compose (Docker Development)

Fork it First and Build from source:

```bash
# Clone
git clone git@github.com:<username>/turso-client-php.git

# Move to project directory
cd turso-client-php

# Make sure you have rust nightly toolchain
rustup toolchain install nightly
rustup default nightly 

# Build the binary
cargo build # or cargo build --release for production
```

Or using Develop inside Docker Container

```bash
# Clone
git clone git@github.com:<username>/turso-client-php.git

# Move to project directory
cd turso-client-php

# Build the binary
make compose/up
```

if using `arm64` then use this command:

```bash
make compose-arm64/up
```

For all make command check `make help`

## Contributors

![Contributors](https://contrib.nn.ci/api?no_bot=true&repo=tursodatabase/turso-client-php)


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
