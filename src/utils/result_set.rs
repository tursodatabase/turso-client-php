use super::runtime::convert_vec_hashmap_to_php_array;

/// Represents the result set of a database query.
pub struct ResultSet {
    /// Columns of the result set.
    pub columns: Vec<String>,
    /// Rows of the result set represented as HashMaps of column names to values.
    pub rows: Vec<std::collections::HashMap<String, libsql::Value>>,
    /// Number of rows affected by the query.
    pub rows_affected: u64,
    /// The ID of the last inserted row, if applicable.
    pub last_insert_rowid: Option<i64>,
}

impl ext_php_rs::convert::IntoZval for ResultSet {
    const TYPE: ext_php_rs::flags::DataType = ext_php_rs::flags::DataType::Array;
    const NULLABLE: bool = false;

    /// Sets the ResultSet into a Zval.
    ///
    /// # Arguments
    ///
    /// * `zv` - A mutable reference to the Zval where the ResultSet will be set.
    /// * `_` - A boolean flag indicating persistence.
    ///
    /// # Returns
    ///
    /// A Result indicating success or an error.
    fn set_zval(
        self,
        zv: &mut ext_php_rs::types::Zval,
        _: bool,
    ) -> Result<(), ext_php_rs::error::Error> {
        let mut array = ext_php_rs::types::ZendHashTable::new();

        let columns_array: Vec<ext_php_rs::types::Zval> = self
            .columns
            .into_iter()
            .map(|col| col.into_zval(false).unwrap())
            .collect();
        array.insert("columns", columns_array)?;

        let rows_array = convert_vec_hashmap_to_php_array(self.rows);
        array.insert("rows", rows_array)?;

        array.insert("rows_affected", self.rows_affected)?;
        if let Some(last_insert_rowid) = self.last_insert_rowid {
            array.insert("last_insert_rowid", last_insert_rowid)?;
        } else {
            let null_zval = ext_php_rs::types::Zval::new();
            array.insert("last_insert_rowid", null_zval)?;
        }

        let array_zval = array.into_zval(false)?;
        *zv = array_zval;
        Ok(())
    }

    /// Converts the ResultSet into a Zval.
    ///
    /// # Arguments
    ///
    /// * `persistent` - A boolean flag indicating persistence.
    ///
    /// # Returns
    ///
    /// A Result containing the converted Zval or an error.
    fn into_zval(self, persistent: bool) -> ext_php_rs::error::Result<ext_php_rs::types::Zval> {
        let mut zval = ext_php_rs::types::Zval::new();
        self.set_zval(&mut zval, persistent)?;
        Ok(zval)
    }
}
