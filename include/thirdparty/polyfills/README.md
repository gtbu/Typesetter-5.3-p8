Symfony Polyfill
================

This project backports features found in the latest PHP versions and provides
compatibility layers for some extensions and functions. It is intended to be
used when portability across PHP versions and extensions is desired.

Polyfills are provided for:
- the `apcu` extension when the legacy `apc` extension is installed;
- the `ctype` extension when PHP is compiled without ctype;
- the `mbstring` and `iconv` extensions;
- the `uuid` extension;
- the `MessageFormatter` class and the `msgfmt_format_message` functions;
- the `Normalizer` class and the `grapheme_*` functions;
- the `utf8_encode` and `utf8_decode` functions from the `xml` extension or PHP-7.2 core;
- the `Collator`, `NumberFormatter`, `Locale` and `IntlDateFormatter` classes,
  limited to the "en" locale;
- the `intl_error_name`, `intl_get_error_code`, `intl_get_error_message` and
  `intl_is_failure` functions;
- the `idn_to_ascii` and `idn_to_utf8` functions;
- a `Binary` utility class to be used when compatibility with
  `mbstring.func_overload` is required;
- the `spl_object_id` and `stream_isatty` functions introduced in PHP 7.2;
- the `mb_ord`, `mb_chr` and `mb_scrub` functions introduced in PHP 7.2 from the `mbstring` extension
- the `sapi_windows_vt100_support` function (Windows only) introduced in PHP 7.2;
- the `PHP_FLOAT_*` constant introduced in PHP 7.2;
- the `PHP_OS_FAMILY` constant introduced in PHP 7.2;
- the `is_countable` function introduced in PHP 7.3;
- the `array_key_first` and `array_key_last` functions introduced in PHP 7.3;
- the `hrtime` function introduced in PHP 7.3;
- the `JsonException` class introduced in PHP 7.3;
- the `get_mangled_object_vars`, `mb_str_split` and `password_algos` functions
  introduced in PHP 7.4;
