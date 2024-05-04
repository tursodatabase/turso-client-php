#[allow(non_snake_case, deprecated)]
#[cfg_attr(windows, feature(abi_vectorcall))]
extern crate ext_php_rs;
use ext_php_rs::prelude::*;

use crate::{
    statements,
    utils::{query_params::QueryParameters, runtime::runtime},
    CONNECTION_REGISTRY, TRANSACTION_REGISTRY,
};

#[php_class]
pub struct LibSQLTransaction {
    #[prop]
    pub trx_behavior: String,
    #[prop]
    pub trx_id: String,
    #[prop]
    pub conn_id: String,
}

#[php_impl]
impl LibSQLTransaction {
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

    pub fn changes(&self) -> Result<u64, PhpException> {
        statements::changes::get_changes(self.conn_id.to_string())
    }

    pub fn is_autocommit(&self) -> Result<bool, PhpException> {
        statements::is_autocommit::get_is_autocommit(self.conn_id.to_string())
    }

    pub fn exec(&self, stmt: &str) -> Result<bool, PhpException> {
        statements::use_exec::exec(self.conn_id.to_string(), stmt)
    }

    pub fn query(
        &self,
        stmt: &str,
        parameters: QueryParameters,
    ) -> Result<ext_php_rs::types::Zval, PhpException> {
        statements::use_query::query(self.conn_id.to_string(), stmt, parameters)
    }

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
