#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
extern crate ext_php_rs;
use ext_php_rs::prelude::*;

use crate::{
    hooks, statement::LibSQLStatement, utils::{query_params::QueryParameters, runtime::runtime}, CONNECTION_REGISTRY, TRANSACTION_REGISTRY
};

/// Represents a LibSQLTransaction object for managing transactions.
#[php_class]
pub struct LibSQLTransaction {
    /// The behavior of the transaction.
    pub trx_behavior: String,

    /// The ID of the transaction.
    pub trx_id: String,

    /// The ID of the database connection associated with the transaction.
    pub conn_id: String,
}

#[php_impl]
impl LibSQLTransaction {
    /// Constructs a new `LibSQLTransaction` object.
    ///
    /// # Arguments
    ///
    /// * `conn_id` - The ID of the database connection.
    /// * `trx_mode` - The mode of the transaction.
    ///
    /// # Returns
    ///
    /// A `Result` containing the constructed `LibSQLTransaction` object or a `PhpException` if an error occurs.
    pub fn __construct(conn_id: String, trx_mode: String) -> Result<Self, PhpException> {
        let conn_registry = CONNECTION_REGISTRY.lock().unwrap();

        let conn = conn_registry
            .get(&conn_id)
            .ok_or_else(|| PhpException::from("Connection not found"))?;

        let trx_behavior = match trx_mode.as_str() {
            "WRITE" => libsql::TransactionBehavior::Immediate,
            "READ" => libsql::TransactionBehavior::ReadOnly,
            _ => libsql::TransactionBehavior::Deferred,
        };

        let trx = runtime().block_on(async {
            let transaction = conn.transaction_with_behavior(trx_behavior).await;
            transaction.unwrap()
        });

        let trx_id = uuid::Uuid::new_v4().to_string();
        TRANSACTION_REGISTRY
            .lock()
            .unwrap()
            .insert(trx_id.clone(), trx);

        Ok(Self {
            trx_behavior: trx_mode,
            trx_id,
            conn_id,
        })
    }

    /// Gets the number of rows changed by the transaction.
    ///
    /// # Returns
    ///
    /// A `Result` containing the number of rows changed or a `PhpException` if an error occurs.
    pub fn changes(&self) -> Result<u64, PhpException> {
        hooks::changes::get_changes(self.conn_id.to_string())
    }

    /// Checks if the connection is in autocommit mode.
    ///
    /// # Returns
    ///
    /// A `Result` containing a boolean indicating if autocommit is enabled or a `PhpException` if an error occurs.
    pub fn is_autocommit(&self) -> Result<bool, PhpException> {
        hooks::is_autocommit::get_is_autocommit(self.conn_id.to_string())
    }

    /// Executes a SQL statement within the transaction.
    ///
    /// # Arguments
    ///
    /// * `stmt` - The SQL statement to execute.
    /// * `parameters` - Query parameters for the statement.
    ///
    /// # Returns
    ///
    /// A `Result` containing the number of affected rows or a `PhpException` if an error occurs.
    pub fn execute(
        &self,
        stmt: &str,
        parameters: Option<QueryParameters>,
    ) -> Result<u64, PhpException> {
        hooks::use_exec::exec(self.conn_id.to_string(), stmt, parameters)
    }

    /// Prepares a SQL statement for execution.
    ///
    /// # Arguments
    ///
    /// * `sql` - The SQL statement to prepare.
    ///
    /// # Returns
    ///
    /// A `Result` containing a `LibSQLStatement` instance or a `PhpException` if an error occurs.
    pub fn prepare(&self, sql: &str) -> Result<LibSQLStatement, PhpException> {
        LibSQLStatement::__construct(self.conn_id.clone(), sql)
    }

    /// Executes a query within the transaction.
    ///
    /// # Arguments
    ///
    /// * `stmt` - The SQL statement to execute.
    /// * `parameters` - Query parameters for the statement.
    ///
    /// # Returns
    ///
    /// A `Result` containing the query result as a PHP value or a `PhpException` if an error occurs.
    pub fn query(
        &self,
        stmt: &str,
        parameters: QueryParameters,
    ) -> Result<ext_php_rs::types::Zval, PhpException> {
        hooks::use_query::query(self.conn_id.to_string(), stmt, Some(parameters))
    }

    /// Commits the transaction.
    ///
    /// # Returns
    ///
    /// A `Result` indicating success or a `PhpException` if an error occurs.
    pub fn commit(&self) -> Result<(), PhpException> {
        let mut trx_registry = TRANSACTION_REGISTRY.lock().unwrap();

        let trx = trx_registry
            .remove(&self.trx_id)
            .ok_or_else(|| PhpException::from("Transaction not found"))?;

        let commit_result = runtime().block_on(async { trx.commit().await });

        match commit_result {
            Ok(_) => Ok(()),
            Err(_) => Err(PhpException::from("Failed to commit transaction")),
        }
    }

    /// Rolls back the transaction.
    ///
    /// # Returns
    ///
    /// A `Result` indicating success or a `PhpException` if an error occurs.
    pub fn rollback(&self) -> Result<(), PhpException> {
        let mut trx_registry = TRANSACTION_REGISTRY.lock().unwrap();

        let trx = trx_registry
            .remove(&self.trx_id)
            .ok_or_else(|| PhpException::from("Transaction not found"))?;

        let rollback_result = runtime().block_on(async { trx.rollback().await });

        match rollback_result {
            Ok(_) => Ok(()),
            Err(_) => Err(PhpException::from("Failed to rollback transaction")),
        }
    }
}
