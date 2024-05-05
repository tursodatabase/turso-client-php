# Get LibSQL Version

## Description

The `version()` method in the LibSQL PHP Extension retrieves the version of the LibSQL currently in use. This method provides developers with essential information about the version of the library they are working with, enabling them to ensure compatibility and access the latest features and improvements.

## Method Signature

```php
public static function version(): string
```

## Parameters

This method does not accept any parameters.

## Return Value

- `string`: The version string of the LibSQL Core Version and LibSQL PHP Extension Version.

## Example

```php
// Retrieve the version of the LibSQL
$version = LibSQL::version();
echo $version;

// Output
// LibSQL Core Version : 3.44.0-3044000 - LibSQL PHP Extension Version: 1.0.0
```

## Notes

It is essential to handle exceptions and errors appropriately when using the `version()` method to ensure smooth execution and graceful error handling in case of any issues.
