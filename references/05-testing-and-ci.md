# Testing Strategy & CI/CD Pipeline

## Testing Architecture

### Test Framework Stack

| Layer | Tool | Purpose |
|-------|------|---------|
| **PHP Testing** | Pest PHP (v4.3) | Test runner with expressive syntax |
| **PHP Unit** | PHPUnit (via Pest) | Assertion library and test structure |
| **Rust Testing** | Cargo test | Unit tests for Rust modules (if any) |

### Test Directory Structure

```
tests/
├── Pest.php                    # Global test configuration
├── TestCase.php                # Base test class with :memory: DB
├── ArchTest.php                # Architecture/linting rules
├── Unit/                       # Connection-specific unit tests
│   ├── LocalConnectionTest.php
│   ├── InMemoryConnectionTest.php
│   ├── RemoteConnectionTest.php
│   ├── OfflineWriteConnectionTest.php
│   └── EmbeddedReplicaConnectionTest.php
└── Feature/                    # Feature/integration tests
    ├── BasicCrudTest.php
    ├── TransactionTest.php
    ├── BatchOperationTest.php
    ├── PerpareStatmentTest.php     # Note: typo in filename
    ├── CoreFunctionalityTest.php
    └── SchemaOperationsTest.php
```

---

## Test Configuration

### Pest.php — Global Setup/Teardown

```php
<?php

pest()->beforeEach(function () {
    // Verify extension is loaded before every test
    expect(class_exists('LibSQL'))->toBe(true);
})->afterEach(function () {
    // Cleanup: remove any .db files created during tests
    if (file_exists("database.db")) {
        unlink("database.db");
    }

    if (file_exists('memory')) {
        unlink('memory');
    }
});
```

**Key behaviors:**
1. **Pre-test check**: Ensures `LibSQL` class exists (extension loaded)
2. **Post-test cleanup**: Deletes `database.db` and `memory` files
3. **Prevents test pollution**: Each test starts with a clean state

### TestCase.php — Base Test Class

```php
<?php

namespace Tests;

use LibSQL;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public LibSQL $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new LibSQL(':memory:');
    }

    protected function tearDown(): void
    {
        $this->db->close();
        parent::tearDown();
    }
}
```

**Design decisions:**
- **In-memory database**: Every test gets a fresh `:memory:` database
- **No file I/O**: Tests don't depend on disk state
- **Automatic cleanup**: `tearDown()` closes connection
- **Shared via `uses()`**: Feature tests use `uses(TestCase::class)` to inherit

---

## Test Categories

### 1. Unit Tests — Connection Tests

#### LocalConnectionTest.php

```php
test('connects successfully with valid DSNs', function (string $dsn) {
    expect(fn() => new LibSQL($dsn))->not->toThrow(Exception::class);
})->with([
    'libsql:dbname=database.db',
    'database.db',
    'file:database.db',
])->group('LocalConnectionTest', 'DatabaseConnectionTest');

test('fails with invalid DSNs', function (string $dsn) {
    expect(fn() => new LibSQL($dsn))->toThrow(Exception::class);
})->with([
    'libsql:database.db',     // Invalid: libsql: prefix without proper format
    '',                        // Invalid: empty string
])->group('LocalConnectionTest', 'DatabaseConnectionTest');
```

**What it tests:**
- DSN parsing for local connections
- Three valid formats: `libsql:dbname=`, bare filename, `file:` prefix
- Two invalid formats that should throw exceptions

#### InMemoryConnectionTest.php

```php
test('successfully connect in-memory database', function () {
    expect(fn() => new LibSQL(':memory:'))->not->toThrow(Exception::class);
})->group('InMemoryConnectionTest', 'DatabaseConnectionTest');

test('fails with invalid in-memory connection', function () {
    expect(fn() => new LibSQL(''))->toThrow(Exception::class);
})->group('InMemoryConnectionTest', 'DatabaseConnectionTest');
```

#### RemoteConnectionTest.php

