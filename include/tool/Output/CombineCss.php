<?php
namespace gp\tool\Output;

defined('is_running') or die('Not an entry point...');

class CombineCSS {

    // Public properties to access results
    public $combined_content_raw = ''; // The combined, path-fixed CSS before minification
    public $final_content = '';      // The final output (minified if requested/possible)
    public $prepended_imports = '';  // @import rules that were kept (remote, media queries)
    public $processed_files = [];    // Files processed in this instance to prevent loops

    // Internal properties
    private $entry_file_path;        // Normalized path relative to $dataDir for the initial file
    private $minify_output;          // Flag whether to minify the final output
    private $dataDir_norm;           // Normalized $dataDir path

    /**
     * Constructor
     *
     * @param string $file Path to the entry CSS file, relative to $dataDir
     * @param bool $minify Whether to minify the final output
     */
    public function __construct($file, $minify = true) {
        global $dataDir;
        $this->minify_output = $minify;

        // --- Path Normalization ---
        // Ensure $dataDir ends with a slash and uses forward slashes
        $this->dataDir_norm = rtrim(str_replace('\\', '/', $dataDir), '/') . '/';
        // Ensure $file starts without a slash and uses forward slashes
        $this->entry_file_path = ltrim(str_replace('\\', '/', $file), '/');

        $full_path = $this->dataDir_norm . $this->entry_file_path;
        $real_full_path = realpath($full_path);

        if (!$real_full_path || !file_exists($real_full_path)) {
            trigger_error('CombineCSS: Entry file not found: ' . htmlspecialchars($full_path), E_USER_WARNING);
            $this->final_content = '/* CombineCSS Error: Entry file not found: ' . htmlspecialchars($file) . ' */';
            return;
        }

        // --- Load Minifier if needed ---
        if ($this->minify_output) {
            // Consider adding a check if the class/function exists after include
            if (!class_exists('\cssmin')) {
                 includeFile('thirdparty/cssmin_v.1.0.php');
                 if (!class_exists('\cssmin')) {
                      trigger_error('CombineCSS: cssmin class not found after including thirdparty/cssmin_v.1.0.php. Minification disabled.', E_USER_WARNING);
                      $this->minify_output = false; // Disable minification if class isn't there
                 }
            }
        }

        // --- Start Processing ---
        $this->processed_files = []; // Reset for this instance
        $this->prepended_imports = '';
        $this->combined_content_raw = $this->processFile($real_full_path);

        // Prepend any imports that were kept
        $this->combined_content_raw = $this->prepended_imports . $this->combined_content_raw;

        // --- Final Minification ---
        
		if ($this->minify_output) {
    try {
        if (class_exists('\cssmin')) {
            $content_size_bytes = strlen($this->combined_content_raw);
            $size_limit_bytes = 10 * 1024;
            $first_chunk = substr($this->combined_content_raw, 0, 15);
            $space_count = substr_count($first_chunk, ' ');
            if ($content_size_bytes >= $size_limit_bytes && $space_count < 3) {
                $this->final_content = $this->combined_content_raw;
            } else {
                 $this->final_content = \cssmin::minify($this->combined_content_raw);
                   }
        } else {
            $this->final_content = $this->combined_content_raw;
               }
        } catch (\Exception $e) { // potential exceptions from cssmin::minify
              error_log("CSS Minification failed: " . $e->getMessage()); 
              $this->final_content = $this->combined_content_raw;
              }
        } else {
            $this->final_content = $this->combined_content_raw;
             }		
 }

