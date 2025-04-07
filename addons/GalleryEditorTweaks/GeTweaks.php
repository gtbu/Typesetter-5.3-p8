<?php 

defined('is_running') or die('Not an entry point...');

class GeTweaks {

  static function GetHead() {
    global $page, $addonRelativeCode;
    if( \gp\tool::LoggedIn() ){
      $page->css_user[] = $addonRelativeCode . '/GeTweaks.css';
      \gp\Tool::LoadComponents('draggable,resizable');
    }
  }

  static function InlineEdit_Scripts($scripts, $type) {
    global $addonRelativeCode;
    if( $type !== 'gallery' ){
      return $scripts;
    }
    $scripts[] = $addonRelativeCode . '/GeTweaks.js';
    return $scripts;
  }

}
