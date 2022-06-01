<?php
$GP_GETALLGADGETS = true;
/*
$link = common::Link('','%s');
gpOutput::Area('header','<h1>'.$link.'</h1>');
gpOutput::Area('link_label','<h3>%s</h3>');
*/

global $page;
// moved from theme.php - otherwise always present
$page->head_js[] = dirname($page->theme_path) . '/addons/CajonParallax/CajonParallax.js';
$page->head_js[] = dirname($page->theme_path) . '/addons/CajonParallax/jquery.scrollspeed/jQuery.scrollSpeed.js';