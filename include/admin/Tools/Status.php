<?php

declare(strict_types=1); // Enforce strict types for better code quality

namespace gp\admin\Tools;

// Ensure this script is run within the context of the application
defined('is_running') or die('Not an entry point...');

class Status extends \gp\special\Base
{
	protected int $check_dir_len = 0;
	protected int $failed_count = 0;
	/** @var list<string> */
	protected array $failed = [];
	protected int $passed_count = 0;
	protected int $show_failed_max = 50;
	/** @var list<string> */
	protected array $deletable = [];
	protected string|int|null $euid = null; // Can be string '?', int from posix_geteuid, or null initially

	public function __construct()
	{
		// Constructor remains empty if no initialization is needed here
	}

	public function RunScript(): void
	{
		global $langmessage; // Access global variable

		if (!is_array($langmessage)) {
			// Handle missing/invalid global gracefully
			$langmessage = ['Site Status' => 'Site Status']; // Provide default
			trigger_error('$langmessage global variable not available or not an array.', E_USER_WARNING);
		}

		echo '<h2>' . htmlspecialchars($langmessage['Site Status'] ?? 'Site Status') . '</h2>';

		$cmd = \gp\tool::GetCommand();
		switch ($cmd) {
			case 'FixOwner':
				$this->FixOwner();
				break;
		}
		$this->CheckDataDir();
		$this->DefaultDisplay();
	}

	public function CheckDataDir(): void
	{
		global $dataDir; // Access global variable

		if (!is_string($dataDir) || $dataDir === '') {
			// Handle missing/invalid global gracefully
			trigger_error('$dataDir global variable not available or empty.', E_USER_ERROR);
			echo '<p class="gp_error">Configuration error: dataDir is not set.</p>';
			return;
		}

		$this->check_dir_len = 0;
		$this->failed_count = 0;
		$this->passed_count = 0;
		$this->failed = [];
		$this->deletable = [];
		$this->show_failed_max = 50;

		$check_dir = $dataDir . '/data';
		$this->check_dir_len = strlen($check_dir);
		$this->euid = '?'; // Default string value

		// Check if POSIX functions are available and get effective user ID
		if (function_exists('posix_geteuid')) {
			$euid = posix_geteuid();
			// posix_geteuid returns int, assign directly
			$this->euid = $euid;
		}

		if (is_dir($check_dir)) {
			$this->CheckDir($check_dir);
		} else {
			echo '<p class="gp_error">Error: Data directory does not exist: ' . htmlspecialchars($check_dir) . '</p>';
			$this->failed_count = 1; // Mark as failed if base dir doesn't exist
			$this->failed[] = $check_dir;
		}
	}