```php
$authToken = 'eyJ0eXAi...';  // Hardcoded test token

test('remote connection using http or https protocol', function () use ($authToken) {
    expect(fn() => new LibSQL('libsql:dbname=http://127.0.0.1:8080;authToken=' . $authToken))
        ->not->toThrow(Exception::class);
})->group('RemoteConnectionTest', 'DatabaseConnectionTest');

test('remote connection using libsql protocol', function () use ($authToken) {
    expect(fn() => new LibSQL('libsql:dbname=libsql://127.0.0.1:8080;authToken=' . $authToken))
        ->not->toThrow(Exception::class);
})->group('RemoteConnectionTest', 'DatabaseConnectionTest');
```

**Note:** These tests use a hardcoded auth token and localhost URL. They test connection creation, not actual remote operations.

### 2. Feature Tests — Integration Tests

#### BasicCrudTest.php

```php
describe('CRUD Operations', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            age INTEGER
        )");
    });

    test('basic insert and retrieve', function () {
        $insertCount = $this->db->execute(
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            ['Alice', 'alice@example.com', 28]
        );

        expect($insertCount)->toBe(1)
            ->and($this->db->lastInsertedId())->toBe(1);

        $result = $this->db->query("SELECT * FROM users WHERE id = 1");
        $user = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($user[0])->toMatchArray([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'age' => 28
        ]);
    });

    test('parameter binding types', function () {
        // Named parameters
        $this->db->execute(
            "INSERT INTO users (name, age) VALUES (:name, :age)",
            [':name' => 'Bob', ':age' => 35]
        );

        // Positional parameters
        $this->db->execute(
            "UPDATE users SET email = ? WHERE id = ?",
            ['bob@example.com', 1]
        );

        $result = $this->db->query("SELECT * FROM users");
        $row = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect(count(array_keys($row[0])))->toBe(4);
    });

    test('update and delete operations', function () {
        // Insert
        $this->db->execute(
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            ['Charlie', 'charlie@example.com', 40]
        );

        // Update
        $updateCount = $this->db->execute(
            "UPDATE users SET age = ? WHERE id = ?",
            [41, 1]
        );
        expect($updateCount)->toBe(1);

        // Delete
        $deleteCount = $this->db->execute("DELETE FROM users WHERE id = 1");
        $deletedUser = $this->db->query("SELECT COUNT(*) FROM users")
            ->fetchSingle(LibSQL::LIBSQL_NUM);
        expect($deleteCount)->toBe(1)
            ->and($deletedUser[0])->toBe(0);
    });
})->group("BasicCrudTest", "Feature");
```

**What it tests:**
- INSERT with positional parameters
- INSERT with named parameters (`:name`, `:age`)
- UPDATE with mixed parameters
- DELETE operations
- `lastInsertedId()` accuracy
- `fetchArray()` with `LIBSQL_ASSOC` mode
- `fetchSingle()` with `LIBSQL_NUM` mode

#### TransactionTest.php

```php
describe('Transactions', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE accounts (
            id INTEGER PRIMARY KEY,
            balance INTEGER
        )");
    });

    test('atomic transaction commit', function () {
        $trx = $this->db->transaction();

        $trx->execute("INSERT INTO accounts (balance) VALUES (1000)");
        $trx->execute("INSERT INTO accounts (balance) VALUES (2000)");

        expect($trx->isAutocommit())->toBeFalse();
        $trx->commit();

        $result = $this->db->query("SELECT SUM(balance) FROM accounts");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe(3000);
    });

    test('transaction rollback', function () {
        $trx = $this->db->transaction();

        $trx->execute("INSERT INTO accounts (balance) VALUES (500)");
        $trx->rollback();

        $result = $this->db->query("SELECT COUNT(*) FROM accounts");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe(0);
    });
})->group('TransactionTest', 'Feature');
```

