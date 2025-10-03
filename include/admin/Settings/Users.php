<?php

namespace gp\admin\Settings;

defined('is_running') or die('Not an entry point...');

class Users extends \gp\special\Base{

	public $users;
	public $possible_permissions	= array();
	public $has_weak_pass			= false;

	protected $cmds					= [
										'NewUserForm'		=> '',
										'ChangePass'		=> '',
										'Details'			=> 'ChangeDetails',
									];

	protected $cmds_post			= [
										'CreateNewUser'		=> 'NewUserForm',
										'RemoveUser'		=> 'DefaultDisplay',
										'ResetPass'			=> 'ChangePass',
										'SaveChanges'		=> 'ChangeDetails',
									];


	public function __construct($args){
		global $langmessage;

		parent::__construct($args);

		$this->page->head_js[]			= '/include/js/admin_users.js';
		$this->possible_permissions		= $this->PossiblePermissions();
		$this->GetUsers();

	}


	/**
	 * Return an array of possible permissions
	 *
	 */
	public static function PossiblePermissions(){
		$possible	= array();
		$scripts	= \gp\admin\Tools::AdminScripts();

		foreach($scripts as $script => $info){

			if( isset($info['permission']) ){
				continue;
			}

			if( !isset($info['label']) ){
				continue;
			}
			$script = str_replace('/','_',$script);
			$possible[$script] = $info['label'];
		}

		return $possible;
	}


	/**
	 * Save changes made to an existing user's permissions
	 *
	 */
	public function SaveChanges(){
		global $langmessage,$gpAdmin;

		$username =& $_REQUEST['username'];
		if( !isset($this->users[$username]) ){
			msg($langmessage['OOPS']);
			return false;
		}

		if( !empty($_POST['email']) ){
			$this->users[$username]['email'] = $_POST['email'];
		}

		$this->users[$username]['granted'] = $this->GetPostedPermissions($username);
		$this->users[$username]['editing'] = $this->GetEditingPermissions();

		//this needs to happen before SaveUserFile();
		//update the /_session file
		$userinfo =& $this->users[$username];
		$userinfo = \gp\tool\Session::SetSessionFileName($userinfo,$username); //make sure $userinfo['file_name'] is set


		if( !$this->SaveUserFile() ){
			return false;
		}

		// update the $user_file_name file
		$this->UserFileDetails($username);
		return true;
	}

	/**
	 * Update the users session file with new permission data
	 *
	 */
	public function UserFileDetails($username){
		global $dataDir, $gpAdmin;

		$user_info			= $this->users[$username];
		$user_file			= $dataDir.'/data/_sessions/'.$user_info['file_name'];

		if( $gpAdmin['username'] === $username ){
			$new_info =& $gpAdmin;
		}else{
			$new_info = \gp\tool\Files::Get($user_file,'gpAdmin');
		}

		if( !$new_info ){
			return;
		}

		$new_info['granted'] = $user_info['granted'];
		$new_info['editing'] = $user_info['editing'];
		\gp\tool\Files::SaveData($user_file,'gpAdmin',$new_info);
	}

	/**
	 * Display the permissions of a single user
	 *
	 */
	public function ChangeDetails(){
		global $langmessage;

		$username =& $_REQUEST['username'];
		if( !isset($this->users[$username]) ){
			msg($langmessage['OOPS']);
			return false;
		}

		echo '<h2>'.$langmessage['user_permissions'].'</h2>';

		$userinfo = $this->users[$username];

		echo '<form action="'.\gp\tool::GetUrl('Admin/Users').'" method="post" id="permission_form">';
		echo '<input type="hidden" name="cmd" value="SaveChanges" />';
		echo '<input type="hidden" name="username" value="'.htmlspecialchars($username).'" />';

		echo '<table class="bordered">';
		echo '<tr>';
			echo '<th colspan="2">';
			echo $langmessage['details'];
			echo ' - ';
			echo $username;
			echo '</th>';
			echo '</tr>';

		$this->DetailsForm($userinfo);

		echo '<tr><td>';
			echo '</td><td>';
			echo ' <input type="submit" name="aaa" value="'.$langmessage['save'].'" class="gpsubmit"/>';
			echo ' <input type="reset" class="gpsubmit" />';
			echo ' <input type="submit" name="cmd" value="'.$langmessage['cancel'].'" class="gpcancel"/>';
			echo '</td>';
			echo '</tr>';

		echo '</table>';
		echo '</form>';

	}

