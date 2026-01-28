use ext_php_rs::{
    convert::FromZval,
    flags::DataType,
    types::{ArrayKey, Zval},
};
use std::collections::HashMap;
use std::fmt;

/// Represents a value used in query parameters.
#[derive(Debug, Clone)]
pub enum QueryValue {
    Integer(i64),
    Real(f64),
    Text(String),
    Blob(Vec<u8>),
    Null,
}

impl fmt::Display for QueryValue {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            QueryValue::Integer(i) => write!(f, "{}", i),
            QueryValue::Real(fl) => write!(f, "{}", fl),
            QueryValue::Text(text) => write!(f, "{}", text),
            QueryValue::Null => write!(f, "NULL"),
            QueryValue::Blob(blob) => {
                let blob_str = blob
                    .iter()
                    .map(|b| format!("{:02X}", b))
                    .collect::<String>();
                write!(f, "X'{}'", blob_str)
            }
        }
    }
}

/// Represents query parameters for database queries.
#[derive(Debug, Clone)]
pub struct QueryParameters {
    pub positional: Option<Vec<QueryValue>>,
    pub named: Option<HashMap<String, QueryValue>>,
}

/// Converts QueryParameters to libsql parameters.
impl QueryParameters {
    pub fn to_params(&self) -> libsql::params::Params {
        let positional_params = self
            .positional
            .as_ref()
            .map(|params| {
                params
                    .iter()
                    .map(|s| match &s {
                        QueryValue::Integer(i) => libsql::Value::Integer(*i),
                        QueryValue::Real(f) => libsql::Value::Real(*f),
                        QueryValue::Null => libsql::Value::Null,
                        QueryValue::Blob(b) => libsql::Value::Blob(b.to_vec()),
                        QueryValue::Text(t) => libsql::Value::Text(t.to_string()),
                    })
                    .collect::<Vec<_>>()
            })
            .unwrap_or_else(Vec::new);

        let param_named = self.named.as_ref().map(|params| {
            params
                .iter()
                .map(|(k, v)| match &v {
                    QueryValue::Integer(i) => (k.clone(), libsql::Value::Integer(*i)),
                    QueryValue::Real(f) => (k.clone(), libsql::Value::Real(*f)),
                    QueryValue::Null => (k.clone(), libsql::Value::Null),
                    QueryValue::Blob(b) => (k.clone(), libsql::Value::Blob(b.to_vec())),
                    QueryValue::Text(t) => (k.clone(), libsql::Value::Text(t.to_string())),
                })
                .collect::<Vec<_>>()
        });

        match (
            positional_params.is_empty(),
            param_named.as_ref().map(|p| p.is_empty()).unwrap_or(true),
        ) {
            (false, true) => libsql::params::Params::Positional(positional_params),
            (true, false) => libsql::params::Params::Named(param_named.unwrap()),
            _ => libsql::params::Params::None,
        }
    }

    pub fn get_named(&self) -> Option<&HashMap<String, QueryValue>> {
        self.named.as_ref()
    }

    pub fn get_positional(&self) -> Option<&Vec<QueryValue>> {
        self.positional.as_ref()
    }
}

impl<'a> FromZval<'a> for QueryParameters {
    const TYPE: DataType = DataType::Mixed;

    fn from_zval(zval: &'a Zval) -> Option<Self> {
        if let Some(array) = zval.array() {
            let mut positional = Vec::new();
            let mut named = HashMap::new();

            for (key, value) in array.iter() {
                match key {
                    ArrayKey::Long(index) => {
                        let query_value = if let Some(int_val) = value.long() {
                            QueryValue::Integer(int_val)
                        } else if let Some(float_val) = value.double() {
                            QueryValue::Real(float_val)
                        } else if let Some(text_val) = value.string() {
                            QueryValue::Text(text_val.to_string())
                        } else if let Some(text_val) = value.string() {
                            QueryValue::Blob(text_val.as_bytes().to_vec())
                        } else if value.is_null() {
                            QueryValue::Null
                        } else {
                            continue;
                        };

                        if index >= positional.len() as i64 {
                            positional.resize((index + 1) as usize, QueryValue::Null);
                        }
                        positional[index as usize] = query_value;
                    }
                    ArrayKey::String(key) => {
                        let query_value = if let Some(int_val) = value.long() {
                            QueryValue::Integer(int_val)
                        } else if let Some(float_val) = value.double() {
                            QueryValue::Real(float_val)
                        } else if let Some(text_val) = value.string() {
                            QueryValue::Text(text_val.to_string())
                        } else if let Some(text_val) = value.string() {
                            QueryValue::Blob(text_val.as_bytes().to_vec())
                        } else if value.is_null() {
                            QueryValue::Null
                        } else {
                            continue;
                        };

                        named.insert(key.to_string(), query_value);
                    }
                    ArrayKey::Str(key) => {
                        let query_value = if let Some(int_val) = value.long() {
                            QueryValue::Integer(int_val)
                        } else if let Some(float_val) = value.double() {
                            QueryValue::Real(float_val)
                        } else if let Some(text_val) = value.string() {
                            QueryValue::Text(text_val.to_string())
                        } else if let Some(text_val) = value.string() {
                            QueryValue::Blob(text_val.as_bytes().to_vec())
                        } else if value.is_null() {
                            QueryValue::Null
                        } else {
                            continue;
                        };

                        named.insert(key.to_string(), query_value);
                    }
                }
            }

            Some(QueryParameters {
                positional: if positional.is_empty() {
                    None
                } else {
                    Some(positional)
                },
                named: if named.is_empty() { None } else { Some(named) },
            })
        } else {
            None
        }
    }
}