	public function DefaultDisplay(): void
	{
		global $langmessage, $dataDir; // Access global variables

		if (!is_array($langmessage)) {
			$langmessage = []; // Prevent errors if not set
		}
		if (!is_string($dataDir)) {
			$dataDir = ''; // Prevent errors if not set
		}

		$check_dir = $dataDir . '/data';
		$checked = $this->passed_count + $this->failed_count;

		if ($this->failed_count === 0) {
			echo '<p class="gp_passed">';
			echo sprintf(
				htmlspecialchars($langmessage['data_check_passed'] ?? 'Passed %d checks out of %d.'),
				$checked,
				$checked
			);
			echo '</p>';
			$this->ShowDeletable();
			return;
		}

		echo '<p class="gp_notice">';
		echo sprintf(
			htmlspecialchars($langmessage['data_check_failed'] ?? 'Failed %d checks out of %d.'),
			$this->failed_count,
			$checked
		);
		echo '</p>';

		// Special message if only the main /data directory isn't writable/accessible
		if (count($this->failed) === 1 && $this->failed[0] === $check_dir) {
			echo '<p class="gp_notice">';
			echo '<b>WARNING:</b> Your data directory is not accessible or writable: ' . htmlspecialchars($check_dir);
			echo '</p>';
			// Don't show the table if only the root data dir failed
			return;
		}

		if ($this->failed_count > $this->show_failed_max) {
			echo '<p class="gp_notice">';
			echo sprintf(
				htmlspecialchars($langmessage['showing_max_failed'] ?? 'Showing first %d failed items.'),
				$this->show_failed_max
			);
			echo '</p>';
		}

		echo '<table class="bordered">';
		echo '<thead><tr><th>';
		echo htmlspecialchars($langmessage['file_name'] ?? 'File/Folder');
		echo '</th><th>';
		echo htmlspecialchars($langmessage['File Owner'] ?? 'File Owner');
		echo '<br/>';
		echo htmlspecialchars($langmessage['Current_Value'] ?? 'Current');
		echo '</th><th>';
		echo '<br/>';
		echo htmlspecialchars($langmessage['Expected_Value'] ?? 'Expected');
		echo '</th><th> &nbsp;'; // Actions column
		echo '</th></tr></thead>';
		echo '<tbody>';

		// Sort by path length (directories often shorter, appear first)
		usort($this->failed, fn (string $a, string $b): int => strlen($a) <=> strlen($b));

		$shown_count = 0;
		foreach ($this->failed as $path) {
			if ($shown_count >= $this->show_failed_max) {
				break;
			}

			$readable_path = substr($path, $this->check_dir_len);
			if ($readable_path === '') {
				$readable_path = '/'; // Represent the base data dir
			}

			// Attempt to get file UID if function exists
			$file_euid = '?'; // Default if cannot determine
			if (class_exists('\gp\install\FilePermissions') && method_exists('\gp\install\FilePermissions', 'file_uid')) {
				$file_euid = \gp\install\FilePermissions::file_uid($path);
			}

			echo '<tr><td>';
			echo htmlspecialchars($readable_path);
			echo '</td><td>';
			echo htmlspecialchars($this->ShowUser($file_euid));
			echo '</td><td>';
			echo htmlspecialchars($this->ShowUser($this->euid));
			echo '</td><td>';
			// Only show Fix link if POSIX functions likely available (used by ShowUser)
			if (function_exists('posix_geteuid')) {
				echo \gp\tool::Link(
					'Admin/Status',
					'Fix', // $langmessage['Fix'] ?? 'Fix'
					'cmd=FixOwner&path=' . rawurlencode($readable_path),
					['data-cmd' => 'cnreq', 'title' => 'Attempt to fix ownership/permissions']
				);
			} else {
				echo '&nbsp;'; // No fix action available
			}
			echo '</td></tr>';
			$shown_count++;
		}

		echo '</tbody></table>';

		// Only check for orphans if the primary checks didn't completely fail
		if (!(count($this->failed) === 1 && $this->failed[0] === $check_dir)) {
			$this->CheckPageFiles();
		}

		$this->ShowDeletable();
	}

	/**
	 * Show Deletable Files found during scan
	 */
	protected function ShowDeletable(): void
	{
		if (empty($this->deletable)) {
			return;
		}
		echo '<h3>Deletable Files</h3>';
		echo '<p>The following files or folders were marked as deletable during the scan:</p>';
		echo '<ol>';
		foreach ($this->deletable as $file) {
			echo '<li>' . htmlspecialchars($file) . '</li>';
		}
		echo '</ol>';
	}