	/**
	 * Remove a user from the installation
	 *
	 */
	public function RemoveUser(){
		global $langmessage;
		$username = $this->CheckUser();

		if( $username == false ){
			return;
		}

		unset($this->users[$username]);
		return $this->SaveUserFile();
	}

	/**
	 * Make sure the submitted username exists
	 *
	 */
	public function CheckUser(){
		global $langmessage,$gpAdmin;
		$username = $_POST['username'];

		if( !isset($this->users[$username]) ){
			msg($langmessage['OOPS']);
			return false;
		}

		//don't allow deleting self
		if( $username == $gpAdmin['username'] ){
			msg($langmessage['OOPS']);
			return false;
		}
		return $username;
	}



	public function CreateNewUser(){
		global $langmessage;
		$_POST += array('grant'=>'');

		if( ($_POST['password']=="") || ($_POST['password'] !== $_POST['password1'])  ){
			msg($langmessage['invalid_password']);
			return false;
		}


		$newname = $_POST['username'];
		$test = str_replace( array('.','_'), array(''), $newname );
		if( empty($test) || !ctype_alnum($test) ){
			msg($langmessage['invalid_username']);
			return false;
		}

		if( isset($this->users[$newname]) ){
			msg($langmessage['OOPS']);
			return false;
		}


		if( !empty($_POST['email']) ){
			$this->users[$newname]['email'] = $_POST['email'];
		}

		$this->users[$newname]['granted']	= $this->GetPostedPermissions($newname);
		$this->users[$newname]['editing']	= $this->GetEditingPermissions();

		$this->SetUserPass( $newname, $_POST['password']);

		if( $this->SaveUserFile() ){
			$url = \gp\tool::GetUrl('Admin/Users','',false);
			\gp\tool::Redirect($url);
		}

	}


	/**
	 * Set the user password and password hash algorithm
	 *
	 */
	public function SetUserPass( $username, $password ){

		$user_info =& $this->users[$username];

		if( function_exists('password_hash') && $_REQUEST['algo'] == 'password_hash' ){
			$temp					= \gp\tool::hash($password,'sha512',50);
			$user_info['password']	= password_hash($temp,PASSWORD_DEFAULT);
			$user_info['passhash']	= 'password_hash';

		}else{
			$user_info['password']	= \gp\tool::hash($password,'sha512');
			$user_info['passhash']	= 'sha512';
		}

	}


	/**
	 * Return the posted admin permissions
	 *
	 */
	public function GetPostedPermissions($username){
		global $gpAdmin;

		if( isset($_POST['grant_all']) && ($_POST['grant_all'] == 'all') ){
			return 'all';
		}

		$_POST += array('grant'=>array());
		$array = $_POST['grant'];

		//cannot remove self from Admin/Users
		if( $username == $gpAdmin['username'] ){
			$array = array_merge($array, array('Admin/Users'));
		}

		if( !is_array($array) ){
			return '';
		}

		$keys = array_keys($this->possible_permissions);
		$array = array_intersect($keys,$array);
		return implode(',',$array);
	}


	/**
	 * Return the posted file editing permissions
	 *
	 */
	public function GetEditingPermissions(){
		global $gp_titles;

		if( isset($_POST['editing_all']) && ($_POST['editing_all'] == 'all') ){
			return 'all';
		}

		$_POST += array('titles'=>array());
		$array = $_POST['titles'];
		if( !is_array($array) ){
			return '';
		}

		$keys = array_keys($gp_titles);
		$array = array_intersect($keys,$array);
		if( count($array) > 0 ){
			return ','.implode(',',$array).',';
		}
		return '';
	}



	public function SaveUserFile($refresh = true ){
		global $langmessage;

		if( !\gp\tool\Files::SaveData('_site/users','users',$this->users) ){
			msg($langmessage['OOPS']);
			return false;
		}

		if( $refresh && isset($_GET['gpreq']) && $_GET['gpreq'] == 'json' ){
			msg($langmessage['SAVED'].' '.$langmessage['REFRESH']);
		}else{
			msg($langmessage['SAVED']);
		}
		return true;
	}