- the `fdiv` function introduced in PHP 8.0;
- the `get_debug_type` function introduced in PHP 8.0;
- the `preg_last_error_msg` function introduced in PHP 8.0;
- the `str_contains` function introduced in PHP 8.0;
- the `str_starts_with` and `str_ends_with` functions introduced in PHP 8.0;
- the `ValueError` class introduced in PHP 8.0;
- the `UnhandledMatchError` class introduced in PHP 8.0;
- the `FILTER_VALIDATE_BOOL` constant introduced in PHP 8.0;
- the `get_resource_id` function introduced in PHP 8.0;
- the `Attribute` class introduced in PHP 8.0;
- the `Stringable` interface introduced in PHP 8.0;
- the `PhpToken` class introduced in PHP 8.0 when the tokenizer extension is enabled;
- the `array_is_list` function introduced in PHP 8.1;
- the `enum_exists` function introduced in PHP 8.1;
- the `MYSQLI_REFRESH_REPLICA` constant introduced in PHP 8.1;
- the `ReturnTypeWillChange` attribute introduced in PHP 8.1;
- the `CURLStringFile` class introduced in PHP 8.1 (but only if PHP >= 7.4 is used);
- the `AllowDynamicProperties` attribute introduced in PHP 8.2;
- the `SensitiveParameter` attribute introduced in PHP 8.2;
- the `SensitiveParameterValue` class introduced in PHP 8.2;
- the `Random\Engine` interface introduced in PHP 8.2;
- the `Random\CryptoSafeEngine` interface introduced in PHP 8.2;
- the `Random\Engine\Secure` class introduced in PHP 8.2 (check [arokettu/random-polyfill](https://packagist.org/packages/arokettu/random-polyfill) for more engines);
- the `odbc_connection_string_is_quoted` function introduced in PHP 8.2;
- the `odbc_connection_string_should_quote` function introduced in PHP 8.2;
- the `odbc_connection_string_quote` function introduced in PHP 8.2;
- the `ini_parse_quantity` function introduced in PHP 8.2;
- the `json_validate` function introduced in PHP 8.3;
- the `Override` attribute introduced in PHP 8.3;
- the `mb_str_pad` function introduced in PHP 8.3;
- the `ldap_exop_sync` function introduced in PHP 8.3;
- the `ldap_connect_wallet` function introduced in PHP 8.3;
- the `stream_context_set_options` function introduced in PHP 8.3;
- the `str_increment` and `str_decrement` functions introduced in PHP 8.3;
- the `Date*Exception/Error` classes introduced in PHP 8.3;
- the `SQLite3Exception` class introduced in PHP 8.3;
- the `mb_ucfirst` and `mb_lcfirst` functions introduced in PHP 8.4;
- the `array_find`, `array_find_key`, `array_any` and `array_all` functions introduced in PHP 8.4;
- the `Deprecated` attribute introduced in PHP 8.4;
- the `mb_trim`, `mb_ltrim` and `mb_rtrim` functions introduced in PHP 8.4;
- the `ReflectionConstant` class introduced in PHP 8.4
- the `CURL_HTTP_VERSION_3` and `CURL_HTTP_VERSION_3ONLY` constants introduced in PHP 8.4;
- the `grapheme_str_split` function introduced in PHP 8.4;
- the `bcdivmod` function introduced in PHP 8.4;
- the `get_error_handler` and `get_exception_handler` functions introduced in PHP 8.5;
- the `NoDiscard` attribute introduced in PHP 8.5;
- the `array_first` and `array_last` functions introduced in PHP 8.5;

It is strongly recommended to upgrade your PHP version and/or install the missing
extensions whenever possible. This polyfill should be used only when there is no
better choice or when portability is a requirement.

Compatibility notes
===================

To write portable code between PHP5 and PHP7, some care must be taken:
- `\*Error` exceptions must be caught before `\Exception`;
- after calling `error_clear_last()`, the result of `$e = error_get_last()` must be
  verified using `isset($e['message'][0])` instead of `null !== $e`.

Usage
=====

When using [Composer](https://getcomposer.org/) to manage your dependencies, you
should **not** `require` the `symfony/polyfill` package, but the standalone ones:
- `symfony/polyfill-apcu` for using the `apcu_*` functions,
- `symfony/polyfill-ctype` for using the ctype functions,
- `symfony/polyfill-php54` for using the PHP 5.4 functions,
- `symfony/polyfill-php55` for using the PHP 5.5 functions,
- `symfony/polyfill-php56` for using the PHP 5.6 functions,
- `symfony/polyfill-php70` for using the PHP 7.0 functions,
- `symfony/polyfill-php71` for using the PHP 7.1 functions,
- `symfony/polyfill-php72` for using the PHP 7.2 functions,
- `symfony/polyfill-php73` for using the PHP 7.3 functions,
- `symfony/polyfill-php74` for using the PHP 7.4 functions,
- `symfony/polyfill-php80` for using the PHP 8.0 functions,
- `symfony/polyfill-php81` for using the PHP 8.1 functions,
- `symfony/polyfill-php82` for using the PHP 8.2 functions,
- `symfony/polyfill-php83` for using the PHP 8.3 functions,
- `symfony/polyfill-php84` for using the PHP 8.4 functions,
- `symfony/polyfill-php85` for using the PHP 8.5 functions,
- `symfony/polyfill-iconv` for using the iconv functions,
- `symfony/polyfill-intl-grapheme` for using the `grapheme_*` functions,
- `symfony/polyfill-intl-idn` for using the `idn_to_ascii` and `idn_to_utf8` functions,
- `symfony/polyfill-intl-icu` for using the intl functions and classes,
- `symfony/polyfill-intl-messageformatter` for using the intl messageformatter,
- `symfony/polyfill-intl-normalizer` for using the intl normalizer,
- `symfony/polyfill-mbstring` for using the mbstring functions,
- `symfony/polyfill-util` for using the polyfill utility helpers.
- `symfony/polyfill-uuid` for using the `uuid_*` functions,

Requiring `symfony/polyfill` directly would prevent Composer from sharing
correctly polyfills in dependency graphs. As such, it would likely install
more code than required.

Design
======

This package is designed for low overhead and high quality polyfilling.

It adds only a few lightweight `require` statements to the bootstrap process
to support all polyfills. Implementations are then loaded on-demand when
needed during code execution.

If your project requires a minimum PHP version it is advisable to add polyfills
for lower PHP versions to the `replace` section of your `composer.json`.
This removes any overhead from these polyfills as they are no longer part of your project.
The same can be done for polyfills for extensions that you require.

If your project requires php 7.0, and needs the mb extension, the replace section would look
something like this:

```json
{
    "replace": {
        "symfony/polyfill-php54": "*",
        "symfony/polyfill-php55": "*",
        "symfony/polyfill-php56": "*",
        "symfony/polyfill-php70": "*",
        "symfony/polyfill-mbstring": "*"
    }
}
```

Polyfills are unit-tested alongside their native implementation so that
feature and behavior parity can be proven and enforced in the long run.

License
=======

This library is released under the [MIT license](LICENSE).
