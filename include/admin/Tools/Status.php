<?php

namespace gp\admin\Tools;

defined('is_running') or die('Not an entry point...');

class Status extends \gp\special\Base{

	protected $check_dir_len	= 0;
	protected $failed_count		= 0;
	protected $failed			= [];
	protected $passed_count		= 0;
	protected $show_failed_max	= 50;
	protected $deletable		= [];

	protected $euid;

	public function __construct(){

	}

	public function RunScript(){
		global $langmessage;

		echo '<h2>'.$langmessage['Site Status'].'</h2>';

		$cmd = \gp\tool::GetCommand();
		switch($cmd){
			case 'FixOwner':
				$this->FixOwner();
			break;
		}

		$this->CheckDataDir();
		$this->DefaultDisplay();
	}

	public function CheckDataDir(){
		global $dataDir;

		$this->check_dir_len	= 0;
		$this->failed_count		= 0;
		$this->passed_count		= 0;
		$this->show_failed_max	= 50;


		$check_dir				= $dataDir.'/data';
		$this->check_dir_len	= strlen($check_dir);
		$this->euid				= '?';

		if( function_exists('posix_geteuid') ){
			$this->euid = posix_geteuid();
		}


		$this->CheckDir($check_dir);
	}

	public function DefaultDisplay(){
		global $langmessage, $dataDir;

		$check_dir		= $dataDir.'/data';
		$checked		= $this->passed_count + $this->failed_count;

		if( $this->failed_count === 0 ){
			echo '<p class="gp_passed">';
			echo sprintf($langmessage['data_check_passed'],$checked,$checked);
			echo '</p>';
			$this->ShowDeletable();

			return;
		}

		echo '<p class="gp_notice">';
		echo sprintf($langmessage['data_check_failed'],$this->failed_count,$checked);
		echo '</p>';


		// the /data directory isn't writable
		if( count($this->failed) == 1 && in_array($check_dir,$this->failed) ){
			echo '<p class="gp_notice">';
			echo '<b>WARNING:</b> Your data directory at is no longer writable: '.$check_dir;
			echo '</p>';
			return;
		}


		if( $this->failed_count > $this->show_failed_max ){
			echo '<p class="gp_notice">';
			echo sprintf($langmessage['showing_max_failed'],$this->show_failed_max);
			echo '</p>';
		}


		echo '<table class="bordered">';
		echo '<tr><th>';
		echo $langmessage['file_name'];
		echo '</th><th>';
		echo $langmessage['File Owner'];
		echo '<br/>';
		echo $langmessage['Current_Value'];
		echo '</th><th>';
		echo '<br/>';
		echo $langmessage['Expected_Value'];
		echo '</th><th> &nbsp;';
		echo '</th></tr>';


		// sort by strlen to get directories first
		usort($this->failed, function($a, $b) {
		    return strlen($a) - strlen($b);
		});

		foreach($this->failed as $i => $path){

			if( $i > $this->show_failed_max ){
				break;
			}

			$readable_path		= substr($path,$this->check_dir_len);
			$euid				= \gp\install\FilePermissions::file_uid($path);

			echo '<tr><td>';
			echo $readable_path;
			echo '</td><td>';

			echo $this->ShowUser($euid);
			echo '</td><td>';
			echo $this->ShowUser($this->euid);
			echo '</td><td>';
			echo \gp\tool::Link('Admin/Status','Fix','cmd=FixOwner&path='.rawurlencode($readable_path),'data-cmd="cnreq"');
			echo '</td></tr>';
		}

		echo '</table>';

		$this->CheckPageFiles();
		$this->ShowDeletable();

	}

	/**
	 * Show Deletable Files
	 */
	protected function ShowDeletable(){
		if( empty($this->deletable) ){
			return;
		}

		echo '<h3>Deletable Files</h3>';
		echo '<ol>';
		foreach($this->deletable as $file){
			echo '<li>'.htmlspecialchars($file).'</li>';
		}
		echo '</ol>';

	}