	/**
	 * Show all users and their permissions
	 *
	 */
	public function DefaultDisplay(){
		global $langmessage;


		echo '<h2>'.$langmessage['user_permissions'].'</h2>';

		ob_start();
		echo '<table class="bordered full_width">';
		echo '<tr><th>';
		echo $langmessage['username'];
		echo '</th><th>';
		echo $langmessage['Password Algorithm'];
		echo '</th><th>';
		echo $langmessage['permissions'];
		echo '</th><th>';
		echo $langmessage['file_editing'];
		echo '</th><th>';
		echo $langmessage['options'];
		echo '</th></tr>';

		foreach($this->users as $username => $userinfo){

			echo '<tr><td>';
			echo $username;

			//algorithm
			echo '</td><td>';
			$this->PassAlgo($userinfo);


			//admin permissions
			echo '</td><td>';
				if( $userinfo['granted'] == 'all' ){
					echo 'all';
				}elseif( !empty($userinfo['granted']) ){

					$permissions = explode(',',$userinfo['granted']);
					$list = array();
					foreach($permissions as $permission){
						if( isset($this->possible_permissions[$permission]) ){
							$list[] = $this->possible_permissions[$permission];
						}
					}
					if( count($list) ){
						echo implode(', ',$list);
					}else{
						echo $langmessage['None'];
					}
				}else{
					echo $langmessage['None'];
				}

			echo '</td>';

			//file editing
			echo '<td>';

			if( $userinfo['editing'] == 'all' ){
				echo $langmessage['All'];
			}else{

				$count = preg_match_all('#,#',$userinfo['editing']) - 1; //count the commas
				if( $count > 0 ){
					echo sprintf($langmessage['%s Pages'],$count);
				}else{
					echo $langmessage['None'];
				}
			}

			echo '</td>';

			//options
			echo '<td>';
			echo \gp\tool::Link('Admin/Users',$langmessage['details'],'cmd=details&username='.$username);
			echo ' &nbsp; ';
			echo \gp\tool::Link('Admin/Users',$langmessage['password'],'cmd=changepass&username='.$username);
			echo ' &nbsp; ';

			$title = sprintf($langmessage['generic_delete_confirm'],htmlspecialchars($username));
			echo \gp\tool::Link('Admin/Users',$langmessage['delete'],'cmd=RemoveUser&username='.$username,array('data-cmd'=>'postlink','title'=>$title,'class'=>'gpconfirm'));
			echo '</td>';
			echo '</tr>';
		}
		echo '<tr><th colspan="5">';
		echo \gp\tool::Link('Admin/Users',$langmessage['new_user'],'cmd=newuserform');
		echo '</th>';

		echo '</table>';

		$content = ob_get_clean();

		if( $this->has_weak_pass ){
			echo '<p class="gp_notice"><b>Warning:</b> ';
			echo 'Weak password algorithms are being used for one or more users. To fix this issue, reset the user\'s password. ';
			echo '</p>';
		}

		echo $content;
	}


	/**
	 * Display the password algorithm being used for the user
	 *
	 */
	public function PassAlgo($userinfo){

		$algo = \gp\tool\Session::PassAlgo($userinfo);
		switch($algo){
			case 'md5':
			case 'sha1':
			$this->has_weak_pass = true;
			echo '<span style="color:red">'.$algo.'</span>';
			return;
		}
		echo $algo;
	}


	/**
	 * Display form for adding new admin user
	 *
	 */
	public function NewUserForm(){
		global $langmessage;

		echo '<h2>'.$langmessage['user_permissions'].'</h2>';

		$_POST += array('username'=>'','email'=>'','grant'=>array(),'grant_all'=>'all','editing_all'=>'all');

		echo '<form action="'.\gp\tool::GetUrl('Admin/Users').'" method="post" id="permission_form">';
		echo '<table class="bordered" style="width:95%">';
		echo '<tr><th colspan="2">';
			echo $langmessage['new_user'];
			echo '</th></tr>';
		echo '<tr><td>';
			echo $langmessage['username'];
			echo '</td><td>';
			echo '<input type="text" name="username" value="'.htmlspecialchars($_POST['username']).'" class="gpinput"/>';
			echo '</td></tr>';
		echo '<tr><td>';
			echo $langmessage['password'];
			echo '</td><td>';
			echo '<input type="password" name="password" value="" class="gpinput"/>';
			echo '</td></tr>';
		echo '<tr><td>';
			echo str_replace(' ','&nbsp;',$langmessage['repeat_password']);
			echo '</td><td>';
			echo '<input type="password" name="password1" value="" class="gpinput"/>';
			echo '</td></tr>';

		$this->AlgoSelect();

		$_POST['granted'] = $this->GetPostedPermissions(false);
		$_POST['editing'] = $this->GetEditingPermissions();
		$this->DetailsForm($_POST);


		echo '<tr><td>';
			echo '</td><td>';
			echo '<input type="hidden" name="cmd" value="CreateNewUser" />';
			echo ' <input type="submit" name="aaa" value="'.$langmessage['save'].'" class="gpsubmit"/>';
			echo ' <input type="reset" class="gpsubmit"/>';
			echo ' <input type="submit" name="cmd" value="'.$langmessage['cancel'].'" class="gpcancel"/>';
			echo '</td></tr>';

		echo '</table>';
		echo '</form>';
	}

