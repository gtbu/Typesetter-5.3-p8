<?php

$GP_GETALLGADGETS = true;

/*
$link = common::Link('','%s');
gpOutput::Area('header','<h1>'.$link.'</h1>');
gpOutput::Area('link_label','<h3>%s</h3>');
*/

/* for user specific entries */

global $page;
$themeDir = dirname($page->theme_path); 

/**
 * Include current layout settings (examples)
 */
//$page->head .= '<link rel="stylesheet" type="text/css" href="'.$themeDir.'/assets/css/style.css'.'" />';
//include($page->theme_dir . '/' . $page->theme_color . '/settings.php');
//$page->head_js[] = $themeDir.'/assets/js/bootnavbar.js'; 

$page->head_js[] = $themeDir.'/assets/js/script.js';
$page->head_js[] = $themeDir.'/assets/js/init.js';


