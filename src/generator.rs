#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
extern crate ext_php_rs;
use std::cell::RefCell;
use std::rc::Rc;

use ext_php_rs::convert::IntoZval;
use ext_php_rs::exception::PhpException;
use ext_php_rs::{prelude::*, types::Zval};

use crate::utils::runtime::{convert_libsql_value_to_zval, runtime};

/// Streaming position state:
/// - `None` — the next row has not been fetched yet.
/// - `Some(None)` — the row source is exhausted.
/// - `Some(Some(zval))` — the fetched current row.
type StreamSlot = Option<Option<Zval>>;

#[php_class]
pub struct LibSQLIterator {
    /// PHP-array-backed source (legacy behavior of `new LibSQLIterator($array)`).
    data: Option<Rc<Zval>>,
    /// libsql row-set-backed source, used for lazily-streamed `LIBSQL_LAZY`
    /// results. Row fetching needs `&mut Rows`, so it sits behind a `RefCell`.
    rows: Option<RefCell<libsql::Rows>>,
    /// Cached streaming position; see [`StreamSlot`].
    stream: RefCell<StreamSlot>,
    counter: i32,
}

impl LibSQLIterator {
    /// Creates an iterator that lazily pulls rows out of a libsql row set
    /// instead of materializing the whole result set first.
    ///
    /// Not exposed to PHP; used internally by `LibSQLResult::fetchArray` for
    /// the `LIBSQL_LAZY` mode.
    pub fn from_rows(rows: libsql::Rows) -> Self {
        Self {
            data: None,
            rows: Some(RefCell::new(rows)),
            stream: RefCell::new(None),
            counter: 0,
        }
    }

    /// Converts a single row into the PHP row shape used by the previous
    /// materialized iterator: string column keys plus sequential numeric keys
    /// (assoc + num combined).
    fn row_to_zval(rows: &libsql::Rows, row: &libsql::Row) -> Result<Zval, PhpException> {
        let mut arr = ext_php_rs::types::ZendHashTable::new();

        for idx in 0..rows.column_count() {
            let idx = idx as i32;
            let value = row
                .get_value(idx)
                .map_err(|e| PhpException::from(format!("Failed to read column value: {}", e)))?;

            // String column key (assoc access, e.g. `$row['name']`).
            if let Some(name) = rows.column_name(idx) {
                let zval_value = convert_libsql_value_to_zval(value.clone())
                    .map_err(|e| PhpException::from(e.to_string()))?;
                arr.insert(name, zval_value)?;
            }

            // Numeric key (index access, e.g. `$row[0]`) — pushed so numeric
            // indices always align 0..N-1.
            let zval_value = convert_libsql_value_to_zval(value)
                .map_err(|e| PhpException::from(e.to_string()))?;
            arr.push(zval_value)?;
        }

        arr.into_zval(false)
            .map_err(|e| PhpException::from(e.to_string()))
    }
}

#[php_impl]
impl LibSQLIterator {
    /// Constructor for LibSQLIterator.
    ///
    /// # Arguments
    ///
    /// * `data` - A reference to the Zval representing the PHP array to iterate over.
    ///
    /// # Returns
    ///
    /// A new instance of LibSQLIterator.
    pub fn __construct(data: &Zval) -> Self {
        Self {
            data: Some(Rc::new(data.shallow_clone())),
            rows: None,
            stream: RefCell::new(None),
            counter: 0,
        }
    }

    /// Returns the current element of the PHP array being iterated over.
    ///
    /// In streaming mode, the first `current()` call fetches the first row
    /// lazily — matching the documented `do { current(); ... next(); } while
    /// (valid());` iteration pattern where `current()` precedes `valid()`.
    ///
    /// # Returns
    ///
    /// A `Result` with an Option containing the current Zval element, or
    /// `Ok(None)` if the iterator is not valid.
    pub fn current(&self) -> Result<Option<Zval>, PhpException> {
        if let Some(data) = &self.data {
            return Ok(data.array().and_then(|hash_table| {
                hash_table
                    .get_index(self.counter as i64)
                    .map(|zval| zval.shallow_clone())
            }));
        }

        // Streaming mode: make sure a current row is available, then hand
        // back a copy of the converted row.
        self.ensure_current_row()?;
        let slot = self.stream.borrow();
        Ok(slot
            .as_ref()
            .and_then(|opt| opt.as_ref())
            .map(|zval| zval.shallow_clone()))
    }

    /// Returns the current key of the PHP array being iterated over.
    ///
    /// # Returns
    ///
    /// The current key as an i32.
    pub fn key(&self) -> i32 {
        self.counter
    }

    /// Moves the iterator to the next element in the PHP array.
    pub fn next(&mut self) {
        self.counter += 1;
        // Invalidate the cached row so the next valid() call fetches the
        // following row from the row set.
        *self.stream.borrow_mut() = None;
    }

    /// Moves the iterator to the first element in the PHP array.
    ///
    /// Note: row-set-backed iterators can only step forward, so streaming
    /// iterators cannot rewind to the beginning of the result set.
    pub fn rewind(&mut self) {
        self.counter = 0;
    }

    /// Checks if the iterator is valid (i.e., if there are more elements to
    /// iterate over).
    ///
    /// # Returns
    ///
    /// A `Result` with true if the iterator is valid, false otherwise.
    pub fn valid(&self) -> Result<bool, PhpException> {
        if let Some(data) = &self.data {
            return Ok(data.array().map_or(false, |hash_table| {
                hash_table.get_index(self.counter as i64).is_some()
            }));
        }

        // Streaming mode — make sure a current row exists, fetching the next
        // one lazily if needed. Row errors surface as PHP exceptions instead
        // of truncating the iteration silently.
        self.ensure_current_row()?;
        let slot = self.stream.borrow();
        Ok(slot.as_ref().map_or(false, |opt| opt.is_some()))
    }
}

impl LibSQLIterator {
    /// Fetches the next row from the row set if no current row is cached.
    ///
    /// Rows are fetched lazily so the result set is never materialized in
    /// full. Row errors propagate as PHP exceptions instead of silently
    /// truncating the iteration.
    fn ensure_current_row(&self) -> Result<(), PhpException> {
        let mut slot = self.stream.borrow_mut();
        if slot.is_none() {
            if let Some(rows) = &self.rows {
                let mut fetched_rows = rows.borrow_mut();
                let next = runtime()
                    .block_on(fetched_rows.next())
                    .map_err(|e| PhpException::from(format!("Failed to fetch row: {}", e)))?;

                *slot = Some(match next {
                    Some(row) => Some(Self::row_to_zval(&fetched_rows, &row)?),
                    None => None,
                });
            } else {
                *slot = Some(None);
            }
        }

        Ok(())
    }
}
