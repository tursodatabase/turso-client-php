# Installation & Configuration

## Requirements

Before installing **Turso Client PHP**, ensure you have the following:

* **PHP** ≥ 8.1 (supports 8.1, 8.2, 8.3, 8.4, 8.5)
* **Composer** (for installer package)
* **Rust nightly toolchain** (if building from source)
* **Git**
* **Docker & Docker Compose** (optional, for containerized development)

Supported platforms:

* ✅ Linux (`x86_64`, `arm64`)
* ✅ macOS
* ✅ Windows / WSL

---

## Installation Methods

There are multiple ways to install the extension depending on your environment and workflow.

---

### 1. Using the Composer Installer (Recommended)

The easiest way is via the official installer package:

```bash
composer global require darkterminal/turso-php-installer
```

Add the Composer global bin directory to your `PATH`:

```bash
export COMPOSER_BIN_DIR=$(composer config --global home)/vendor/bin
export PATH="$COMPOSER_BIN_DIR:$PATH"
```

Run the installer:

#### Interactive Mode

```bash
turso-php-installer install
```

This will guide you step-by-step.

#### Non-Interactive Mode

```bash
turso-php-installer install -n --php-version=8.3
```

You can specify PHP versions (`8.1`, `8.2`, `8.3`, `8.4`, `8.5`).

---

### 2. Building from Source

If you prefer to build directly:

```bash
# Clone the repository
git clone https://github.com/tursodatabase/turso-client-php.git
cd turso-client-php

# Ensure Rust nightly is available
rustup toolchain install nightly
rustup default nightly

# Build the extension
cargo build
```

The compiled extension (`.so` / `.dll`) will be placed in `target/`.

---

### 3. Docker Development

You can also run inside a Docker container:

```bash
make compose/up
```

For ARM64 development environments:

```bash
make compose-arm64/up
```

For available commands:

```bash
make help
```

---

## Configuration

After installation, enable the extension in your `php.ini`:

```ini
extension=libsql_php.so
```

Verify the extension is loaded:

```bash
php -m | grep libsql
```

---

## Next Steps

* 👉 [Quick Start](quick-start.md) — run your first queries with Turso Client PHP
* 👉 [Connections](local-connection.md) — learn how to connect locally, in-memory, or to remote databases
