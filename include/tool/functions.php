<?php
defined('is_running') or die('Not an entry point...');

/* Obsolete : https://www.php.net/manual/en/function.ctype-alnum.php  */
if( !function_exists('ctype_alnum') ){
	function ctype_alnum($string){
		return (bool)preg_match('#^[a-z0-9]*$#i',$string);
	}
} 

/* Obsolete : https://www.php.net/manual/de/function.ctype-digit.php */
if( !function_exists('ctype_digit') ){
	function ctype_digit($string){
		return (bool)preg_match('#^[0-9]*$#',$string);
	}
}  

/**
 * obsolete now: https://www.php.net/manual/en/function.mb-strpos.php
 * mb_strpos(
 *   string $haystack,
 *   string $needle,
 *   int $offset = 0,
 *   ?string $encoding = null
 *  ): int|false
 */
if( !function_exists('mb_strpos') ){ 
	function mb_strpos(){
		$args = func_get_args();
		return call_user_func_array('strpos',$args);
	}
	function mb_strlen($str){
		return strlen($str);
	}
	function mb_strtoupper($str){
		return strtoupper($str);
	}
	function mb_strtolower($str){
		return strtolower($str);
	}
	function mb_substr(){
		$args = func_get_args();
		return call_user_func_array('substr',$args);
	}
	function mb_substr_count($haystack,$needle){
		return substr_count($haystack,$needle);
	}
}


/**
 * Multibyte-safe version of substr_replace() with array support.
 * @param string|string[] $str         Input string or array of strings.
 * @param string|string[] $repl        Replacement string or array of strings.
 * @param int|int[]       $start       Start position or array of start positions.
 * @param int|int[]|null  $length      (Optional) Length or array of lengths.
 * @param string|null     $encoding    (Optional) Character encoding.
 * @return string|string[]|false The modified string or array, or false on error.
 */
function mb_substr_replace(
    string|array $str,
    string|array $repl,
    int|array $start,
    int|array|null $length = null,
    ?string $encoding = null
): string|array|false {
    $encoding ??= mb_internal_encoding();
    if (is_array($str)) {
        $num = count($str);        
        $repl = is_array($repl) ? array_slice($repl, 0, $num) : array_fill(0, $num, $repl);
        $start = is_array($start) ? array_slice($start, 0, $num) : array_fill(0, $num, $start);
        $length = is_array($length) ? array_slice($length, 0, $num) : array_fill(0, $num, $length ?? null);
        return array_map(
            fn($s, $r, $st, $l) => mb_substr_replace($s, $r, $st, $l, $encoding),
            $str,
            $repl,
            $start,
            $length
        );
    }
    if (is_array($repl) || is_array($start) || is_array($length)) {
        trigger_error('mb_substr_replace(): Passing an array for replacement, start, or length is not supported when the main string is not an array.', E_USER_WARNING);
        return false;
    }
    $str_len = mb_strlen($str, $encoding);
    if ($start < 0) {
        $start = max(0, $str_len + $start);
    } else {
        $start = min($start, $str_len);
    }
    if ($length === null) {
        $length = $str_len - $start;
    } elseif ($length < 0) {
        $length = max(0, $str_len - $start + $length);
    } else {
        $length = min($length, $str_len - $start);
    }
    $before = mb_substr($str, 0, $start, $encoding);
    $after = mb_substr($str, $start + $length, null, $encoding);
    return $before . $repl . $after;
}

/**
     * Multibyte-safe str_replace() equivalent.     *
     * @param string|array $search
     * @param string|array $replace
     * @param string|array $subject
     * @param int $count (by reference) Count of replacements made
     * @param string|null $encoding Optional character encoding
     * @return string|array
     */
if (!function_exists('mb_str_replace')) {
    
    function mb_str_replace($search, $replace, $subject, &$count = 0, ?string $encoding = null) {
        if (is_array($subject)) {
            foreach ($subject as $key => $value) {
                $subject[$key] = mb_str_replace($search, $replace, $value, $count, $encoding);
            }
            return $subject;
        }

        $encoding = $encoding ?? mb_internal_encoding();
        $subject = (string) $subject;
        $searches = is_array($search) ? array_values($search) : [$search];
        $replacements = is_array($replace) ? array_values($replace) : [$replace];
        $replacements = array_pad($replacements, count($searches), '');

        foreach ($searches as $key => $searchTerm) {
            $searchTerm = (string) $searchTerm;

            if ($searchTerm === '') {
                trigger_error('mb_str_replace(): Empty search string is not supported', E_USER_WARNING);
                continue;
            }

            $replaceWith = (string) $replacements[$key];
            $offset = 0;
            while (($pos = mb_strpos($subject, $searchTerm, $offset, $encoding)) !== false) {
                $subject = mb_substr($subject, 0, $pos, $encoding)
                         . $replaceWith
                         . mb_substr($subject, $pos + mb_strlen($searchTerm, $encoding), null, $encoding);

                $offset = $pos + mb_strlen($replaceWith, $encoding);
                $count++;
            }
        }
        return $subject;
    }
}

if (!function_exists('mb_explode')) {
    /**
     * A multi-byte safe version of the native PHP explode() function,
     * @param string $delimiter The boundary string.
     * @param string $string    The input string.
     * @param int    $limit     
     * @return array Returns an array of strings.
     * @throws ValueError if the delimiter is an empty string, mimicking PHP 8's explode().
     */
    function mb_explode(string $delimiter, string $string, int $limit = PHP_INT_MAX): array
    {        
        if ($delimiter === '') {
            throw new ValueError('mb_explode(): Argument #1 ($delimiter) must not be empty');
        }
		
        if (mb_strpos($string, $delimiter) === false) {
            return [$string];
        }
		
        if ($limit === 0) {
            $limit = 1;
        }

        $result = [];
        $current_string = $string; 
        $delimiter_length = mb_strlen($delimiter);

        if ($limit > 0) {
           
            while (count($result) < $limit - 1) {
                $pos = mb_strpos($current_string, $delimiter);
                if ($pos === false) {
                    break; 
                }
                $result[] = mb_substr($current_string, 0, $pos);
                $current_string = mb_substr($current_string, $pos + $delimiter_length);
            }
            
            $result[] = $current_string;
            return $result;
        }

        while (($pos = mb_strpos($current_string, $delimiter)) !== false) {
            $result[] = mb_substr($current_string, 0, $pos);
            $current_string = mb_substr($current_string, $pos + $delimiter_length);
        }
        $result[] = $current_string; 
        
        return array_slice($result, 0, $limit);
    }
}

/* https://www.php.net/manual/de/function.gzopen.php */
if( !function_exists('gzopen') && function_exists('gzopen64') ){
	function gzopen( $filename, $mode, $use_include_path = 0 ){
		return gzopen64( $filename, $mode, $use_include_path );
	}
}

/* If not, the function is defined as an empty function */
if( !function_exists('gpSettingsOverride') ){
	function gpSettingsOverride(){}
}
