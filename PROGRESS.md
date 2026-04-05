# Development Progress Tracker

> Auto-managed by Qwen Code. Check this file at the start of every session.

## Enhancement Roadmap

| # | Enhancement | Priority | Status | Session |
|---|---|---|---|---|
| 1 | Remove `.unwrap()` usage — replace with `?` propagation and proper `PhpException` mapping | 🔴 Critical | ✅ COMPLETED | 2026-04-05 |
| 2 | Implement `LibSQLResult::finalize()` — declared in stubs but missing in `src/result.rs` | 🔴 High | pending | — |
| 3 | Batch prepared statement execution — `executeBatch` on `LibSQLStatement` | 🟡 High | pending | — |
| 4 | Expand test coverage for uncovered methods | 🟡 High | pending | — |
| 5 | Offline write mode flow testing — sync, connectivity, queue behavior | 🟡 High | pending | — |
| 6 | DSN parsing improvement — extract `syncInterval`, `read_your_writes`, `encryptionKey` | 🟢 Medium | pending | — |
| 7 | Add PHPStan static analysis — validate stubs against actual Rust implementation | 🟢 Medium | pending | — |
| 8 | `LIBSQL_LAZY` fetch mode with proper Iterator streaming | 🟢 Medium | pending | — |
| 9 | Connection pooling support for production PHP apps | 🟢 Medium | pending | — |
| 10 | Backup API (`backup_to_file`) | 🔵 Low | pending | — |
| 11 | Flags bitwise handling for local connections | 🔵 Low | pending | — |

## Session Log

### Session: 2026-04-05 (Critical: Remove .unwrap())
- **Completed**: `crit: remove all .unwrap() calls — replace with proper error handling` (95 instances across 15+ files)
  - Replaced all `.unwrap()` calls with `?` operator and `PhpException` mapping
  - Changed `runtime()` function to return `Result<&'static Runtime, PhpException>` 
  - Updated all 41 callers of `runtime()` to extract runtime before `block_on()`
  - Mutex locks now use `.map_err()` to convert poison errors to `PhpException`
  - Option types use `.ok_or_else()` for proper error propagation
  - LibSQL operations use `.map_err()` for error mapping
  - Files modified: `lib.rs`, `statement.rs`, `transaction.rs`, `result.rs`, all `providers/`, all `hooks/`, all `utils/`
  - Build verification blocked by missing `php-config` (Herd PHP installation limitation)

---

## How to Update This File

When starting work on an enhancement:
1. Change its status from `pending` to `in_progress`
2. Record the session date
3. When done, change to `completed` and add a brief note