**What it tests:**
- Transaction creation (default DEFERRED)
- Multiple executes within transaction
- `isAutocommit()` returns false during transaction
- `commit()` persists all changes atomically
- `rollback()` undoes all changes

#### BatchOperationTest.php

```php
describe('Batch Operations', function () {
    test('execute multiple statements', function () {
        $batch = "
            CREATE TABLE cities (
                id INTEGER PRIMARY KEY,
                name TEXT,
                population INTEGER
            );

            INSERT INTO cities (name, population) VALUES
                ('Paris', 2161000),
                ('London', 8982000),
                ('Berlin', 3769000);
        ";

        $success = $this->db->executeBatch($batch);
        expect($success)->toBeTrue();

        $result = $this->db->query("SELECT COUNT(*) FROM cities");
        expect($result->fetchArray(LibSQL::LIBSQL_NUM)[0][0])->toBe(3);
    });
})->group('BatchOperationTest', 'Feature');
```

#### PerpareStatmentTest.php (Prepared Statements)

```php
describe('Prepared Statements', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            name TEXT,
            price REAL
        )");
    });

    test('reusable prepared statements', function () {
        $stmt = $this->db->prepare("INSERT INTO products (name, price) VALUES (?, ?)");

        $products = [
            ['Laptop', 999.99],
            ['Phone', 699.99],
            ['Tablet', 399.99]
        ];

        foreach ($products as $product) {
            $stmt->bindPositional($product);
            $stmt->execute();
            $stmt->reset();
        }

        $result = $this->db->query("SELECT COUNT(*) FROM products");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe(3);
    });

    test('parameter binding validation', function () {
        $stmt = $this->db->prepare("INSERT INTO products (id, name, price) VALUES (:id, :name, :price)");

        expect($stmt->parameterCount())->toBe(3)
            ->and($stmt->parameterName(1))->toBe('id')
            ->and($stmt->parameterName(2))->toBe('name')
            ->and($stmt->parameterName(3))->toBe('price');

        $stmt->bindNamed([':id' => 1, ':name' => 'Test', ':price' => 9.99]);
        $stmt->execute();

        $result = $this->db->query("SELECT name FROM products WHERE id = 1");
        expect($result->fetchSingle(LibSQL::LIBSQL_ASSOC)['name'])->toBe('Test');
    });
})->group("PreparedStatementTest", "Feature");
```

**What it tests:**
- Statement preparation and reuse
- `bindPositional()` with multiple executions
- `reset()` clearing parameters
- `parameterCount()` accuracy
- `parameterName()` stripping prefix characters
- `bindNamed()` with named parameters

#### CoreFunctionalityTest.php

```php
describe('LibSQL Core Functionality', function () {
    it('database version information', function () {
        $version = $this->db::version();
        expect($version)
            ->toBeString()
            ->toContain('LibSQL Core Version')
            ->toContain('LibSQL PHP Extension Version');
    });

    it('connection status and autocommit mode', function () {
        expect($this->db->isAutocommit())->toBeTrue();
    });
})->group('CoreFunctionalityTest', 'Feature');
```

#### SchemaOperationsTest.php

```php
describe('Schema Operations', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE,
            age INTEGER CHECK(age >= 18)
        )");
    });

    it('verify table structure', function () {
        $result = $this->db->query("PRAGMA table_info(users)");

        $columns = [];
        foreach ($result->fetchArray(LibSQL::LIBSQL_ASSOC) as $row) {
            $columns[$row['name']] = [
                'type' => $row['type'],
                'notnull' => $row['notnull'],
                'pk' => $row['pk']
            ];
        }

        expect($columns)->toMatchArray([
            'id' => ['type' => 'INTEGER', 'notnull' => 0, 'pk' => 1],
            'name' => ['type' => 'TEXT', 'notnull' => 1, 'pk' => 0],
            'email' => ['type' => 'TEXT', 'notnull' => 0, 'pk' => 0],
            'age' => ['type' => 'INTEGER', 'notnull' => 0, 'pk' => 0]
        ]);
    });

    it('table constraints enforcement', function () {
        // UNIQUE constraint
        $this->db->execute("INSERT INTO users (name, email, age) VALUES ('John', 'john@test.com', 25)");
        expect(fn() => $this->db->execute("INSERT INTO users (name, email, age) VALUES ('Jane', 'john@test.com', 30)"))
            ->toThrow(Exception::class);

        // CHECK constraint
        expect(fn() => $this->db->execute("INSERT INTO users (name, age) VALUES ('Child', 17)"))
            ->toThrow(Exception::class);

        // NOT NULL constraint
        expect(fn() => $this->db->execute("INSERT INTO users (email, age) VALUES ('test@test.com', 20)"))
            ->toThrow(Exception::class);
    });
})->group('SchemaOperationsTest', 'Feature');
```

