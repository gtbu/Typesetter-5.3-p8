<?php

namespace gp\tool;

defined('is_running') or die('Not an entry point...');


gp_defined('gp_lock_time',900); // = 15 minutes


class Session{

	private static $singleton;
	private $logged_in			= false;
	private $session_id;
	private $session_file;


	public static function Init(){
		return self::getInstance();
	}


	/**
	 * Get singleton instance
	 * @return \gp\tool\Session
	 */
	public static function getInstance(){
		if( !isset(self::$singleton) ){
			self::$singleton = new static();
		}
		return self::$singleton;
	}


	public function __construct(){

		self::SetConstants();

		if( isset($_COOKIE[gp_session_cookie]) ){
			self::CheckPosts();
			$this->Start($_COOKIE[gp_session_cookie]);
		}

		$cmd = \gp\tool::GetCommand();

		switch( $cmd ){
			case 'logout':
				$this->LogOut();
				return;

			case 'login':
				$this->LogIn();
				return;
		}

		if( $this->logged_in ){
			$this->Prepare();
		}

		if( $cmd === 'ClearErrors' ){
			self::ClearErrors();
		}

	}


	public static function SetConstants(){
		global $config;
		gp_defined('gp_session_cookie',self::SessionCookie($config['gpuniq']));
		gp_defined('gp_installation_cookie',self::SessionCookie('g-'.$config['gpuniq']));
	}



	public function LogIn(){
		global $langmessage;


		if( $this->logged_in ){
			return;
		}

		// check nonce
		// expire the nonce after 10 minutes
		$nonce = $_POST['login_nonce'];
		if( !\gp\tool\Nonce::Verify('login_nonce', $nonce, true, 300) ){
			msg($langmessage['OOPS'] . ' (Expired Nonce)');
			return;
		}

		if( !self::HasCookies() ){
			msg($langmessage['COOKIES_REQUIRED']);
			return false;
		}

		//delete the entry in $sessions if we're going to create another one with login
		if( isset($_COOKIE[gp_session_cookie]) ){
			self::CleanSession($_COOKIE[gp_session_cookie]);
		}

		$users		= \gp\tool\Files::Get('_site/users');
		$username	= self::GetLoginUser($users, $nonce);

		if( $username === false ){
			self::IncorrectLogin('1');
			return false;
		}
		$users[$username] += array(
			'attempts'	=> 0,
			'granted'	=> '', // 'editing' will be set EditingValue()
		);
		$userinfo = $users[$username];

		//Check Attempts
		if( $userinfo['attempts'] >= 5 ){
			$timeDiff = (time() - $userinfo['lastattempt']) / 60; //minutes
			if( $timeDiff < 10 ){
				msg($langmessage['LOGIN_BLOCK'], ceil(10 - $timeDiff));
				return false;
			}
		}

		//check against password sent to a user's email address from the forgot_password form
		$passed = self::PasswordPassed($userinfo, $nonce);

		//if passwords don't match
		if( $passed !== true ){
			self::IncorrectLogin('2');
			self::UpdateAttempts($users, $username);
			return false;
		}

		//will be saved in UpdateAttempts
		if( isset($userinfo['newpass']) ){
			unset($userinfo['newpass']);
		}

		$session_id = self::create($userinfo, $username, $sessions);
		if( !$session_id ){
			msg($langmessage['OOPS'] . ' (Data Not Saved)');
			self::UpdateAttempts($users, $username, true);
			return false;
		}


		if( $this->Start($session_id, $sessions) ){
			msg($langmessage['logged_in']);
		}


		//need to save the user info regardless of success or not
		//also saves file_name in users.php
		$users[$username] = $userinfo;
		self::UpdateAttempts($users, $username, true);

		//redirect to prevent resubmission
		$this->Redirect();
	}



	/**
	 * Return true if we can confirm there are cookies
	 * Return false and attempt to set a cookie otherwise
	 *
	 */
	public static function HasCookies(){
		global $config;

		self::SetConstants();

		if( array_key_exists(gp_session_cookie, $_COOKIE) ||
			array_key_exists(gp_installation_cookie, $_COOKIE)
			){
			return true;
		}

		\gp\tool\Session::cookie(gp_installation_cookie,'2');
		return false;
	}



