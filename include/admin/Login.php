<?php

namespace gp\admin;

defined('is_running') or die('Not an entry point...');

class Login extends \gp\Page{

	public $pagetype = 'admin_display';

	public function __construct($title){
		global $config, $languages;

		\gp\tool::LoadComponents('gp-admin-css');

		$this->requested		= str_replace(' ','_',$title);
		$this->title			= $title;
		$this->lang				= $config['language'];
		$this->language			= $languages[$this->lang];
		$this->get_theme_css	= false;
		$_REQUEST['gpreq']		= 'admin';

		$this->head .= "\n".'<meta name="robots" content="noindex,nofollow" />';
		header( 'X-Frame-Options: SAMEORIGIN' );
	}

	public function RunScript(){}

	public function GetGpxContent(){		
		
		// https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js
		$this->head_js[] = '/include/thirdparty/js/crypto-js.min.js'; 		
				
		$this->head_js[] = '/include/js/login.js';

		$this->css_admin[] = '/include/css/login.css';
		$_POST += array('username'=>'');

		$this->admin_js = true;
		\gp\tool\Session::HasCookies();


		$this->BrowserWarning();
		$this->JavascriptWarning();

		echo '<div class="req_script nodisplay" id="login_container">';
		echo '<table><tr><td>';

		$cmd = \gp\tool::GetCommand();
		switch($cmd){
			case 'send_password';
				if( $this->SendPassword() ){
					$this->LoginForm();
				}else{
					$this->FogottenPassword();
				}
			break;

			case 'forgotten':
				$this->FogottenPassword();
			break;
			default:
				$this->LoginForm();
			break;
		}

		echo '</td></tr></table>';
		echo '</div>';
	}


	public function FogottenPassword(){
		global $langmessage;

		$_POST += array('username'=>'');
		$this->css_admin[] = '/include/css/login.css';


		echo '<div id="loginform">';
		echo '<form class="loginform" action="'.\gp\tool::GetUrl('Admin').'" method="post">';

		echo '<p class="login_text">';
		echo '<input type="text" name="username" value="'.htmlspecialchars($_POST['username']).'" placeholder="'.htmlspecialchars($langmessage['username']).'"/>';
		echo '</p>';

		echo '<input type="hidden" name="cmd" value="send_password" />';
		echo '<input type="submit" name="aa" value="'.$langmessage['send_password'].'" class="login_submit" />';
		echo ' &nbsp; <label>'. \gp\tool::Link('Admin',$langmessage['back']).'</label>';

		echo '</form>';
		echo '</div>';

	}

	public function LoginForm(){
		global $langmessage;


		$_REQUEST += array('file'=>'');


		echo '<div id="loginform">';
			echo '<div id="login_timeout" class="nodisplay">Log in Timeout: '.\gp\tool::Link('Admin','Reload to continue...').'</div>';

			echo '<form action="'.\gp\tool::GetUrl('Admin').'" method="post" id="login_form">';
			echo '<input type="hidden" name="file" value="'.htmlspecialchars($_REQUEST['file']).'">';	//for redirection

			echo '<div>';
			echo '<input type="hidden" name="cmd" value="login" />';
			echo '<input type="hidden" name="verified" value="'.htmlspecialchars(\gp\tool\Nonce::Create('post',true)).'" />';
			echo '<input type="hidden" name="login_nonce" value="'.htmlspecialchars(\gp\tool\Nonce::Create('login_nonce',true,300)).'" />';
			echo '</div>';

			echo '<p class="login_text">';
			echo '<input type="text" name="username" value="'.htmlspecialchars($_POST['username']).'" placeholder="'.htmlspecialchars($langmessage['username']).'" />';
			echo '<input type="hidden" name="user_sha" value="" />';
			echo '</p>';

			echo '<p class="login_text">';
			echo '<input type="password" class="password" name="password" value="" placeholder="'.htmlspecialchars($langmessage['password']).'"/>';
			echo '<input type="hidden" name="pass_md5" value="" />';
			echo '<input type="hidden" name="pass_sha" value="" />';
			echo '<input type="hidden" name="pass_sha512" value="" />';
			echo '</p>';

			echo '<p>';
			echo '<input type="submit" class="login_submit" value="'.$langmessage['login'].'" />';
			echo ' &nbsp; ';
			echo \gp\tool::Link('',$langmessage['cancel']);
			echo '</p>';

			echo '<p>';
			echo '<label>';
			echo '<input type="checkbox" name="remember" '.$this->checked('remember').'/> ';
			echo '<span>'.$langmessage['remember_me'].'</span>';
			echo '</label> ';

			echo '<label>';
			echo '<input type="checkbox" name="encrypted" '.$this->checked('encrypted').'/> ';
			echo '<span>'.$langmessage['send_encrypted'].'</span>';
			echo '</label>';
			echo '</p>';

			echo '<div>';
			echo '<label>';
			$url = \gp\tool::GetUrl('Admin','cmd=forgotten');
			echo sprintf($langmessage['forgotten_password'],$url);
			echo '</label>';
			echo '</div>';


			echo '</form>';
		echo '</div>';
	}

