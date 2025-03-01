<?php

declare(strict_types=1);

namespace intltime;

defined('is_running') or die('Not an entry point...');

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use IntlCalendar;
use InvalidArgumentException;

/**
 * Emulates the strftime() function for PHP 8.4 using the intl extension.
 * This version combines:
 * - Handling various timestamp input types (int, string, DateTimeInterface, null)
 * - Explicit locale and timezone arguments with defaults
 * - Comprehensive mapping of strftime format specifiers to ICU equivalents
 * - Robust error handling
 * - Proper namespacing and class structure
 * - Checking for the intl extension
 * - Implementation of missing specifiers like %U, %V, %W, %P, %k, %l
 *
 * @param string $format The format string. See https://www.php.net/manual/en/function.strftime.php for details.
 * @param int|string|DateTimeInterface|null $timestamp (Optional) The timestamp to format. If null, uses the current time.
 * @param string|null $locale (Optional) The locale to use. If null, uses the default locale.
 * @param string|null $timezone (Optional) The timezone to use. If null, uses the default timezone.
 * @return string|false The formatted string, or false on failure.
 * @throws InvalidArgumentException
 */
function strftime(string $format, int|string|DateTimeInterface|null $timestamp = null, ?string $locale = null, ?string $timezone = null): string|false
{
    if (!extension_loaded('intl')) {
        trigger_error('The intl extension is not loaded.', E_USER_WARNING);
        return false;
    }

    // Handle Timestamp Input and Create DateTime Object
    try {
        if ($timestamp === null) {
            $dateTime = new DateTime();
        } elseif (is_numeric($timestamp)) {
            $dateTime = new DateTime('@' . $timestamp);
            $dateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));
        } elseif (is_string($timestamp)) {
            $dateTime = new DateTime($timestamp);
        } elseif ($timestamp instanceof DateTimeInterface) {
            $dateTime = $timestamp;
        } else {
            throw new InvalidArgumentException('$timestamp argument is not a valid date-time string nor a DateTime object nor a valid UNIX timestamp.');
        }
    } catch (\Exception $e) {
        throw new InvalidArgumentException('Invalid timestamp format', 0, $e);
    }

    // Set Locale and Timezone (use defaults if not provided)
    $locale = $locale ?? \Locale::getDefault();
    $timezone = $timezone ?? date_default_timezone_get();
    $timeZoneObject = new DateTimeZone($timezone);
    $dateTime->setTimezone($timeZoneObject);

    // Format Map: strftime to ICU
    $formatMap = [
        '%a' => 'EEE',       // Abbreviated weekday name
        '%A' => 'EEEE',      // Full weekday name
        '%b' => 'MMM',       // Abbreviated month name
        '%B' => 'MMMM',      // Full month name
        '%c' => 'EEE MMM dd HH:mm:ss yyyy', // Preferred date and time representation (locale-dependent)
        '%C' => null,        // Century number (year/100, truncated to integer)
        '%d' => 'dd',        // Day of the month as a decimal number (01-31)
        '%D' => 'MM/dd/yy',  // Short MM/DD/YY date
        '%e' => 'd',         // Day of the month as a decimal number (1-31); a single digit is preceded by a space
        '%F' => 'yyyy-MM-dd', // ISO 8601 date format (YYYY-MM-DD)
        '%g' => 'yy',        // The ISO 8601 week-based year without the century (00-99) (approximation)
        '%G' => 'yyyy',      // The ISO 8601 week-based year with century
        '%h' => 'MMM',       // Same as %b
        '%H' => 'HH',        // Hour as a decimal number (00-23)
        '%I' => 'hh',        // Hour as a decimal number (01-12)
        '%j' => null,        // Day of the year as a decimal number (001-366)
        '%m' => 'MM',        // Month as a decimal number (01-12)
        '%M' => 'mm',        // Minute as a decimal number (00-59)
        '%n' => "\n",        // A newline character
        '%p' => 'a',         // AM or PM
        '%P' => null,         // am or pm (lowercase)
        '%r' => 'hh:mm:ss a', // AM/PM time format
        '%R' => 'HH:mm',     // 24-hour HH:MM time format
        '%s' => null,        // Number of seconds since the Epoch
        '%S' => 'ss',        // Second as a decimal number (00-60)
        '%t' => "\t",        // A tab character
        '%T' => 'HH:mm:ss',  // 24-hour time format
        '%u' => null,        // ISO 8601 weekday as number with Monday as 1
        '%U' => null,        // Week number of the current year (Sunday as first day)
        '%V' => null,        // ISO 8601 week number
        '%w' => null,        // Weekday as a decimal number (0-6), with Sunday as 0
        '%W' => null,        // Week number of the current year, starting with the first Monday as the first week
        '%x' => 'MM/dd/yyyy', // Preferred date representation (locale-dependent)
        '%X' => 'HH:mm:ss',  // Preferred time representation (locale-dependent)
        '%y' => 'yy',        // Year without century (00-99)
        '%Y' => 'yyyy',      // Year with century
        '%z' => 'ZZZZ',      // Numeric timezone offset. E.g., -0800
        '%Z' => 'z',         // Timezone abbreviation
        '%%' => '%',         // A literal '%' character
        '%k' => null,         // Hour in 24-hour format, space-padded
        '%l' => null,         // Hour in 12-hour format, space-padded
    ];

    // Build ICU Format String
    $icuFormat = '';
    $len = strlen($format);

    for ($i = 0; $i < $len; $i++) {
        if ($format[$i] == '%') {
            $i++;
            if (!isset($format[$i])) {
                return false; // Invalid format. Trailing %.
            }

            $key = '%' . $format[$i];
            if (isset($formatMap[$key])) {
                if ($formatMap[$key] === null) {
                    // Handle cases where a direct mapping isn't possible
                    switch ($key) {
                        case '%C':
                            $icuFormat .= floor($dateTime->format('Y') / 100);
                            break;
                        case '%j':
                            $icuFormat .= sprintf('%03d', (int)$dateTime->format('z') + 1);
                            break;
                        case '%s':
                            $icuFormat .= $dateTime->getTimestamp();
                            break;
                        case '%k':
                            $icuFormat .= sprintf('% 2u', $dateTime->format('G')); // Hour in 24-hour format, space-padded
                            break;
                        case '%l':
                            $icuFormat .= sprintf('% 2u', $dateTime->format('g')); // Hour in 12-hour format, space-padded
                            break;
                        case '%u': // ISO 8601 weekday as number with Monday as 1
                            $dayOfWeek = (int)$dateTime->format('N');
                            $icuFormat .= $dayOfWeek;
                            break;
                        case '%w': // Weekday as a decimal number (0-6), with Sunday as 0
                            $icuFormat .= $dateTime->format('w');
                            break;
                        case '%U': // Week number of the current year as a decimal number, starting with the first Sunday as the first week
                        case '%W': // Week number of the current year as a decimal number, starting with the first Monday as the first week
                        case '%V': // The ISO 8601 week number (01-53)
                            $calendar = IntlCalendar::createInstance($timeZoneObject, $locale);
                            $calendar->setTime($dateTime->getTimestamp() * 1000); // IntlCalendar uses milliseconds

                            if ($key == '%U') {
                                $icuFormat .= $calendar->get(IntlCalendar::FIELD_WEEK_OF_YEAR);
                            } elseif ($key == '%W') {
                                $calendar->setMinimalDaysInFirstWeek(4); // ISO 8601
                                $calendar->setFirstDayOfWeek(IntlCalendar::MONDAY); // ISO 8601
                                $icuFormat .= $calendar->get(IntlCalendar::FIELD_WEEK_OF_YEAR);

                            } else { // %V
                                $calendar->setMinimalDaysInFirstWeek(4); // ISO 8601
                                $calendar->setFirstDayOfWeek(IntlCalendar::MONDAY); // ISO 8601
                                $icuFormat .= sprintf("%02d", $calendar->get(IntlCalendar::FIELD_WEEK_OF_YEAR)); //format to 2 digits
                            }
                            break;
                        case '%P':
                            $amPm = $dateTime->format('a');
                            $icuFormat .= strtolower($amPm);
                            break;
                        default:
                            return false; // Indicate unsupported format
                    }
                } else {
                    $icuFormat .= $formatMap[$key];
                }
            } else {
                return false; // Unknown format code.
            }
        } else {
            $icuFormat .= $format[$i];
        }
    }

    try {
        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            $timeZoneObject,
            IntlDateFormatter::GREGORIAN,
            $icuFormat
        );

        return $formatter->format($dateTime);
    } catch (\Exception $e) {
        // Handle invalid locale or timezone
        error_log("IntlDateFormatter exception: " . $e->getMessage()); // Log the error
        return false;
    }
}