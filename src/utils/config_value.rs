#[derive(Debug, Clone)]
pub enum ConfigValue {
    String(String),
    Long(i64),
    Double(f64),
    Bool(bool),
    Null,
    Array(std::collections::HashMap<String, ConfigValue>),
}

impl ConfigValue {
    pub fn to_string(&self) -> Option<String> {
        match self {
            ConfigValue::String(s) => Some(s.clone()),
            _ => None,
        }
    }

    pub fn to_long(&self) -> Option<i64> {
        match self {
            ConfigValue::Long(v) => Some(*v),
            _ => None,
        }
    }

    pub fn to_bool(&self) -> Option<bool> {
        match self {
            ConfigValue::Bool(v) => Some(*v),
            _ => None,
        }
    }
}

impl<'a> ext_php_rs::convert::FromZval<'a> for ConfigValue {
    const TYPE: ext_php_rs::flags::DataType = ext_php_rs::flags::DataType::Mixed;

    fn from_zval(zval: &'a ext_php_rs::types::Zval) -> Option<Self> {
        if let Some(s) = zval.string() {
            Some(ConfigValue::String(s))
        } else if let Some(v) = zval.long() {
            Some(ConfigValue::Long(v))
        } else if let Some(v) = zval.double() {
            Some(ConfigValue::Double(v))
        } else if let Some(v) = zval.bool() {
            Some(ConfigValue::Bool(v))
        } else if zval.is_null() {
            Some(ConfigValue::Null)
        } else if let Some(array) = zval.array() {
            let mut map = std::collections::HashMap::new();
            for (key, val) in array.iter() {
                // Unsupported nested values (e.g. objects, resources) are
                // skipped instead of aborting the whole conversion.
                if let Some(value) = ConfigValue::from_zval(val) {
                    map.insert(key.to_string(), value);
                }
            }
            Some(ConfigValue::Array(map))
        } else {
            None
        }
    }
}