	/**
	 * Redirect user after login
	 *
	 */
	public function Redirect(){
		global $gp_index;

		if( !$this->logged_in ){
			return;
		}

		$redirect = false;

		if( isset($_REQUEST['file']) ){
			$redirect = \gp\tool::ArrayKey($_REQUEST['file'], $gp_index );
		}

		if( $redirect === false ){
			$redirect = 'Admin';
		}

		$url = \gp\tool::GetUrl($redirect, '', false);
		\gp\tool::Redirect($url);
	}



	/**
	 * Return the username for the login request
	 *
	 */
	public static function GetLoginUser( $users, $nonce ){

		$_POST += array(
			'user_sha'		=> '',
			'username'		=> '',
			'login_nonce'	=> '',
		);

		if( gp_require_encrypt && empty($_POST['user_sha']) ){
			return false;
		}

		foreach($users as $username => $info){
			$sha_user = sha1($nonce.$username);

			if( !gp_require_encrypt
				&& !empty($_POST['username'])
				&& $_POST['username'] == $username
				){
					return $username;
			}

			if( $sha_user === $_POST['user_sha'] ){
				return $username;
			}
		}

		return false;
	}



	/**
	 * Check the posted password
	 * Check against reset password if set
	 *
	 */
	public static function PasswordPassed(&$userinfo, $nonce){

		if( gp_require_encrypt && !empty($_POST['password']) ){
			return false;
		}

		//if not encrypted with js
		if( !empty($_POST['password']) ){
			$_POST['pass_md5']		= sha1($nonce . md5($_POST['password']));
			$_POST['pass_sha']		= sha1($nonce . sha1($_POST['password']));
			$_POST['pass_sha512']	= \gp\tool::hash($_POST['password'], 'sha512', 50);
		}

		$pass_algo = self::PassAlgo($userinfo);

		if( !empty($userinfo['newpass']) && self::CheckPassword($userinfo['newpass'], $nonce, $pass_algo) ){
			$userinfo['password'] = $userinfo['newpass'];
			return true;
		}

		//check password
		if( self::CheckPassword($userinfo['password'], $nonce, $pass_algo) ){
			return true;
		}

		return false;
	}



	/**
	 * Return the algorithm used by the user for passwords
	 *
	 */
	public static function PassAlgo($userinfo){
		global $config;

		if( isset($userinfo['passhash']) ){
			return $userinfo['passhash'];
		}
		return $config['passhash'];
	}



	/**
	 * Check password, choose between plaintext, md5 encrypted or sha-1 encrypted
	 * @param string $user_pass
	 * @param string $nonce
	 * @param string $pass_algo Password hashing algorithm
	 *
	 */
	public static function CheckPassword($user_pass, $nonce, $pass_algo){
		global $config;

		$posted_pass = false;
		switch($pass_algo){

			case 'md5':
				$posted_pass = $_POST['pass_md5'];
				$user_pass = sha1($nonce . $user_pass);
			break;

			case 'sha1':
				$posted_pass = $_POST['pass_sha'];
				$user_pass = sha1($nonce . $user_pass);
			break;

			case 'sha512':
				//javascript only loops through sha512 50 times
				$posted_pass = \gp\tool::hash($_POST['pass_sha512'], 'sha512', 950);
			break;

			case 'password_hash':
				if( !function_exists('password_verify') ){
					msg('This version of PHP does not have password_verify(). '
					. 'To fix, reset your password at /Admin_Preferences and '
					. 'select "sha512" for the "Password Algorithm"');
					return false;
				}
				return password_verify($_POST['pass_sha512'], $user_pass);

		}

		if( $posted_pass && $posted_pass === $user_pass ){
			return true;
		}

		return false;
	}



	public static function IncorrectLogin($i){
		global $langmessage;
		msg($langmessage['incorrect_login'] . ' (' . $i . ')');
		$url = \gp\tool::GetUrl('Admin', 'cmd=forgotten');
		msg($langmessage['forgotten_password'], $url);
	}



	/**
	 * Set the value of $userinfo['file_name']
	 *
	 */
	public static function SetSessionFileName($userinfo,$username){
		global $dataDir;

		if( isset($userinfo['file_name']) ){
			return $userinfo;
		}

		do{
			$new_file_name	= 'gpsess_' . \gp\tool::RandomString(40) . '.php';
			$new_file		= $dataDir . '/data/_sessions/' . $new_file_name;
		}while( \gp\tool\Files::Exists($new_file) );

		$userinfo['file_name']	= $new_file_name;

		return $userinfo;
	}