**What it tests:**
- `PRAGMA table_info()` for schema introspection
- Column types, NOT NULL, PRIMARY KEY
- UNIQUE constraint enforcement
- CHECK constraint enforcement
- NOT NULL constraint enforcement

### 3. Architecture Tests

#### ArchTest.php

```php
arch('it should not use any debugging functions')
    ->expect([
        'dd',
        'debug_backtrace',
        'die',
        'dump',
        'echo',
        'eval',
        'exec',
        'exit',
        'passthru',
        'phpinfo',
        'print_r',
        'proc_open',
        'ray',
        'shell_exec',
        'system',
        'var_dump',
    ])
    ->each->not->toBeUsed();
```

**Purpose:** Ensures production PHP code doesn't contain debugging/development functions. This is a code quality gate.

---

## Running Tests

### Commands

```bash
# Run all tests
composer test
# Equivalent to:
vendor/bin/pest

# Run feature tests only
composer test:feature
# Equivalent to:
vendor/bin/pest --group=Feature

# Run specific test group
vendor/bin/pest --group=BasicCrudTest
vendor/bin/pest --group=TransactionTest
vendor/bin/pest --group=DatabaseConnectionTest

# Run specific test file
vendor/bin/pest tests/Feature/BasicCrudTest.php

# Run with verbose output
vendor/bin/pest --verbose

# Run with coverage (if xdebug enabled)
vendor/bin/pest --coverage
```

### Test Groups

Tests are tagged with multiple groups for flexible filtering:

| Group | Tests |
|-------|-------|
| `Feature` | All feature tests |
| `DatabaseConnectionTest` | All connection tests (unit) |
| `LocalConnectionTest` | Local connection tests |
| `RemoteConnectionTest` | Remote connection tests |
| `InMemoryConnectionTest` | In-memory connection tests |
| `BasicCrudTest` | CRUD operations |
| `TransactionTest` | Transaction tests |
| `BatchOperationTest` | Batch operations |
| `PreparedStatementTest` | Prepared statement tests |
| `CoreFunctionalityTest` | Core API tests |
| `SchemaOperationsTest` | Schema/constraint tests |

---

## CI/CD Pipeline

### GitHub Workflow: `cross-compile.yml`

**Trigger:** On release creation (`release: types: [created]`)

**Purpose:** Cross-compile the extension for all supported platforms and upload binaries to the GitHub release.

### Build Matrix

The workflow builds **60+ combinations**:

| Platform | Architectures | PHP Versions | Thread Safety | Total |
|----------|--------------|--------------|---------------|-------|
| **Linux** | x86_64 | 8.1, 8.2, 8.3, 8.4, 8.5 | TS, NTS | 10 |
| **macOS 14** | x86_64, aarch64 | 8.1, 8.2, 8.3, 8.4, 8.5 | TS, NTS | 20 |
| **macOS 15** | x86_64, aarch64 | 8.1, 8.2, 8.3, 8.4, 8.5 | TS, NTS | 20 |
| **Windows** | x86_64 | 8.1, 8.2, 8.3, 8.4, 8.5 | TS, NTS (partial) | 10+ |

### Build Steps