	/**
	 * Check page files for orphaned data files
	 *
	 */
	protected function CheckPageFiles(){
		global $dataDir,$gp_index;

		$pages_dir = $dataDir.'/data/_pages';
		$all_files = \gp\tool\Files::ReadDir($pages_dir,'php');
		foreach($all_files as $key => $file){
			$all_files[$key] = $pages_dir.'/'.$file.'.php';
		}

		$page_files = array();
		foreach($gp_index as $slug => $index){
			$page_files[] = \gp\tool\Files::PageFile($slug);
		}

		$diff = array_diff($all_files,$page_files);

		if( !count($diff) ){
			return;
		}

		echo '<h2>Orphaned Data Files</h2>';
		echo '<p>The following data files appear to be orphaned and are most likely no longer needed. Before completely removing these files, we recommend backing them up first.</p>';
		echo '<table class="bordered"><tr><th>File</th></tr>';
		foreach($diff as $file){
			echo '<tr><td>'
				. $file
				. '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * Check the ownership of the directory and files within it
	 * @param string $dir
	 *
	 */
	protected function CheckDir($dir){

		if( !$this->CheckFile($dir) ){
			return;
		}

		$dh = @opendir($dir);
		if( $dh === false ){
			$this->failed_count++;
			$this->failed[] = $dir;
			return;
		}

		while( ($file = readdir($dh)) !== false){
			if( $file === '.' || $file === '..' ){
				continue;
			}

			$full_path = $dir.'/'.$file;
			if( is_link($full_path) ){
				continue;
			}

			if( preg_match('#x-deletable-[0-9]+#',$full_path) ){
				$this->deletable[] = $full_path;
				continue;
			}

			if( is_dir($full_path) ){
				$this->CheckDir($full_path);
			}else{
				$this->CheckFile($full_path,'file');
			}
		}
	}

	protected function CheckFile($path,$type='dir'){

		if( \gp\install\FilePermissions::HasFunctions() ){
			$current = @substr(decoct( @fileperms($path)), -3);

			if( $type === 'file' ){
				$expected = \gp\install\FilePermissions::getExpectedPerms_file($path);
			}else{
				$expected = \gp\install\FilePermissions::getExpectedPerms($path);
			}

			if( \gp\install\FilePermissions::perm_compare($expected,$current) ){
				$this->passed_count++;
				return true;
			}

		}elseif( gp_is_writable($path) ){
			$this->passed_count++;
			return true;
		}

		$this->failed_count++;
		$this->failed[] = $path;

		return false;
	}


	/**
	 * Display a user name and uid
	 * @param int $uid
	 */
	protected function ShowUser($uid){
		$user_info = posix_getpwuid($uid);
		if( $user_info ){
			return $user_info['name'].' ('.$uid.')';
		}

		return $uid;
	}


	/**
	 * Attempt to fix the ownership issue of the posted file
	 * 1) create a copy of the file
	 * 2) move old to temp folder
	 * 3) move new into original place of old
	 * 4) attempt to delete temp folder
	 *
	 */
	public function FixOwner(){
		global $dataDir, $langmessage;


		$to_fix				= '/data'.$_REQUEST['path'];
		$to_fix_full		= $dataDir . $to_fix;
		$new_file			= \gp\tool\FileSystem::TempFile($to_fix);
		$new_file_full		= $dataDir . $new_file;
		$deletable			= \gp\tool\FileSystem::TempFile(dirname($to_fix).'/x-deletable');
		$deletable_full		= $dataDir . $deletable;

		if( !\gp\tool\Files::CheckPath( $to_fix_full ) ){
			msg($langmessage['OOPS'].' Invalid Path');
			return;
		}


		echo '<ol>';
		echo '<li>Copy: '.$to_fix.' -&gt; ' . $new_file . '</li>';

		if( !\gp\admin\Tools\Port::CopyAll($to_fix_full,$new_file_full) ){
			echo '<li>Failed</li>';
			echo '</ol>';
			msg($langmessage['OOPS'].' Not Copied');
			\gp\tool\Files::RmAll($new_file_full);
			return;
		}

				
		// move old to deletable
		echo '<li>Move: '.htmlspecialchars($to_fix, ENT_QUOTES, 'UTF-8').' -> ' . $deletable . '</li>';
		if( !rename($to_fix_full,$deletable_full) ){
			echo '<li>Failed</li>';
			echo '</ol>';
			msg($langmessage['OOPS'].' Rename to deletable failed');
			\gp\tool\Files::RmAll($new_file_full);
			return;
		}
		

		// move
		echo '<li>Move: '.$new_file.' -&gt; ' . $to_fix . '</li>';
		if( !rename($new_file_full, $to_fix_full) ){
			echo '<li>Failed</li>';
			echo '</ol>';
			msg($langmessage['OOPS'].' Rename to old failed');
			return;
		}

		echo '<li>Success</li>';

		// attempt to remove deletable
		if( !\gp\tool\Files::RmAll($deletable_full) ){
			echo '<li>Note: '.$deletable.' was not deleted</li>';
		}

		echo '</ol>';

	}


}
