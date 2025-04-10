<?php

namespace gp\tool\Output;

defined('is_running') or die('Not an entry point...');

includeFile('thirdparty/cssmin_v.1.0.php');

/**
 * Combines CSS files, handling @import rules and fixing relative url() paths.
 */
class CombineCSS {

    private string $base_dir;
    private string $entry_file_rel; // Relative path from base_dir
    private array $processed_files = []; // Prevent infinite loops
    private string $final_css = '';
    private string $preserved_imports = ''; // For external or media-specific imports

    /**
     * Constructor
     *
     * @param string $entry_file Relative path to the main CSS file from $dataDir.
     */
    public function __construct(string $entry_file) {
        global $dataDir; // Assuming $dataDir ends *without* a slash

        if (!class_exists('\cssmin')) {
            throw new \Exception('cssmin class not found. Ensure thirdparty/cssmin_v.1.0.php is included correctly.');
        }
        if (empty($dataDir) || !is_dir($dataDir)) {
             throw new \Exception('$dataDir is not a valid directory.');
        }

        $this->base_dir = rtrim($dataDir, '/');
        $this->entry_file_rel = ltrim($entry_file, '/');

        $full_entry_path = $this->base_dir . '/' . $this->entry_file_rel;

        if (!file_exists($full_entry_path) || !is_readable($full_entry_path)) {
            trigger_error('CombineCSS: Entry file not found or not readable: ' . $full_entry_path, E_USER_WARNING);
            $this->final_css = '/* CombineCSS Error: Entry file not found: ' . htmlspecialchars($entry_file) . ' */';
            return;
        }

        $this->processFile($this->entry_file_rel);

        // Minify *after* all processing
        $this->final_css = \cssmin::minify($this->preserved_imports . $this->final_css);
    }

    /**
     * Public getter for the combined and minified CSS content.
     *
     * @return string
     */
    public function getContent(): string {
        return $this->final_css;
    }

    /**
     * Recursively processes a CSS file, handling imports and fixing URLs.
     * @param string $file_rel Relative path of the CSS file from base_dir.
     * @return string The processed CSS content of this file and its imports.
     */
    private function processFile(string $file_rel): string {
        $full_path = $this->base_dir . '/' . $file_rel;
        $real_path = realpath($full_path); // Resolve symlinks, etc. for accurate tracking

        // Prevent infinite loops for circular imports
        if (!$real_path || isset($this->processed_files[$real_path])) {
            trigger_error('CombineCSS: Skipped duplicate or invalid import: ' . $file_rel, E_USER_NOTICE);
            return '/* CombineCSS Warning: Skipped duplicate import of ' . htmlspecialchars($file_rel) . ' */' . "\n";
        }

        if (!is_readable($full_path)) {
             trigger_error('CombineCSS: Could not read file: ' . $full_path, E_USER_WARNING);
             return '/* CombineCSS Error: Could not read ' . htmlspecialchars($file_rel) . ' */' . "\n";
        }

        $this->processed_files[$real_path] = true; // Mark as processed

        $content = file_get_contents($full_path);
        if ($content === false) {
             trigger_error('CombineCSS: Failed to get contents of: ' . $full_path, E_USER_WARNING);
             return '/* CombineCSS Error: Failed read for ' . htmlspecialchars($file_rel) . ' */' . "\n";
        }

        // 1. Process @import rules first
        $content = $this->processImports($content, $file_rel);

        // 2. Fix relative url() paths
        $content = $this->processUrls($content, $file_rel);

        // Remove this file from processed list *after* processing its children
        // Allows the same file to be imported via different paths if needed, though generally avoided
        // unset($this->processed_files[$real_path]);
        
        return $content;
    }