```yaml
steps:
  # 1. Checkout code
  - name: Checkout
    uses: actions/checkout@v6

  # 2. Extract version from git tag
  - name: Get the release version from the tag
    run: echo "VERSION=${GITHUB_REF#refs/tags/}" >> $GITHUB_ENV

  # 3. Install Rust
  - name: Install Rust (MacOS and Linux)
    uses: dtolnay/rust-toolchain@nightly
    with:
      targets: ${{ matrix.target }}

  # 4. Install Rust (Windows — uses stable, not nightly!)
  - name: Install Rust (Windows)
    uses: dtolnay/rust-toolchain@stable
    with:
      targets: ${{ matrix.target }}

  # 5. Setup PHP
  - name: Setup PHP
    uses: shivammathur/setup-php@v2
    with:
      php-version: ${{ matrix.php-versions }}

  # 6. Build extension
  - name: Build Extension
    run: cargo build --release --target ${{ matrix.target }}

  # 7. Create archive
  - name: Build archive
    run: |
      binary_name="libsql_php"
      version=${GITHUB_REF#refs/tags/turso-php-extension/}
      dirname="$binary_name-$version-php-${{ matrix.php-versions }}-${{ matrix.phpts }}-${{ matrix.build }}"
      mkdir -p "$dirname"

      # Platform-specific binary handling
      if [[ "${{ matrix.os }}" == *"macos"* ]]; then
        cp "target/.../lib${binary_name}.dylib" "$dirname"
        cp "target/.../lib${binary_name}.dylib" "$dirname/lib${binary_name}.so"  # Copy as .so too
      elif [[ "${{ matrix.os }}" == "windows-latest" ]]; then
        mv "target/.../${binary_name}.dll" "$dirname"
      else
        mv "target/.../lib${binary_name}.so" "$dirname"
      fi

      # Include stubs
      mv "libsql_php_extension.stubs.php" "$dirname"

      # Archive
      if [[ "${{ matrix.os }}" == "windows-latest" ]]; then
        7z a "$dirname.zip" "$dirname"
      else
        tar -czf "$dirname.tar.gz" "$dirname"
      fi

  # 8. Upload to GitHub release
  - name: Release
    uses: softprops/action-gh-release@v1
    with:
      files: ${{ env.ASSET }}
```

### Artifact Naming Convention

```
libsql_php-{version}-php-{php_version}-{thread_safety}-{build}.{ext}

Examples:
libsql_php-1.6.2-php-8.3-nts-x86_64-unknown-linux-gnu.tar.gz
libsql_php-1.6.2-php-8.4-ts-aarch64-apple-darwin-macos-15.tar.gz
libsql_php-1.6.2-php-8.1-nts-x86_64-pc-windows-msvc.zip
```

### Platform-Specific Notes

| Platform | Binary Extension | Notes |
|----------|-----------------|-------|
| Linux | `.so` | Standard shared object |
| macOS | `.dylib` + `.so` copy | macOS uses `.dylib`, but PHP expects `.so` — both are included |
| Windows | `.dll` | Dynamic link library |

### Rust Toolchain Difference

```yaml
# MacOS and Linux: nightly
- name: Install Rust (MacOS and Linux)
  uses: dtolnay/rust-toolchain@nightly

# Windows: stable
- name: Install Rust (Windows)
  uses: dtolnay/rust-toolchain@stable
```

**Why?** The `abi_vectorcall` feature (used in `lib.rs`) requires nightly Rust. However, on Windows this feature may not be available or needed, so stable is used instead.

---

### GitHub Workflow: `update-release-metadata.yml`

**Purpose:** Updates a remote JSON store with release metadata (version, download links, etc.) for the installer to use.

**Mechanism:**
```python
# scripts/release_metadata.py
import requests

def update_store(storage_id, file_content):
    headers = {
        "Accept": "application/json",
        "User-Agent": "GitHub Actions",
    }

    update_data = {
        "data": file_content,
        "source": "GitHub Actions"
    }

    response = requests.put(
        f"https://json-storage-six.vercel.app/{storage_id}",
        json=update_data,
        headers=headers
    )
```

