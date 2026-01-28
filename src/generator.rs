#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
extern crate ext_php_rs;
use std::rc::Rc;

use ext_php_rs::{prelude::*, types::Zval};

#[php_class]
pub struct LibSQLIterator {
    data: Rc<Zval>,
    counter: i32,
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
            data: Rc::new(data.shallow_clone()),
            counter: 0,
        }
    }

    /// Returns the current element of the PHP array being iterated over.
    ///
    /// # Returns
    ///
    /// An Option containing the current Zval element, or None if the iterator is not valid.
    pub fn current(&self) -> Option<Zval> {
        if let Some(hash_table) = self.data.array() {
            hash_table
                .get_index(self.counter as i64)
                .map(|zval| zval.shallow_clone())
        } else {
            None
        }
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
    }

    /// Moves the iterator to the first element in the PHP array.
    pub fn rewind(&mut self) {
        self.counter = 0;
    }

    /// Checks if the iterator is valid (i.e., if there are more elements to iterate over).
    ///
    /// # Returns
    ///
    /// True if the iterator is valid, false otherwise.
    pub fn valid(&self) -> bool {
        if let Some(hash_table) = self.data.array() {
            hash_table.get_index(self.counter as i64).is_some()
        } else {
            false
        }
    }
}