	/**
	 * Display <select> for password algorithm
	 *
	 */
	public function AlgoSelect(){
		global $langmessage;

		$algos						= array();
		if( function_exists('password_hash') ){
			$algos['password_hash']		= true;
			$algos['sha512']			= true;
		}else{
			$algos['sha512']			= true;
			$algos['password_hash']		= false;
		}

		echo '<tr><td>';
		echo str_replace(' ','&nbsp;',$langmessage['Password Algorithm']);
		echo '</td><td>';
		echo '<select name="algo" class="gpselect">';
		foreach($algos as $algo => $avail){

			$attr = '';
			if( !$avail ){
				$attr .= 'disabled';
			}
			if( isset($_REQUEST['algo']) && $algo == $_REQUEST['algo'] ){
				$attr .= ' selected';
			}
			echo '<option value="'.$algo.'" '.$attr.'>'.$algo.'</option>';
		}
		echo '</select>';

		echo ' &nbsp; <span class="sm text-muted">password_hash requires PHP 5.5+</span>';

		echo '</td></tr>';


	}


	/**
	 * Display permission options
	 *
	 */
	public function DetailsForm( $values=array() ){
		global $langmessage, $gp_titles;

		$values += array('granted'=>'','email'=>'');

		//email address
		echo '<tr><td>';
		echo str_replace(' ','&nbsp;',$langmessage['email_address']);
		echo '</td><td>';
		echo '<input type="text" name="email" value="'.htmlspecialchars($values['email']).'" class="gpinput"/>';
		echo ' - DMARC compliant address !</td></tr>';


		//admin permissions
		echo '<tr><td>';
		echo str_replace(' ','&nbsp;',$langmessage['grant_usage']);
		echo '</td><td class="all_checkboxes">';

		$all = false;
		$current = $values['granted'];
		$checked = '';
		if( $current == 'all' ){
			$all = true;
			$checked = ' checked="checked" ';
		}else{
			$current = ','.$current.',';
		}

		echo '<p><label class="select_all">';
		echo '<input type="checkbox" class="select_all" name="grant_all" value="all" '.$checked.'/>';
		echo $langmessage['All'];
		echo '</label></p>';

		foreach($this->possible_permissions as $permission => $label){
			$checked = '';
			if( $all ){
				$checked = ' checked="checked" ';
			}elseif( strpos($current,','.$permission.',') !== false ){
				$checked = ' checked="checked" ';
			}

			echo '<label class="all_checkbox">';
			echo '<input type="checkbox" name="grant[]" value="'.$permission.'" '.$checked.'/>';
			$title_attr = trim(strip_tags($label));
			preg_match('/title="(.*?)".*?>/si', $label, $matches); 
			if( isset($matches[1]) ){
				$title_attr = $matches[1].': '.$title_attr;
			}
			echo '<span title="'.$title_attr.'">'.$label.'</span>';
			echo '</label> ';
		}

		echo '</td></tr>';

		//file editing
		echo '<tr><td>';
		echo $langmessage['file_editing'];
		echo '</td><td class="all_checkboxes">';

		$editing_values = $values['editing'];
		$all = ($editing_values == 'all');
		$checked = $all ? ' checked="checked" ' : '';
		echo '<p><label class="select_all">';
		echo '<input type="checkbox" class="select_all" name="editing_all" value="all" '.$checked.'/> ';
		echo $langmessage['All'];
		echo '</label></p>';

		echo '<div style="max-height:168px;overflow:auto;">';

		$ordered = array();
		foreach($gp_titles as $index => $info){
			$ordered[$index] = strip_tags(\gp\tool::GetLabelIndex($index));
		}

		uasort($ordered,'strnatcasecmp');

		foreach($ordered as $index => $label){
			$label = strip_tags($label);
			$checked = '';
			if( $all ){
				$checked = ' checked="checked" ';
			}elseif( strpos($editing_values,','.$index.',') !== false ){
				$checked = ' checked="checked" ';
			}

			echo '<label class="all_checkbox">';
			echo '<input type="checkbox" name="titles[]" value="'.$index.'" '.$checked.'/>';
			echo '<span title="'.$label.'">'.$label.'</span>';
			echo '</label> ';
		}

		echo '</div>';
		echo '</td></tr>';

	}