	/**
	 * Check page files for orphaned data files
	 */
	protected function CheckPageFiles(): void
	{
		global $dataDir, $gp_index; // Access global variables

		if (!is_string($dataDir) || $dataDir === '') {
			trigger_error('$dataDir global variable not available or empty for CheckPageFiles.', E_USER_WARNING);
			return;
		}
		if (!is_array($gp_index)) {
			trigger_error('$gp_index global variable not available or not an array for CheckPageFiles.', E_USER_WARNING);
			$gp_index = []; // Prevent errors
		}

		$pages_dir = $dataDir . '/data/_pages';
		if (!is_dir($pages_dir)) {
			// If _pages dir doesn't exist, nothing to check
			return;
		}

		// Use Files helper if available, otherwise fallback or skip
		if (!class_exists('\gp\tool\Files') || !method_exists('\gp\tool\Files', 'ReadDir')) {
			echo '<p class="gp_notice">Cannot check for orphaned files: Files helper is unavailable.</p>';
			return;
		}

		$all_files_in_dir = \gp\tool\Files::ReadDir($pages_dir, 'php');
		$all_page_data_files = [];
		foreach ($all_files_in_dir as $file) {
			// Assuming ReadDir returns filenames without extension
			$all_page_data_files[] = $pages_dir . '/' . $file . '.php';
		}

		$active_page_files = [];
		if (method_exists('\gp\tool\Files', 'PageFile')) {
			foreach (array_keys($gp_index) as $slug) {
				if(is_string($slug)){ // Ensure slug is a string
					$active_page_files[] = \gp\tool\Files::PageFile($slug);
				}
			}
		} else {
			echo '<p class="gp_notice">Cannot check for orphaned files: PageFile helper is unavailable.</p>';
			return;
		}


		// Find files present in the directory but not in the active page index
		$diff = array_diff($all_page_data_files, $active_page_files);

		if (empty($diff)) {
			return;
		}

		echo '<h2>Orphaned Data Files</h2>';
		echo '<p>The following data files exist in the <code>_pages</code> directory but do not seem to correspond to any currently active page. They might be remnants of deleted pages. Before removing them, consider backing them up.</p>';
		echo '<table class="bordered"><thead><tr><th>File Path</th></tr></thead>';
		echo '<tbody>';
		foreach ($diff as $file) {
			echo '<tr><td>' . htmlspecialchars($file) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Check the ownership/permissions of a directory and recursively its contents.
	 */
	protected function CheckDir(string $dir): void
	{
		// First, check the directory itself
		if (!$this->CheckFile($dir, 'dir')) {
			// If the directory check failed (e.g., permissions), maybe don't recurse?
			// Original logic continued, so we keep that. If dir isn't readable, opendir will fail.
			// $this->failed[] is already populated by CheckFile if it fails.
		}

		// Attempt to open the directory
		// Use error suppression carefully, consider try-catch for FilesystemIterator later if refactoring
		$dh = @opendir($dir);
		if ($dh === false) {
			// If opendir failed after CheckFile potentially passed (e.g., race condition or complex ACLs)
			// ensure it's marked as failed if not already.
			if (!in_array($dir, $this->failed, true)) {
				$this->failed_count++;
				$this->failed[] = $dir;
			}
			// Cannot proceed further into this directory
			return;
		}

		while (($file = readdir($dh)) !== false) {
			if ($file === '.' || $file === '..') {
				continue;
			}

			$full_path = $dir . '/' . $file;

			// Skip symbolic links to avoid issues, unless specifically needed
			if (is_link($full_path)) {
				continue;
			}

			// Check for specially named deletable files/folders
			if (preg_match('#/x-deletable-[0-9]+$#', $full_path)) {
				if (!in_array($full_path, $this->deletable, true)) {
					$this->deletable[] = $full_path;
				}
				continue; // Don't check permissions on deletable items
			}

			if (is_dir($full_path)) {
				$this->CheckDir($full_path); // Recurse into subdirectory
			} else {
				$this->CheckFile($full_path, 'file'); // Check file
			}
		}

		closedir($dh); // Always close the directory handle
	}

	/**
	 * Check the ownership/permissions of a single file or directory.
	 * Returns true if checks pass, false otherwise.
	 */
	protected function CheckFile(string $path, string $type = 'dir'): bool
	{
		// Use FilePermissions helper if available
		if (class_exists('\gp\install\FilePermissions') && method_exists('\gp\install\FilePermissions', 'HasFunctions')) {
			if (\gp\install\FilePermissions::HasFunctions()) {
				$perms = @fileperms($path);
				if ($perms === false) {
					// Cannot get permissions, count as failed
					$this->failed_count++;
					$this->failed[] = $path;
					return false;
				}
				// Get last 3 digits of octal representation
				$current_perms = substr(sprintf('%o', $perms), -3);

				if ($type === 'file') {
					$expected_perms = \gp\install\FilePermissions::getExpectedPerms_file($path);
				} else {
					$expected_perms = \gp\install\FilePermissions::getExpectedPerms($path);
				}

				// Compare permissions using helper
				if (\gp\install\FilePermissions::perm_compare($expected_perms, $current_perms)) {
					$this->passed_count++;
					return true;
				} else {
					// Permission mismatch, count as failed
					$this->failed_count++;
					$this->failed[] = $path;
					return false;
				}
			}
		}

		// Fallback check: Check if writable (less precise but better than nothing)
		// Use standard is_writable if gp_is_writable doesn't exist
		$is_writable_func = function_exists('gp_is_writable') ? 'gp_is_writable' : 'is_writable';

		if ($is_writable_func($path)) {
			$this->passed_count++;
			return true;
		}

		// Fallback check failed
		$this->failed_count++;
		$this->failed[] = $path;
		return false;
	}

	/**
	 * Display a user name and UID if possible.
	 */
	protected function ShowUser(int|string|null $uid): string
	{
		// If UID is not an integer or posix functions are not available, just return the UID as string
		if (!is_int($uid) || !function_exists('posix_getpwuid')) {
			return (string) $uid; // Cast null or string '?' to string
		}

		// Attempt to get user info only if we have an integer UID
		$user_info = posix_getpwuid($uid);

		if (is_array($user_info) && isset($user_info['name'])) {
			// Found user info, return name and UID
			return $user_info['name'] . ' (' . $uid . ')';
		} else {
			// Function failed or user not found, return only UID
			return (string) $uid;
		}
	}

	/**
	 * Attempt to fix the ownership/permissions issue of the posted file/folder.
	 * This is a potentially risky operation.
	 */
	public function FixOwner(): void
	{
		global $dataDir, $langmessage; // Access global variables

		if (!is_array($langmessage)) {
			$langmessage = []; // Prevent errors
		}
		if (!is_string($dataDir) || $dataDir === '') {
			trigger_error('$dataDir global variable not available or empty for FixOwner.', E_USER_ERROR);
			msg($langmessage['OOPS'] ?? 'Oops!' . ' Configuration error.');
			return;
		}

		// Basic input check
		if (!isset($_REQUEST['path']) || !is_string($_REQUEST['path']) || $_REQUEST['path'] === '') {
			msg($langmessage['OOPS'] ?? 'Oops!' . ' Invalid request path.');
			return;
		}

		// Path received from URL needs decoding; should correspond to $readable_path
		$relative_path = $_REQUEST['path'];
		$to_fix = '/data' . $relative_path;
		$to_fix_full = $dataDir . $to_fix;

		// Use Files helper for path checking if available
		if (class_exists('\gp\tool\Files') && method_exists('\gp\tool\Files', 'CheckPath')) {
			if (!\gp\tool\Files::CheckPath($to_fix_full, false)) { // Check existence without is_executable
				msg(($langmessage['OOPS'] ?? 'Oops!') . ' Invalid or non-existent path: ' . htmlspecialchars($to_fix));
				return;
			}
		} elseif (!file_exists($to_fix_full)) {
			// Basic fallback check
			msg(($langmessage['OOPS'] ?? 'Oops!') . ' Path does not exist: ' . htmlspecialchars($to_fix));
			return;
		}

		// Check dependencies for FileSystem and Port helpers
		if (!class_exists('\gp\tool\FileSystem') || !method_exists('\gp\tool\FileSystem', 'TempFile')) {
			msg(($langmessage['OOPS'] ?? 'Oops!') . ' FileSystem helper unavailable.');
			return;
		}
		if (!class_exists('\gp\admin\Tools\Port') || !method_exists('\gp\admin\Tools\Port', 'CopyAll')) {
			msg(($langmessage['OOPS'] ?? 'Oops!') . ' Port helper unavailable.');
			return;
		}
		if (!class_exists('\gp\tool\Files') || !method_exists('\gp\tool\Files', 'RmAll')) {
			// RmAll is needed for cleanup
		}


		// Generate temporary paths using the helper
		$new_file_rel = \gp\tool\FileSystem::TempFile($to_fix); // Relative path from dataDir
		$new_file_full = $dataDir . $new_file_rel;
		$deletable_rel = \gp\tool\FileSystem::TempFile(dirname($to_fix) . '/x-deletable'); // Relative path
		$deletable_full = $dataDir . $deletable_rel;

		echo '<h4>Attempting to fix: ' . htmlspecialchars($to_fix) . '</h4>';
		echo '<ol>';

		// 1. Copy original to new temporary location (should inherit correct owner/perms)
		echo '<li>Copy: ' . htmlspecialchars($to_fix) . ' &rarr; ' . htmlspecialchars($new_file_rel) . '</li>';
		if (!\gp\admin\Tools\Port::CopyAll($to_fix_full, $new_file_full)) {
			echo '<li style="color: red;">Failed: Could not copy.</li>';
			echo '</ol>';
			msg(($langmessage['OOPS'] ?? 'Oops!') . ' Failed to create temporary copy.');
			// Attempt cleanup of potentially partially created new file
			if (file_exists($new_file_full) && class_exists('\gp\tool\Files') && method_exists('\gp\tool\Files', 'RmAll')) {
				\gp\tool\Files::RmAll($new_file_full);
			}
			return;
		}

		// 2. Move original to a deletable location
		echo '<li>Move (Original): ' . htmlspecialchars($to_fix) . ' &rarr; ' . htmlspecialchars($deletable_rel) . '</li>';
		if (!@rename($to_fix_full, $deletable_full)) {
			echo '<li style="color: red;">Failed: Could not move original to deletable location.</li>';
			echo '</ol>';
			msg(($langmessage['OOPS'] ?? 'Oops!') . ' Rename to deletable location failed.');
			// Attempt cleanup of the temporary copy
			if (class_exists('\gp\tool\Files') && method_exists('\gp\tool\Files', 'RmAll')) {
				\gp\tool\Files::RmAll($new_file_full);
			}
			return;
		}

		// 3. Move the new copy into the original's place
		echo '<li>Move (New Copy): ' . htmlspecialchars($new_file_rel) . ' &rarr; ' . htmlspecialchars($to_fix) . '</li>';
		if (!@rename($new_file_full, $to_fix_full)) {
			echo '<li style="color: red;">Failed: Could not move new copy into original place.</li>';
			echo '</ol>';
			msg(($langmessage['OOPS'] ?? 'Oops!') . ' Rename to original location failed.');
			// CRITICAL: Try to move the original back from deletable
			if (@rename($deletable_full, $to_fix_full)) {
				msg('Attempted to restore original file.');
			} else {
				msg('CRITICAL ERROR: Could not restore original file. Manual intervention required.');
			}
			// Cleanup the temporary copy if it still exists somehow
            if (file_exists($new_file_full) && class_exists('\gp\tool\Files') && method_exists('\gp\tool\Files', 'RmAll')) {
                \gp\tool\Files::RmAll($new_file_full);
            }
			return;
		}

		echo '<li style="color: green;">Success: File/folder replaced.</li>';

		// 4. Attempt to remove the old version from the deletable location
		if (class_exists('\gp\tool\Files') && method_exists('\gp\tool\Files', 'RmAll')) {
			if (!\gp\tool\Files::RmAll($deletable_full)) {
				echo '<li>Note: Deletable temporary file/folder ' . htmlspecialchars($deletable_rel) . ' could not be removed. It might require manual deletion.</li>';
			} else {
				echo '<li>Cleanup: Removed temporary original from ' . htmlspecialchars($deletable_rel) . '.</li>';
			}
		} else {
			echo '<li>Note: Could not attempt cleanup of ' . htmlspecialchars($deletable_rel) . ' (RmAll helper missing).</li>';
		}

		echo '</ol>';
		msg('Fix attempt completed. Please refresh the status page to verify.', true); // Success message
	}
}

// Helper function 'msg' needs to be defined globally or within the scope where this class is used.
// Example definition:
if (!function_exists('msg')) {
	function msg(string $message, bool $isSuccess = false): void {
		echo '<div class="gp_' . ($isSuccess ? 'passed' : 'error') . '">' . htmlspecialchars($message) . '</div>';
	}
}

// gp\tool::GetCommand(), gp\tool::Link(), gp\tool\Files::*, gp\tool\FileSystem::*,
// gp\install\FilePermissions::*, gp\admin\Tools\Port::*, gp_is_writable()
// and globals $langmessage, $dataDir, $gp_index
// MUST all be defined and compatible with PHP 8.4 elsewhere in your application.