	public function LogOut(){
		global $langmessage;

		if( empty($_GET['verified']) ){
			return;
		}

		if( !\gp\tool\Nonce::Verify('post', $_GET['verified'], true) ){
			return;
		}

		if( !$this->logged_in ){
			return false;
		}

		if( !isset($_COOKIE[gp_session_cookie]) ){
			return false;
		}


		$session_id = $_COOKIE[gp_session_cookie];

		self::Unlock($session_id);
		self::cookie(gp_session_cookie);
		self::CleanSession($session_id);
		$this->logged_in = false;

		msg($langmessage['LOGGED_OUT']);
	}



	/**
	 * Remove the admin session lock
	 *
	 */
	public static function Unlock($session_id){
		return \gp\tool\Files::Unlock('admin', sha1(sha1($session_id)));
	}



	public static function CleanSession($session_id){
		//remove the session_id from session_ids.php
		$sessions = self::GetSessionIds();
		unset($sessions[$session_id]);
		self::SaveSessionIds($sessions);
	}



	/**
	 * Set a session cookie
	 * Attempt to use httponly if available
	 *
	 */
	public static function Cookie($name, $value='', $expires = false){
		global $dirPrefix;

		$cookiePath		= empty($dirPrefix) ? '/' : $dirPrefix;
		$cookiePath		= \gp\tool::HrefEncode($cookiePath, false);
		$secure			= (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on');
		$domain			= \gp\tool::ServerName(true);

		if( !$domain || strpos($domain, '.') === false ){
			$domain = '';
		}

		if( strpos($domain, ':') !== false ){
			$domain = substr($domain, 0, strrpos($domain, ':'));
		}

		// expire if value is empty
		// cookies are set with either www removed from the domain or with an empty string
		if( empty($value) ){
			$expires = time()-2592000;
			setcookie($name, $value, $expires, $cookiePath, $domain, $secure, true);
			setcookie($name, $value, $expires, $cookiePath, $domain, false, true);
			return;
		}


		// get expiration and set
		if( $expires === false ){
			$expires = time()+2592000; //30 days
		}elseif( $expires === true ){
			$expires = 0; //expire at end of session
		}

		setcookie($name, $value, $expires, $cookiePath, $domain, $secure, true);
	}



	/**
	 * Update the number of login attempts and the time of the last attempt for a $username
	 *
	 */
	public static function UpdateAttempts($users,$username,$reset = false){
		if( $reset ){
			$users[$username]['attempts'] = 0;
		}else{
			$users[$username]['attempts']++;
		}
		$users[$username]['lastattempt'] = time();
		\gp\tool\Files::SaveData('_site/users', 'users', $users);
	}



	/**
	 * called when a user logs in
	 *
	 */
	public static function create(&$user_info, $username, &$sessions){
		global $dataDir, $langmessage;

		//update the session files to .php files
		//changes to $userinfo will be saved by UpdateAttempts() below
		$user_info			= self::SetSessionFileName($user_info, $username);
		$user_file_name		= $user_info['file_name'];
		$user_file			= $dataDir . '/data/_sessions/' . $user_file_name;


		//use an existing session_id if the new login matches an existing session (uid and file_name)
		$sessions			= self::GetSessionIds();
		$uid				= self::auth_browseruid();
		$session_id			= false;
		foreach($sessions as $sess_temp_id => $sess_temp_info){
			if( isset($sess_temp_info['uid']) &&
				$sess_temp_info['uid'] == $uid &&
				$sess_temp_info['file_name'] == $user_file_name
				){
				$session_id = $sess_temp_id;
			}
		}

		//create a unique session id if needed
		if( $session_id === false ){
			do{
				$session_id = \gp\tool::RandomString(40);
			}while( isset($sessions[$session_id]) );
		}

		$expires = !isset($_POST['remember']);
		self::cookie(gp_session_cookie, $session_id, $expires);

		//save session id
		$sessions[$session_id]					= array();
		$sessions[$session_id]['file_name']		= $user_file_name;
		$sessions[$session_id]['uid']			= $uid;
		//$sessions[$session_id]['time'] = time(); //for session locking
		if( !self::SaveSessionIds($sessions) ){
			return false;
		}

		//make sure the user's file exists
		$new_data					= self::SessionData($user_file,$checksum);
		$new_data['username']		= $username;
		$new_data['granted']		= $user_info['granted'];

		if( isset($user_info['editing']) ){
			$new_data['editing'] = $user_info['editing'];
		}
		\gp\admin\Tools::EditingValue($new_data);

		// may needt to extend the cookie life later
		if( isset($_POST['remember']) ){
			$new_data['remember'] = time();
		}else{
			unset($new_data['remember']);
		}

		\gp\tool\Files::SaveData($user_file, 'gpAdmin', $new_data);
		return $session_id;
	}



	/**
	 * Return the contents of the session_ids.php data file
	 * @return array array of all sessions
	 */
	public static function GetSessionIds(){
		return \gp\tool\Files::Get('_site/session_ids', 'sessions');
	}



	/**
	 * Save $sessions to the session_ids.php data file
	 * @param $sessions array array of all sessions
	 * @return bool
	 */
	public static function SaveSessionIds($sessions){

		while( $current = current($sessions) ){
			$key = key($sessions);

			//delete if older than
			if( isset($current['time']) && $current['time'] > 0 && ($current['time'] < (time() - 1209600)) ){
			//if( $current['time'] < time() - 2592000 ){ //one month
				unset($sessions[$key]);
				$continue = true;
			}else{
				next($sessions);
			}
		}

		//clean
		return \gp\tool\Files::SaveData('_site/session_ids', 'sessions', $sessions);
	}



	/**
	 * Determine if $session_id represents a valid session and if so start the session
	 *
	 */
	public function Start($session_id, $sessions = false ){
		global $langmessage, $dataDir;

		//get the session file
		if( !$sessions ){
			$sessions = self::GetSessionIds();
			if( !isset($sessions[$session_id]) ){
				self::cookie(gp_session_cookie); //make sure the cookie is deleted
				msg($langmessage['Session Expired'] . ' (timeout)');
				return false;
			}
		}

		$sess_info = $sessions[$session_id];

		//check ~ip, ~user agent ...
		if( gp_browser_auth && !empty($sess_info['uid']) ){

			$auth_uid			= self::auth_browseruid();

			if( $sess_info['uid'] != $auth_uid ){
				self::cookie(gp_session_cookie); //make sure the cookie is deleted
				msg($langmessage['Session Expired'] . ' (browser auth)');
				return false;
			}
		}

		$session_file = $dataDir . '/data/_sessions/' . $sess_info['file_name'];
		if( ($session_file === false) || !\gp\tool\Files::Exists($session_file) ){
			self::cookie(gp_session_cookie); //make sure the cookie is deleted
			msg($langmessage['Session Expired'] . ' (invalid)');
			return false;
		}

		$this->session_id		= $session_id;
		$this->session_file		= $session_file;
		$this->logged_in		= true;

		return true;
	}


	public function Prepare(){
		global $langmessage, $wbMessageBuffer;
		static $locked_message = false;

		//prevent browser caching when editing
		Header( 'Last-Modified: ' . gmdate( 'D, j M Y H:i:s' ) . ' GMT' );
		Header( 'Expires: ' . gmdate( 'D, j M Y H:i:s', time() ) . ' GMT' );
		Header( 'Cache-Control: no-store, no-cache, must-revalidate'); // HTTP/1.1
		Header( 'Cache-Control: post-check=0, pre-check=0', false );
		Header( 'Pragma: no-cache' ); // HTTP/1.0

		$GLOBALS['gpAdmin'] = self::SessionData($this->session_file,$checksum);

		//lock to prevent conflicting edits
		if( gp_lock_time > 0 &&
			( !empty($GLOBALS['gpAdmin']['editing']) || !empty($GLOBALS['gpAdmin']['granted']) )
			){
			$expires = gp_lock_time;
			if( !\gp\tool\Files::Lock('admin', sha1(sha1($this->session_id)), $expires) ){
				msg($langmessage['site_locked'] . ' ' . sprintf($langmessage['lock_expires_in'], ceil($expires / 60)));
				$locked_message = true;
				$GLOBALS['gpAdmin']['locked'] = true;
			}else{
				unset($GLOBALS['gpAdmin']['locked']);
			}
		}

		//extend cookie?
		if( isset($GLOBALS['gpAdmin']['remember']) ){
			$elapsed = time() - $GLOBALS['gpAdmin']['remember'];
			if( $elapsed > 604800 ){ //7 days
				$GLOBALS['gpAdmin']['remember'] = time();
				self::cookie(gp_session_cookie, $this->session_id);
			}
		}

		register_shutdown_function(array('\\gp\\tool\\Session', 'close'), $this->session_file, $checksum);

		//make sure forms have admin nonce
		ob_start(array('\\gp\\tool\\Session', 'AdminBuffer'));

		\gp\tool\Output::$lang_values += array(
			'cancel'					=>	'ca',
			'update'					=>	'up',
			'caption'					=>	'cp',
			'Width'						=>	'Width',
			'Height'					=>	'Height',
			'save'						=>	'Save',
			'Saved'						=>	'Saved',
			'Saving'					=>	'Saving',
			'Close'						=>	'Close',
			'Page'						=>	'Page',
			'theme_content'				=>	'Extra',
			'Publish Draft'				=>	'PublishDraft',
			'Publish'					=>	'Publish',
			'Dismiss'					=>	'Dismiss',
			'Dismiss Draft'				=>	'DismissDraft',
			'Select Image'				=>	'SelectImage',
			'edit'						=>	'edit',
			'Expand Editor'				=>	'ExpandEditor',
			'Shrink Editor'				=>	'ShrinkEditor',
			'Hide Admin UI'				=>	'HideAdminUI',
			'Show Admin UI'				=>	'ShowAdminUI',
			'options'					=>	'options',
			'Copy'						=>	'Copy',
			'Copy to Clipboard'			=>	'CopyToClipboard',
			'Manage Sections'			=>	'ManageSections',
			'Sections'					=>	'Sections',
			'Section Attributes'		=>	'SectionAttributes',
			'Available Classes'			=>	'AvailableClasses',
			'category'                  =>  'Category',
			'Attribute'					=>	'Attribute',
			'Add Attribute'				=>	'AddAttribute',
			'Value'						=>	'Value',
			'Visibility'				=>	'Visibility',
			'remove'					=>	'remove',
			'delete'					=>	'del',
			'Move Behind'				=>	'MoveBehind',
			'Section %s'				=>	'Section',
			'generic_delete_confirm'	=>	'generic_delete_confirm',
			'Ctrl Key'					=>	'ctrlKey',
			'Shift Key'					=>	'shiftKey',
			'Alt Key'					=>	'altKey',
		);


		\gp\tool::LoadComponents('sortable, autocomplete, gp-admin, gp-admin-css, fontawesome, popper');
		\gp\admin\Tools::VersionsAndCheckTime();


		\gp\tool\Output::$inline_vars += array(
			'gpRem' => \gp\admin\Tools::CanRemoteInstall(),
		);


		//prepend messages from message buffer
		if( isset($GLOBALS['gpAdmin']['message_buffer']) && count($GLOBALS['gpAdmin']['message_buffer']) ){
			$wbMessageBuffer = array_merge($GLOBALS['gpAdmin']['message_buffer'], $wbMessageBuffer);
			unset($GLOBALS['gpAdmin']['message_buffer']);
		}

		//alias
		 if( isset($_COOKIE['gp_alias']) ){
       $GLOBALS['gpAdmin']['useralias'] = $_COOKIE['gp_alias'];
    } else { 
      if (isset($GLOBALS['gpAdmin']['username'])) { 
	  $GLOBALS['gpAdmin']['useralias'] = $GLOBALS['gpAdmin']['username'];
       } else { $GLOBALS['gpAdmin']['useralias'] = '';  }
    }

	}



	/**
	 * Perform admin only changes to the content buffer
	 * This will happen before \gp\tool\Output::BufferOut()
	 *
	 */
	public static function AdminBuffer($buffer){
		global $wbErrorBuffer, $gp_admin_html;

		//add $gp_admin_html to the document
		if( strpos($buffer, '<!-- get_head_placeholder ' . gp_random . ' -->') !== false ){
			$buffer = \gp\tool\Output::AddToBody(
				$buffer,
				'<div id="gp_admin_html">'
					. $gp_admin_html . \gp\tool\Output::$editlinks
					. '</div><div id="gp_admin_fixed"></div>'
			);
		}

		// Add a generic admin nonce field to each post form
		// Admin nonces are also added with javascript if needed
		$count = preg_match_all('#<form[^<>]*method=[\'"]post[\'"][^<>]*>#i', $buffer, $matches);
		if( $count ){
			$nonce = \gp\tool\Nonce::Create('post',true);
			$matches[0] = array_unique($matches[0]);
			foreach($matches[0] as $match){

				//make sure it's a local action
				if( preg_match('#action=[\'"]([^\'"]+)[\'"]#i', $match, $sub_matches) ){
					$action = $sub_matches[1];
					if( substr($action, 0, 2) === '//' ){
						continue;
					}elseif( strpos($action, '://') ){
						continue;
					}
				}
				$replacement = '<input type="hidden" name="verified" value="' . $nonce . '"/>';
				$pos	= strpos($buffer,$match)+strlen($match);
				$buffer	= substr_replace($buffer, $replacement, $pos, 0);
			}
		}

		return $buffer;
	}



	/**
	 * Get the session data from a user session file
	 * @param string $session_file The full path to the user's session file
	 * @param string $checksum
	 * @return array The user's session data
	 */
	public static function SessionData($session_file, &$checksum){

		$gpAdmin	= \gp\tool\Files::Get($session_file, 'gpAdmin');

		$checksum	= '';

		if( isset($gpAdmin['checksum']) ){
			$checksum = $gpAdmin['checksum'];
		}

		return $gpAdmin + self::gpui_defaults();
	}



	public static function gpui_defaults(){
		return array(
			'gpui_cmpct'	=> 0,
			'gpui_tx'		=> 10,
			'gpui_ty'		=> 39,
			'gpui_ckx'		=> 20,
			'gpui_cky'		=> 240,
			'gpui_exp'		=> 1,
			'gpui_vis'		=> 'cur',
			'gpui_thw'		=> 250,
		);
	}



	/**
	 * Prevent XSS attacks for logged in users by
	 * making sure the request contains a valid nonce
	 *
	 */
	public static function CheckPosts(){

		if( count($_POST) == 0 ){
			return;
		}

		if( empty($_POST['verified']) ){
			self::StripPost('XSS Verification Parameter Error');
			return;
		}

		if( !\gp\tool\Nonce::Verify('post', $_POST['verified'], true) ){
			self::StripPost('XSS Verification Parameter Mismatch');
			return;
		}
	}



	/**
	 * Unset all $_POST values
	 *
	 */
	public static function StripPost($message){
		global $langmessage, $post_quarantine;
		msg($langmessage['OOPS'] . ' (' . $message . ')');
		$post_quarantine = $_POST;
		foreach($_POST as $key => $value){
			unset($_POST[$key]);
		}
	}



	/**
	 * Save any changes to the $gpAdmin array
	 * @param string $file Session file path
	 * @param string $checksum_read The original checksum of the $gpAdmin array
	 *
	 */
	public static function Close($file, $checksum_read){
		global $gpAdmin;

		self::FatalNotices();
		self::Cron();
		self::LayoutInfo();

		unset($gpAdmin['checksum']);
		$checksum = \gp\tool::ArrayHash($gpAdmin);

		//nothing changed
		if( $checksum === $checksum_read ){
			return;
		}
		if( !isset($gpAdmin['username']) ){
			trigger_error('username not set');
			die();
		}

		$gpAdmin['checksum'] = $checksum; //store the new checksum
		\gp\tool\Files::SaveData($file, 'gpAdmin', $gpAdmin);

	}


	/**
	 * Update layout information if needed
	 *
	 */
	public static function LayoutInfo(){
		global $page, $gpLayouts, $get_all_gadgets_called;

		if( !\gp\tool\Output::$template_included ){
			return;
		}

		$layout = $page->gpLayout;
		if( !isset($gpLayouts[$layout]) ){
			return;
		}

		$layout_info =& $gpLayouts[$layout];

		//template.php file not modified
		$template_file = realpath($page->theme_dir . '/template.php');
		$template_mod = filemtime($template_file);
		if( isset($layout_info['template_mod']) && $layout_info['template_mod'] >= $template_mod ){
			return;
		}

		$contents = ob_get_contents();

		//charset
		if( strpos($contents, 'charset=') === false ){
			return;
		}

		//get just the head of the buffer to see if we need to add charset
		$pos = strpos($contents, '</head');
		unset($layout_info['doctype']);
		if( $pos > 0 ){
			$head = substr($contents, 0, $pos);
			$layout_info['doctype'] = self::DoctypeMeta($head);
		}
		$layout_info['all_gadgets'] = $get_all_gadgets_called;

		//save
		$layout_info['template_mod'] = $template_mod;
		\gp\admin\Tools::SavePagesPHP();
	}



	/**
	 * Determine if CMS needs to add a <meta charset> tag
	 * Look at the beginning of the document to see what kind of doctype the current template is using
	 * See http://www.w3schools.com/tags/tag_doctype.asp for description of different doctypes
	 *
	 */
	public static function DoctypeMeta($doc_start){

		//charset already set
		if( stripos($doc_start,'charset=') !== false ){
			return '';
		}

		// html5
		// spec states this should be "the first element child of the head element"
		if( stripos($doc_start, '<!doctype html>') !== false ){
			return '<meta charset="UTF-8" />';
		}
		return '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
	}



	/**
	 * Perform regular tasks
	 * Once an hour only when admin is logged in
	 *
	 */
	public static function Cron(){
		$cron_info	= \gp\tool\Files::Get('_site/cron_info');
		$file_stats	= \gp\tool\Files::$last_stats;

		$file_stats += array('modified' => 0);
		if( (time() - $file_stats['modified']) < 3600 ){
			return;
		}

		self::CleanTemp();
		\gp\tool\Files::SaveData('_site/cron_info', 'cron_info', $cron_info);
	}




	/**
	 * Clean old files and folders from the temporary folder
	 * Delete after 36 hours (129600 seconds)
	 *
	 */
	public static function CleanTemp(){
		global $dataDir;
		$temp_folder = $dataDir . '/data/_temp';
		$files = \gp\tool\Files::ReadDir($temp_folder, false);
		foreach($files as $file){
			if( $file == 'index.html'){
				continue;
			}
			$full_path = $temp_folder . '/' . $file;
			$mtime = (int)filemtime($full_path);
			$diff = time() - $mtime;
			if( $diff < 129600 ){
				continue;
			}
			\gp\tool\Files::RmAll($full_path);
		}
	}



	/**
	 * Output the UI variables as a Javascript Object
	 *
	 */
	public static function GPUIVars(){
		global $gpAdmin, $page, $config;

		$defaults	= self::gpui_defaults();
		$js			= array();

		foreach($defaults as $key => $value){
			if( isset($gpAdmin[$key]) ){
				$value = $gpAdmin[$key];
			}
			$renamed_key		= substr($key, 5);
			$js[$renamed_key]	= $value;
		}

		//default layout (admin layout)
		if( $page->gpLayout && $page->gpLayout == $config['gpLayout'] ){
			$js['dlayout'] = true;
		}else{
			$js['dlayout'] = false;
		}

		echo 'var gpui=' . json_encode($js) . ';';
	}



	/**
	 * Code modified from dokuwiki
	 * /dokuwiki/inc/auth.php
	 *
	 * Builds a pseudo UID from browser and IP data
	 *
	 * This is neither unique nor unfakable - still it adds some
	 * security. Using the first part of the IP makes sure
	 * proxy farms like AOLs are stil okay.
	 *
	 * @author  Andreas Gohr <andi@splitbrain.org>
	 *
	 * @return  string  a MD5 sum of various browser headers
	 */
	public static function auth_browseruid(){

		$uid = '';
		if( isset($_SERVER['HTTP_USER_AGENT']) ){
			$uid .= $_SERVER['HTTP_USER_AGENT'];
		}
		if( isset($_SERVER['HTTP_ACCEPT_ENCODING']) ){
			$uid .= $_SERVER['HTTP_ACCEPT_ENCODING'];
		}

		if( isset($_SERVER['HTTP_ACCEPT_CHARSET']) ){
			$uid .= $_SERVER['HTTP_ACCEPT_CHARSET'];
		}

		$ip = self::clientIP();
		$uid .= substr($ip, 0, strpos($ip, '.'));

		//ie8 will report ACCEPT_LANGUAGE as en-us and en-US depending on the type of request (normal, ajax)
		$uid = strtolower($uid);

		return md5($uid);
	}



	/**
	 * Return the IP of the client
	 * Modified from ClientIP method in Dokuwiki
	 *
	 * Honours X-Forwarded-For and X-Real-IP Proxy Headers
	 *
	 * Tries to return a routable public address, prefering the ones suplied in the X headers
	 *
	 */
	public static function ClientIP(){
		$ips = [];

		$add_ip = function($ips, $ip_key){

			if( !isset($_SERVER[$ip_key]) ){
				return $ips;
			}

			// some IPv4/v6 regexps borrowed from Feyd
			// see: http://forums.devnetwork.net/viewtopic.php?f=38&t=53479
			$dec_octet = '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|[0-9])';
			$hex_digit = '[A-Fa-f0-9]';
			$h16 = "{$hex_digit}{1,4}";
			$IPv4Address = "$dec_octet\\.$dec_octet\\.$dec_octet\\.$dec_octet";
			$ls32 = "(?:$h16:$h16|$IPv4Address)";
			$IPv6Address =
				"(?:(?:{$IPv4Address})|(?:".
				"(?:$h16:){6}$ls32" .
				"|::(?:$h16:){5}$ls32" .
				"|(?:$h16)?::(?:$h16:){4}$ls32" .
				"|(?:(?:$h16:){0,1}$h16)?::(?:$h16:){3}$ls32" .
				"|(?:(?:$h16:){0,2}$h16)?::(?:$h16:){2}$ls32" .
				"|(?:(?:$h16:){0,3}$h16)?::(?:$h16:){1}$ls32" .
				"|(?:(?:$h16:){0,4}$h16)?::$ls32" .
				"|(?:(?:$h16:){0,5}$h16)?::$h16" .
				"|(?:(?:$h16:){0,6}$h16)?::" .
				")(?:\\/(?:12[0-8]|1[0-1][0-9]|[1-9][0-9]|[0-9]))?)";

			$new_ips = explode(',',str_replace(' ','',$_SERVER[$ip_key]));

			// remove any non-IP stuff
			foreach($new_ips as $ip){
				if( preg_match("/^$IPv4Address$/",$ip,$match) || preg_match("/^$IPv6Address$/",$ip,$match)) {
					$ips[] = $match[0];
				}
			}

			return $ips;
		};

		$ips = $add_ip($ips,'REMOTE_ADDR');
		$ips = $add_ip($ips,'HTTP_X_FORWARDED_FOR');
		$ips = $add_ip($ips,'HTTP_X_REAL_IP');
		$ips = array_values(array_unique($ips));

		// for some strange reason we don't have a IP
		if( empty($ips) ){
			return '0.0.0.0';
		}

		// decide which IP to use, trying to avoid local addresses
		$ips = array_reverse($ips);
		foreach($ips as $ip){
			if( !preg_match('/^(::1|[fF][eE]80:|127\.|10\.|192\.168\.|172\.((1[6-9])|(2[0-9])|(3[0-1]))\.)/',$ip) ){
				return $ip;
			}
		}

		// use the first (last) address
		return end($ips);
	}


	/**
	 * Re-enable components that were disabled because of fatal errors
	 *
	 */
	public static function ClearErrors(){
		\gp\admin\Tools\Errors::ClearAll();
		$title = \gp\tool::WhichPage();
		\gp\tool::Redirect(\gp\tool::GetUrl($title, '', false));
	}


	/**
	 * Notify the admin if there have been any fatal errors
	 *
	 */
	public static function FatalNotices(){
		global $dataDir, $page;

		if( !\gp\admin\Tools::HasPermission('Admin_Errors') ){
			return;
		}

		if( is_object($page)
			&& property_exists($page, 'requested')
			&& strpos($page->requested, 'Admin/Errors') !== false
			){
			return;
		}

		$dir		= $dataDir . '/data/_site';
		$files		= scandir($dir);
		$has_fatal	= false;

		foreach($files as $file){
			if( strpos($file, 'fatal_') === false ){
				continue;
			}
			$has_fatal = true;
		}

		if( !$has_fatal ){
			return;
		}

		$msg = 'Warning: One or more components have caused fatal errors. <br/>';
		$msg .= \gp\tool::Link(
			'Admin/Errors',
			'More Information',
			'',
			'style="white-space:nowrap"'
		);

		$msg .= ' &nbsp; ';

		$msg .= \gp\tool::Link(
			(isset($page) ? $page->requested : 'Admin'),
			'Clear All Errors',
			'cmd=ClearErrors',
			'', //cannot be creq
			'ClearErrors'
		);

		msg($msg);
	}



	public static function SessionCookie($uniq){
		return 'gpEasy_'.substr(sha1($uniq),12,12);
	}


}
