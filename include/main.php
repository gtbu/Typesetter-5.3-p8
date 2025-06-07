<?php


define('gp_start_time', microtime(true));

defined('is_running') or define('is_running',true);
require_once('common.php');
\gp\tool::EntryPoint(0);

/*
 *	Flow Control
 */

if( !empty($GLOBALS['config']['updating_message']) ){
	die($GLOBALS['config']['updating_message']);
}


$title = \gp\tool::WhichPage();
$type = \gp\tool::SpecialOrAdmin($title);
switch($type){

	case 'special':
		$page = new \gp\special\Page($title, $type);
	break;

	case 'admin':
		if( \gp\tool::LoggedIn() ){
			$page = new \gp\admin\Page($title, $type);
		}else{
			$page = new \gp\admin\Login($title, $type);
		}
	break;

	default:
		if( \gp\tool::LoggedIn() ){
			$page = new \gp\Page\Edit($title, $type);
		}else{
			$page = new \gp\Page($title, $type);
		}
	break;
}

\gp\tool\Plugins::Action('PageCreated');

if (session_status() == PHP_SESSION_NONE) {
        //error_log("No active session detected. Attempting session_start()...");
		if (!session_start()) {
            error_log("session_start() FAILED.");
			die("Session could not be started.");
        } else {
            //error_log("session_start() SUCCEEDED. Session ID: " . session_id());
        }
    }

\gp\tool\Output::RunOut();