	public function BrowserWarning(){
		global $langmessage;

		echo	'<div id="browser_warning" class="nodisplay">';
		echo 		'<h2>'.$langmessage['Browser Warning'].'</h2>';
		echo 		'<p>'.$langmessage['Browser !Supported'].'</p>';
		echo		'<a href="https://www.mozilla.com/"><i class="fa fa-firefox"></i> Mozilla Firefox</a>';
		echo		'<a href="https://www.google.com/chrome"><i class="fa fa-chrome"></i> Google Chrome</a>';
		echo		'<a href="https://www.opera.com/"><i class="fa fa-opera"></i> Opera</a>';
		echo		'<a href="https://www.apple.com/safari"><i class="fa fa-safari"></i> Apple Safari</a>';
		echo		'<a href="https://www.microsoft.com/edge/"><i class="fa fa-edge"></i> Microsoft Edge</a>';
		echo	'</div>';
	}

	public function JavascriptWarning(){
		global $langmessage;

		echo	'<div class="without_script" id="javascript_warning" style="opacity:0">';
		echo		'<h2>'.$langmessage['JAVASCRIPT_REQ'].'</h2>';
		echo		'<p>';
		echo			$langmessage['INCOMPAT_BROWSER'];
		echo			' ';
		echo			$langmessage['MODERN_BROWSER'];
		echo		'</p>';
		echo	'</div>';
	}


	public function Checked($name){

		if( strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST' )
			return ' checked="checked" ';

		if( !isset($_POST[$name]) )
			return '';

		return ' checked="checked" ';
	}


	public function SendPassword(){
		global $langmessage, $config;

		$users		= \gp\tool\Files::Get('_site/users');
		$username	= $_POST['username'];

		if( !isset($users[$username]) ){
			msg($langmessage['OOPS']);
			return false;
		}

		$userinfo = $users[$username];



		if( empty($userinfo['email']) ){
			msg($langmessage['no_email_provided']);
			return false;
		}

		$passwordChars		= str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 3);
		$newpass			= str_shuffle($passwordChars);
		$newpass			= substr($newpass, 0, 8);
		$pass_hash			= \gp\tool\Session::PassAlgo($userinfo);
		$former_pass_hash	= false;

		if( $pass_hash == 'password_hash' && !function_exists('password_hash') ){
			// this unlikely but possible case may only occur if a former PHP 5.5+ site was moved to a PHP < 5.5 host
			// the password algorithm will then be changed to sha512. the old password will not be usable anymore
			$former_pass_hash				= $pass_hash;
			$pass_hash 						= 'sha512';
			$users[$username]['passhash']	= $pass_hash;
		}

		$users[$username]['newpass'] = \gp\tool::hash($newpass, $pass_hash);
		if( !\gp\tool\Files::SaveData('_site/users', 'users', $users) ){
			msg($langmessage['OOPS'] . ' (User data not saved. Check file permissions)');
			return false;
		}

		$server		= \gp\tool::ServerName();
		$link		= \gp\tool::AbsoluteLink('Admin', $langmessage['login']);
		$message	= sprintf($langmessage['passwordremindertext'], $server, $link, $username, $newpass);


		// send email
		$mailer = new \gp\tool\Emailer();

		if( $mailer->SendEmail($userinfo['email'], $langmessage['new_password'], $message) ){
			list($namepart, $sitepart) = explode('@', $userinfo['email']);
			$showemail = substr($namepart, 0, 3) . '...@' . $sitepart;
			msg(sprintf($langmessage['password_sent'], $username, $showemail));
			return true;
		}

		// sending the new password failed

		msg($langmessage['OOPS'].' (Email not sent)');

		if( $former_pass_hash ){
			// although this will only help in the *very special* case, where the
			// PHP version < 5.5 was changed to 5.5+ AFTER the (now failed) new password request
			// we will restore the former password hash algorithm, so the old password (if recalled) will work again
			$users[$username]['passhash'] = $former_pass_hash;
			\gp\tool\Files::SaveData('_site/users', 'users', $users);
		}

		return false;
	}


}
