     
<?php

$GP_MENU_ELEMENTS = 'Bootstrap5_menu';

/**
 * Generates menu link elements compatible with Bootstrap 5.2 navbars/dropdowns.
 */
function Bootstrap5_menu($node, $attributes, $level, $menu_id, $item_position){
    GLOBAL $GP_MENU_LINKS;

   if( $node == 'a' ){
    $strpos_class = strpos($attributes['attr'], 'class="');
    $add_class = ( $level > 0 ) ? "dropdown-item" : "nav-link";

    if( $strpos_class === false ){
      $attributes['attr'] .= ' class="' . $add_class . '"';
      $strpos_class = strpos($attributes['attr'], 'class="');
    } else {
      $attributes['attr'] = substr($attributes['attr'], 0, $strpos_class + 7) 
        . $add_class . ' '                                                  
        . substr($attributes['attr'], $strpos_class + 7);                    
    }
   
    // Ensure 'title' is included if it might be used in $GP_MENU_LINKS
    $search = array('{$href_text}', '{$attr}', '{$label}', '{$title}');

    if( isset($attributes['class']) && is_array($attributes['class']) && in_array('dropdown-toggle', $attributes['class']) ){
      $format = '<a {$attr} data-bs-toggle="dropdown" href="{$href_text}">{$label}</a>';
    } else {
      if (isset($GP_MENU_LINKS) && !empty($GP_MENU_LINKS)) {
          $format = $GP_MENU_LINKS;
    } else {
          $format = '<a {$attr} href="{$href_text}">{$label}</a>';
      }
    }
        
    return str_replace( $search, $attributes, $format );
  }
  return null;
}

?>

      
