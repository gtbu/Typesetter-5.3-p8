     
<?php

$GP_MENU_ELEMENTS = 'Bootstrap5_menu';

/**
 * Generates menu link elements compatible with Bootstrap 5.2 navbars/dropdowns.
 *
 * @param string $node The HTML node type being processed (expects 'a').
 * @param array $attributes Associative array of link attributes:
 *    'href_text' => The URL (value for href attribute).
 *    'attr'      => String containing existing HTML attributes (e.g., 'id="my-link"').
 *    'label'     => The visible text label of the link.
 *    'title'     => The title attribute text (tooltip).
 *    'class'     => An array of classes assigned by the menu system (used to check for 'dropdown-toggle').
 * @param int $level The depth level of the menu item (0 for top level).
 * @param mixed $menu_id The ID of the menu being processed.
 * @param int $item_position The position of the item within its level.
 *
 * @return string|null The generated HTML for the <a> tag, or null if $node is not 'a'.
 */
function Bootstrap5_menu($node, $attributes, $level, $menu_id, $item_position){
    GLOBAL $GP_MENU_LINKS;

   if( $node == 'a' ){
    // --- Add Bootstrap specific classes ---
    $strpos_class = strpos($attributes['attr'], 'class="');
    $add_class = ( $level > 0 ) ? "dropdown-item" : "nav-link";

    if( $strpos_class === false ){
      $attributes['attr'] .= ' class="' . $add_class . '"';
      $strpos_class = strpos($attributes['attr'], 'class="');
    } else {
      $attributes['attr'] = substr($attributes['attr'], 0, $strpos_class + 7) // Part before classes
        . $add_class . ' '                                                   // The new class + space
        . substr($attributes['attr'], $strpos_class + 7);                    // The rest of the original classes
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
    // --- End Determine the link format ---
    
    return str_replace( $search, $attributes, $format );
  }
  return null;
}

?>

      
