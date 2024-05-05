#[derive(Debug, Clone)]
pub enum ConfigValue {
    String(String),
    Array(std::collections::HashMap<String, ConfigValue>)
}

impl ConfigValue {
    pub fn to_string(&self) -> Option<String> {
        match self {
            ConfigValue::String(s) => Some(s.clone()),
            _ => None,
        }
    }

    pub fn to_long(&self) -> Option<u64> {
        None
    }

    pub fn to_bool(&self) -> Option<bool> {
        None
    }
}

impl<'a> ext_php_rs::convert::FromZval<'a> for ConfigValue {
    const TYPE: ext_php_rs::flags::DataType = ext_php_rs::flags::DataType::Mixed;

    fn from_zval(zval: &'a ext_php_rs::types::Zval) -> Option<Self> {
        if let Some(s) = zval.string() {
            Some(ConfigValue::String(s))
        } else if let Some(array) = zval.array() {
            let mut map = std::collections::HashMap::new();
            for (key, val) in array.iter() {
                if let (Some(key), Some(value)) =
                    (Some(key.to_string()), ConfigValue::from_zval(val))
                {
                    map.insert(key, value);
                } else {
                    return None;
                }
            }
            Some(ConfigValue::Array(map))
        } else {
            None
        }
    }
}
