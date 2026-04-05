# Development Progress Tracker

> Auto-managed by Qwen Code. Check this file at the start of every session.

## Enhancement Roadmap

| # | Enhancement | Priority | Status | Session |
|---|---|---|---|---|
| 1 | Remove `.unwrap()` usage — replace with `?` propagation and proper `PhpException` mapping | 🔴 Critical | pending | — |
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

### Session: 2026-04-05
- **Completed**: `style: normalize line endings to lf across project` (87 files, CRLF→LF normalization + typo fix in test filename)

---

## How to Update This File

When starting work on an enhancement:
1. Change its status from `pending` to `in_progress`
2. Record the session date
3. When done, change to `completed` and add a brief note
