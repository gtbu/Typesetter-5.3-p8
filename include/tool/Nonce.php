<?php

namespace gp\tool;

defined('is_running') or die('Not an entry point...');

/**
 * A class for creating and verifying nonces (numbers used once) , self-contained and uses the secure HMAC method for hashing
 */
class Nonce{

	/**
	 * Hashing algorithm for the nonce. 
	 */
	private const NONCE_ALGO = 'sha512';


	/**
	 * Generate a new nonce.
	 * @param string $action  A string identifying the action.
	 * @param bool   $anon    True if the nonce is for an anonymous user.
	 * @param int    $factor  Determines the nonce's validity period. 
	 * @return string The generated nonce.
	 */
	public static function Create($action = 'none', $anon = false, $factor = 43200){
		global $gpAdmin;

		$nonce_base = $action;
		if (!$anon && !empty($gpAdmin['username'])) {
			$nonce_base .= $gpAdmin['username'];
		}

		return self::Hash($nonce_base, 0, $factor);
	}


	/**
	 * Verify a submitted nonce using a timing-attack-safe comparison.
	 *
	 * @param string $action      A string identifying the action.
	 * @param mixed  $check_nonce The user-submitted nonce. If false, checks $_REQUEST['_gpnonce'].
	 * @param bool   $anon        True if the nonce is for an anonymous user.
	 * @param int    $factor      Determines the nonce's validity period.
	 * @return bool Returns true if the nonce is valid, false otherwise.
	 */
	public static function Verify($action = 'none', $check_nonce = false, $anon = false, $factor = 43200){
		global $gpAdmin;

		if ($check_nonce === false) {
			$check_nonce = $_REQUEST['_gpnonce'] ?? '';
		}

		if (empty($check_nonce) || !is_string($check_nonce)) {
			return false;
		}

		$nonce_base = $action;
		if (!$anon) {
			if (empty($gpAdmin['username'])) {
				return false;
			}
			$nonce_base .= $gpAdmin['username'];
		}
		
		$expected_current = self::Hash($nonce_base, 0, $factor);
		if (hash_equals($expected_current, $check_nonce)) {
			return true;
		}

		$expected_previous = self::Hash($nonce_base, 1, $factor);
		if (hash_equals($expected_previous, $check_nonce)) {
			return true;
		}

		return false;
	}


	/**
	 * Generates a nonce hash now using the secure HMAC method instead of the global \gp\tool::hash().
	 *
	 * @param string $nonce_base  The base string for the nonce.
	 * @param int    $tick_offset An offset for the time tick.
	 * @param int    $factor      Determines the nonce's validity period.
	 * @return string The calculated HMAC hash.
	 */
	public static function Hash($nonce_base, $tick_offset = 0, $factor = 43200){
		global $config;

		$nonce_tick = ceil(time() / $factor) - $tick_offset;

		$key = $config['gpuniq'];

		$data = $nonce_base . $nonce_tick;

		return hash_hmac(self::NONCE_ALGO, $data, $key);
	}
}