**Used by:** The `turso-php-installer` Composer package to know which extension versions are available for download.

---

## Test Coverage Analysis

### What's Covered

| Feature | Test Coverage |
|---------|--------------|
| Local connection | ✅ DSN parsing, file paths |
| In-memory connection | ✅ Basic connectivity |
| Remote connection | ✅ Connection creation (no actual remote ops) |
| CRUD operations | ✅ INSERT, SELECT, UPDATE, DELETE |
| Parameter binding | ✅ Named, positional |
| Transactions | ✅ Commit, rollback, autocommit |
| Batch operations | ✅ Multi-statement execution |
| Prepared statements | ✅ Prepare, bind, execute, reset, finalize |
| Schema operations | ✅ PRAGMA, constraints |
| Core functionality | ✅ Version, autocommit |
| Architecture rules | ✅ No debug functions |

### What's NOT Covered

| Feature | Gap | Risk |
|---------|-----|------|
| **Remote replica** | No integration test with actual remote | Medium |
| **Offline writes** | No test for queueing/sync flow | High |
| **sqld offline writes** | No test for dual-connection mode | High |
| **Encryption** | No test for encrypted databases | Medium |
| **Extension loading** | No test for SQLite extensions | Low |
| **Webhook/captureIt** | No test for event capture | Low |
| **Error handling** | No test for error scenarios | Medium |
| **Concurrency** | No test for concurrent operations | Low (PHP model) |
| **Large datasets** | No performance/benchmark tests | Medium |

### Test Philosophy

The test suite follows these principles:
1. **Fast execution**: All tests use `:memory:` — no disk I/O
2. **Isolation**: Each test gets a fresh database
3. **Cleanup**: `afterEach` removes any created files
4. **Integration-focused**: Tests actual PHP→Rust→libSQL flow, not mocked
5. **No remote dependencies**: Remote tests only check connection creation, not actual cloud operations

---

## Docker Development Environment

### compose.yaml

```yaml
services:
  dev:
    container_name: turso-client-php-development
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - 6969:6969
    volumes:
      - ./php-server/php.ini:/etc/php/8.5/cli/php.ini
      - ./:/app
    profiles: ["dev"]

  dev-arm64:
    container_name: turso-client-php-development-arm64
    build:
      context: .
      dockerfile: Dockerfile.arm64
    ports:
      - 6969:6969
    volumes:
      - ./php-server/php.ini:/etc/php/8.5/cli/php.ini
      - ./:/app
    profiles: ["arm64"]
```

### Dockerfile Architecture

**Two-stage build:**

```
Stage 1: Builder (Rust)
├── debian:bookworm-slim
├── Install: curl, build-essential, pkg-config, libssl-dev
├── Install Rust nightly via rustup
└── Verify: rustc --version, cargo --version

Stage 2: Final (PHP + Rust)
├── debian:bookworm-slim
├── Install: PHP 8.5, PHP 8.5-dev, git, cmake, clang
├── Add Ondrej Sury PHP PPA
├── Copy Rust from builder stage
├── WORKDIR /app
├── COPY . .
├── RUN cargo build
├── EXPOSE 6969
└── CMD: php -S 0.0.0.0:6969 -t php-server
```

### php-server/ Directory

```
php-server/
├── index.php       # Entry point for PHP dev server
└── php.ini         # PHP configuration (mounted into container)
```

### Usage

```bash
# Start development environment (x86_64)
make compose/up

# Start development environment (ARM64 / Apple Silicon)
make compose-arm64/up

# View logs
make compose/logs

# Stop
make compose/down

# Restart
make compose/restart
```

### Port 6969

The PHP development server runs on port 6969. This is used for:
- Testing examples in `examples/` directory
- Interactive development
- Quick verification of extension functionality