    /**
     * Finds and processes @import rules within CSS content.
     * @return string CSS content with local imports replaced.
     */
    private function processImports(string $content, string $file_rel): string {
        
        $regex = '/@import\s+(?:url\(\s*(?:(["\']?)([^)"\'\s]+)\1?)\s*\)|(["\'])([^"\']+)\3)\s*([^;]*)?;/i';

        return preg_replace_callback($regex, function ($matches) use ($file_rel) {
            $import_statement = $matches[0];
            $url = !empty($matches[2]) ? trim($matches[2]) : trim($matches[4]);
            $media_query = isset($matches[5]) ? trim($matches[5]) : '';

            // --- Conditions to PRESERVE @import ---
            // 1. External URL
            if (str_contains($url, '//') || str_starts_with($url, 'http:') || str_starts_with($url, 'https:')) {
                // Add unique preserved imports
                 if (strpos($this->preserved_imports, $import_statement) === false) {
                    $this->preserved_imports .= $import_statement . "\n";
                }
                return ''; // Remove from current content
            }

            // 2. Media Query is present
            if (!empty($media_query)) {
                // Resolve the path relative to the *current* file for the preserved import
                $import_file_rel = $this->resolvePath(dirname($file_rel), $url);
                $preserved_import_rule = '@import url("' . htmlspecialchars($import_file_rel) . '") ' . $media_query . ';';
                 if (strpos($this->preserved_imports, $preserved_import_rule) === false) {
                    $this->preserved_imports .= $preserved_import_rule . "\n";
                }
                return ''; // Remove from current content
            }

            // --- Condition to INLINE @import ---
            // Local import without media query
            $import_file_rel = $this->resolvePath(dirname($file_rel), $url);
            $import_full_path = $this->base_dir . '/' . $import_file_rel;

            if (!file_exists($import_full_path)) {
                trigger_error('CombineCSS: Imported file not found: ' . $import_full_path . ' (referenced in ' . $file_rel . ')', E_USER_WARNING);
                return '/* CombineCSS Error: Import not found: ' . htmlspecialchars($url) . ' */';
            }

            // Recursively process the imported file
            return $this->processFile($import_file_rel);

        }, $content);
    }


    /**
     * Finds and fixes relative url() paths within CSS content.
     * @return string CSS content with url() paths fixed.
     */
    private function processUrls(string $content, string $file_rel): string {
        
        $regex = '/url\(\s*(["\']?)(?!(?:["\']?(?:(?:[a-z]+:)?\/\/|\/|data:|#)))([^)"\']+)\1\s*\)/i';

        return preg_replace_callback($regex, function ($matches) use ($file_rel) {
            $original_url = trim($matches[2]);
            $quote = $matches[1]; // Preserve original quote style

            // Resolve the path relative to the *current* file's directory
            $absolute_path = $this->resolvePath(dirname($file_rel), $original_url);

            // Return the corrected url() statement with an absolute path from base_dir
            
            return 'url(' . $quote . '/' . ltrim($absolute_path,'/') . $quote . ')';

        }, $content);
    }


    /**
     * Resolves a relative path or URL against a base directory path.
     * Handles "../", "./", and ensures the path is relative to the base_dir.
     * @return string The canonicalized path relative to $this->base_dir.
     */
    private function resolvePath(string $base_path, string $relative_path): string {
        // Normalize base path (remove trailing '.', ensure it's a dir)
        if ($base_path === '.') {
            $base_path = '';
        } else {
           $base_path = rtrim($base_path, '/');
        }

        // If relative path is already absolute (shouldn't happen with URL regex but check anyway)
        if (str_starts_with($relative_path, '/')) {
             return ltrim($relative_path, '/');
        }

        $full_path = $base_path ? $base_path . '/' . $relative_path : $relative_path;

        // Canonicalize the path (resolve ../ and ./) - Stack-based approach
        $parts = explode('/', $full_path);
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part || '' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return implode('/', $absolutes);
    }

    /**
     * Public access to the list of processed files (absolute paths).
     * Useful for debugging or cache invalidation.
     * @return array
     */
    public function getProcessedFiles(): array {
        return array_keys($this->processed_files);
    }

    /**
     * Public access to the preserved @import rules.
     * @return string
     */
     public function getPreservedImports(): string {
         return $this->preserved_imports;
     }
}