    /**
     * Process a single CSS file: read, handle imports, fix URLs.
     * Returns the processed (but not minified) CSS content.
     *
     * @param string $real_path Absolute filesystem path to the CSS file.
     * @return string Processed CSS content.
     */
    private function processFile($real_path) {
        // --- Prevent Infinite Loops ---
        if (isset($this->processed_files[$real_path])) {
            trigger_error('CombineCSS: Recursive import detected and skipped for: ' . htmlspecialchars($real_path), E_USER_WARNING);
            return '/* CombineCSS Error: Recursive import skipped: ' . htmlspecialchars(basename($real_path)) . ' */';
        }
        $this->processed_files[$real_path] = true;

        // --- Read File ---
        $content = @file_get_contents($real_path); // Use @ to suppress warning, check result
        if ($content === false) {
            trigger_error('CombineCSS: Could not read file: ' . htmlspecialchars($real_path), E_USER_WARNING);
            unset($this->processed_files[$real_path]); // Allow reprocessing if attempted again? Maybe not.
            return '/* CombineCSS Error: Could not read file: ' . htmlspecialchars(basename($real_path)) . ' */';
        }

        $source_dir = dirname($real_path); // Directory of the current file
        $output_buffer = '';
        $offset = 0;

        // --- Process @import Rules ---
        // Regex to find @import rules, handling url() and quoted strings
        $import_pattern = '/@import\s+(?:url\(\s*([\'"]?)(.*?)\1\s*\)|([\'"])(.*?)\3)\s*([^;]*);/i';

        while (preg_match($import_pattern, $content, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $match_info = $matches[0]; // [full_match_string, offset]
            $full_match = $match_info[0];
            $match_start = $match_info[1];

            // Append the content *before* this @import rule, fixing its URLs
            $content_before = substr($content, $offset, $match_start - $offset);
            $output_buffer .= $this->fixUrlsInBlock($content_before, $source_dir);

            // Extract import details
            // Group 2 is URL from url('...'), Group 4 is URL from "..."
            $import_url = trim(!empty($matches[2][0]) ? $matches[2][0] : $matches[4][0]);
            $media_query = trim($matches[5][0]);

            $is_remote = preg_match('#^(https?:|//)#i', $import_url);
            $has_media = !empty($media_query);

            if ($is_remote || $has_media) {
                // --- Keep Import Rule ---
                $resolved_import_url = $import_url;
                if (!$is_remote && !$this->isAbsoluteUrl($import_url)) {
                    // Resolve local path relative to *this* file's directory
                    $resolved_import_url = $this->resolvePath($import_url, $source_dir);
                }
                // Add to the list of imports to prepend later
                $this->prepended_imports .= '@import url("' . $resolved_import_url . '")' . ($has_media ? ' ' . $media_query : '') . ";\n";

            } else {
                // --- Inline Import Rule ---
                if ($this->isAbsoluteUrl($import_url)) {
                     // Handle root-relative imports (e.g., /css/other.css)
                     $import_full_path = $this->dataDir_norm . ltrim($import_url, '/');
                } else {
                     // Handle relative imports (e.g., ../common.css)
                     $import_full_path = $source_dir . '/' . $import_url;
                }

                // Canonicalize and get real path
                $import_real_path = realpath($this->normalizePath($import_full_path));

                if ($import_real_path && file_exists($import_real_path)) {
                    // Recursively process the imported file
                    $imported_content = $this->processFile($import_real_path);
                    $output_buffer .= $imported_content; // Append processed content
                } else {
                    trigger_error('CombineCSS: Imported file not found or path error: ' . htmlspecialchars($import_full_path) . ' (referenced in ' . htmlspecialchars($real_path) . ')', E_USER_WARNING);
                    $output_buffer .= '/* CombineCSS Error: Import not found: ' . htmlspecialchars($import_url) . ' */';
                }
            }

            // Move offset past the processed @import rule
            $offset = $match_start + strlen($full_match);

        } // End while loop for @import

        // --- Process Remaining Content ---
        // Append the rest of the file content (after the last @import), fixing its URLs
        $content_after = substr($content, $offset);
        $output_buffer .= $this->fixUrlsInBlock($content_after, $source_dir);

        // Clean up recursion tracking for this file (allows it to be imported again via a different path if needed, though generally discouraged)
        // unset($this->processed_files[$real_path]); // Optional: Decide if a file should *never* be processed twice per instance

        return $output_buffer;
    }

    /**
     * Finds all url() references in a block of CSS and fixes relative paths.
     *
     * @param string $content_block CSS content segment.
     * @param string $source_dir Absolute directory path of the file this content came from.
     * @return string CSS content segment with URLs fixed.
     */
    private function fixUrlsInBlock($content_block, $source_dir) {
        if (empty($content_block) || strpos($content_block, 'url(') === false) {
            return $content_block;
        }

        // Regex to find url(...) patterns, handling optional quotes
        $url_pattern = '/url\(\s*([\'"]?)(.*?)\1\s*\)/i';

        return preg_replace_callback(
            $url_pattern,
            function ($matches) use ($source_dir) {
                $original_match = $matches[0]; // The full url(...) match
                $url = trim($matches[2]);     // The actual URL inside

                // Don't modify absolute URLs, data URIs, or empty URLs
                if (empty($url) || $this->isAbsoluteUrl($url) || strncasecmp($url, 'data:', 5) === 0) {
                    return $original_match; // Return the original match unchanged
                }

                // Resolve the relative path
                $resolved_url = $this->resolvePath($url, $source_dir);

                // Return the corrected url() string, always quoted for safety
                return 'url("' . $resolved_url . '")';
            },
            $content_block
        );
    }

    /**
     * Resolves a relative URL from a CSS file to be relative to the web root ($dataDir).
     *
     * @param string $relative_url The relative URL found in the CSS.
     * @param string $source_dir The absolute directory path where the CSS file resides.
     * @return string The resolved URL, typically starting with '/' relative to the web root.
     */
    private function resolvePath($relative_url, $source_dir) {
    	// Separate query string and fragment
    	$query_fragment = '';
    	$path_only = $relative_url;
    	if (($pos = strpos($relative_url, '?')) !== false || ($pos = strpos($relative_url, '#')) !== false) {
         	$query_fragment = substr($relative_url, $pos);
         	$path_only = substr($relative_url, 0, $pos);
    	}

    	$combined_path = $source_dir . '/' . $path_only;
    	$reduced_path = $this->reducePath($this->normalizePath($combined_path));

		//error_log("--- resolvePath Check ---");
    	//error_log("Relative URL: " . $relative_url);
    	//error_log("Source Dir: " . $source_dir);
    	//error_log("Reduced FS Path: " . $reduced_path);
    	//error_log("DataDir Norm: " . $this->dataDir_norm);

    	$starts_with_check = (stripos($reduced_path, $this->dataDir_norm) === 0);
    	//error_log("Does Reduced start w/ DataDir (Case-Insensitive)? " . ($starts_with_check ? 'YES' : 'NO'));

    	if ($starts_with_check) {
	        // Get path part after dataDir
        	$path_relative_to_datadir = substr($reduced_path, strlen($this->dataDir_norm));

        	// --- Use Typesetter's Base URL ---
        	// Assumes \gp\tool::GetUrl('') returns http://localhost/T53test5g5/
        	// Or use \gp\tool::GetDir('') if that returns /T53test5g5/
        	$base_url = \gp\tool::GetDir(''); // Get base path like /T53test5g5/
        	$final_web_path = rtrim($base_url, '/') . '/' . ltrim($path_relative_to_datadir, '/');
        	// --- End Modification ---

        	//error_log("Resolved Web Path (Using Base Path): " . $final_web_path . $query_fragment);
        	return $final_web_path . $query_fragment;
    	} else {
        	// ... (existing trigger_error code) ...
        	$fallback_path = $path_only;
        	//error_log("Resolved Fallback Path (Outside dataDir): " . $fallback_path . $query_fragment);
        	return $fallback_path . $query_fragment;
    	}
	}

    /**
     * Normalizes a path: forward slashes, no duplicate slashes.
     *
     * @param string $path
     * @return string
     */
    private function normalizePath($path) {
        return preg_replace('#/+#', '/', str_replace('\\', '/', $path));
    }

    /**
     * Canonicalizes a path by resolving '/./' and '/../'.
     *
     * @param string $path Normalized path.
     * @return string Canonicalized path.
     */
    private function reducePath($path) {
        $parts = explode('/', $path);
        $result = [];
        $is_absolute = str_starts_with($path, '/');

        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                // Only pop if result is not empty and the last element is not '..'
                // This prevents going above the root in relative paths like ../../file
                 if (!empty($result) && end($result) !== '..') {
                    array_pop($result);
                } elseif (!$is_absolute) {
                    // Keep '..' if path is relative and we are at the start or after other '..'
                     $result[] = '..';
                }
                 // If absolute, popping '..' at the root does nothing
            } else {
                $result[] = $part;
            }
        }

        // Handle the case of an absolute path resolving to root ('/')
        $final_path = implode('/', $result);
        if ($is_absolute) {
             return '/' . $final_path;
        } else {
             // Handle empty result for relative path (e.g. "dir/..") -> "."
             return ($final_path === '') ? '.' : $final_path;
        }
    }

    /**
     * Checks if a URL is absolute (protocol-relative, http, https, data, or root-relative).
     *
     * @param string $url
     * @return bool
     */
    private function isAbsoluteUrl($url) {
        // Scheme relative (//), http/https, data URI, or root relative (/)
        return preg_match('#^(\/\/|https?:|data:|/)#i', $url);
    }
}