	/**
	 * Display form for changing a user password
	 *
	 */
	public function ChangePass(){
		global $langmessage;

		$username =& $_REQUEST['username'];
		if( !isset($this->users[$username]) ){
			msg($langmessage['OOPS']);
			return;
		}


		echo '<form action="'.\gp\tool::GetUrl('Admin/Users').'" method="post">';
		echo '<input type="hidden" name="cmd" value="resetpass" />';
		echo '<input type="hidden" name="username" value="'.htmlspecialchars($username).'" />';

		echo '<table class="bordered">';
		echo '<tr><th colspan="2">';
			echo $langmessage['change_password'];
			echo ' - ';
			echo $username;
			echo '</th></tr>';
		echo '<tr><td>';
			echo $langmessage['new_password'];
			echo '</td><td>';
			echo '<input type="password" name="password" value="" class="gpinput"/>';
			echo '</td></tr>';
		echo '<tr><td>';
			echo str_replace(' ','&nbsp;',$langmessage['repeat_password']);
			echo '</td><td>';
			echo '<input type="password" name="password1" value="" class="gpinput"/>';
			echo '</td></tr>';

		$this->AlgoSelect();

		echo '<tr><td>';
			echo '</td><td>';
			echo '<input type="submit" name="aaa" value="'.$langmessage['save'].'" class="gpsubmit" />';
			echo ' <input type="submit" name="cmd" value="'.$langmessage['cancel'].'" class="gpcancel" />';
			echo '</td></tr>';
		echo '</table>';
		echo '</form>';
	}

	/**
	 * Save a user's new password
	 *
	 */
	public function ResetPass(){
		global $langmessage, $config;

		if( !$this->CheckPasswords() ){
			return false;
		}

		$username = $_POST['username'];
		if( !isset($this->users[$username]) ){
			msg($langmessage['OOPS']);
			return false;
		}

		$this->SetUserPass( $username, $_POST['password']);

		return $this->SaveUserFile();
	}

	/**
	 * Check the posted passwords
	 * Make sure they're not empty and the match each other
	 *
	 */
	public function CheckPasswords(){
		global $langmessage;

		//see also Admin/Users for password checking
		if( ($_POST['password']=="") || ($_POST['password'] !== $_POST['password1'])  ){
			msg($langmessage['invalid_password']);
			return false;
		}
		return true;
	}

	public function GetUsers(){

		$this->users		= \gp\tool\Files::Get('_site/users');

		//fix the editing value
		foreach($this->users as $username => $userinfo){
			$userinfo += array('granted'=>'');
			\gp\admin\Tools::EditingValue($userinfo);
			$this->users[$username] = $userinfo;
		}
	}


	/**
	 * Get the menu indexes
	 *
	 */
	public function RequestedIndexes(){
		global $langmessage, $gp_titles;

		$_REQUEST		+= array('index'=>'');
		$indexes		= explode(',',$_REQUEST['index']);

		if( empty($indexes) ){
			msg($langmessage['OOPS'].' Invalid Title (1)');
			return;
		}

		$cleaned = array();
		foreach($indexes as $index){
			if( !isset($gp_titles[$index]) ){
				continue;
			}
			$cleaned[] = $index;
		}

		if( empty($cleaned) ){
			msg($langmessage['OOPS'].' Invalid Title (2)');
			return;
		}

		return $cleaned;
	}

}
