<?php

namespace gp\admin\Layout;

defined('is_running') or die('Not an entry point...');

class Text extends \gp\admin\Layout{

	public function RunScript(){


		$this->cmds['EditText']					= '';
		$this->cmds['SaveText']					= 'ReturnHeader';

		$this->cmds['AddonTextForm']			= '';
		$this->cmds['SaveAddonText']			= 'ReturnHeader';

		$cmd = \gp\tool::GetCommand();
		$this->RunCommands($cmd);
	}



	public function AddonTextForm(){
		global $langmessage,$config;

		$addon = \gp\tool\Editing::CleanArg($_REQUEST['addon']);
		$texts = $this->GetAddonTexts($addon);

		//not set up correctly
		if( $texts === false ){
			$this->EditText();
			return;
		}


		echo '<div class="inline_box" style="text-align:right">';
		echo '<form action="'.\gp\tool::GetUrl('Admin_Theme_Content/Text').'" method="post">';
		echo '<input type="hidden" name="cmd" value="SaveAddonText" />';
		echo '<input type="hidden" name="addon" value="'.htmlspecialchars($addon, ENT_QUOTES).'" />'; //will be populated by javascript


		$this->AddonTextFields($texts);
		echo ' <input type="submit" name="aaa" value="'.$langmessage['save'].'" class="gpsubmit" />';
		echo ' <input type="submit" name="cmd" value="'.$langmessage['cancel'].'" class="admin_box_close gpcancel" />';


		echo '</form>';
		echo '</div>';

	}

	public function AddonTextFields($array){
		global $langmessage,$config;
		echo '<table class="bordered">';
		echo '<tr><th>';
		echo $langmessage['default'];
		echo '</th><th>';
		echo '</th></tr>';

		$key = isset($_GET['key']) ? $this->sanitizeKey($_GET['key']) : '';  // Sanitize first
		foreach($array as $text){

			$value = $text;
			if( isset($langmessage[$text]) ){
				$value = $langmessage[$text];
			}
			if( isset($config['customlang'][$text]) ){
				$value = $config['customlang'][$text];
			}

			$style = '';
			if( $text == $key ){
				$style = ' style="background-color:#f5f5f5"';
			}

			echo '<tr'.$style.'><td>';
			echo htmlspecialchars($text, ENT_QUOTES);
			echo '</td><td>';
			echo '<input type="text" name="values['.htmlspecialchars($text, ENT_QUOTES).']" value="'.htmlspecialchars($value, ENT_QUOTES).'" class="gpinput"/>';
			//value has already been escaped with htmlspecialchars()
			echo '</td></tr>';

		}
		echo '</table>';
	}


	public function EditText(){
		global $config, $langmessage, $nonce_str;

		if( !isset($_GET['key']) ){
			msg($langmessage['OOPS'].' (0)');
			return;
		}

        $key = $this->sanitizeKey($_GET['key']); // Sanitize input
        $default = isset($langmessage[$key]) ? $langmessage[$key] : htmlspecialchars($key, ENT_QUOTES);
        $value = isset($config['customlang'][$key]) ? $config['customlang'][$key] : htmlspecialchars($key, ENT_QUOTES);

       	echo '<div class="inline_box">';
		echo '<form action="'.\gp\tool::GetUrl('Admin_Theme_Content/Text').'" method="post">';
		echo '<input type="hidden" name="nonce" value="'.htmlspecialchars(\gp\tool::new_nonce($nonce_str)).'" />';
		echo '<input type="hidden" name="cmd" value="savetext" />';
		echo '<input type="hidden" name="key" value="'.htmlspecialchars($key, ENT_QUOTES).'" />';

		echo '<table class="bordered full_width">';
		echo '<tr><th>';
		echo $langmessage['default'];
		echo '</th><th>';
		echo $langmessage['edit'];
		echo '</th></tr>';
		echo '<tr><td>';
		echo htmlspecialchars($default, ENT_QUOTES);
		echo '</td><td>';
		//$value is already escaped using htmlspecialchars()
		echo '<input type="text" name="value" value="'.htmlspecialchars($value, ENT_QUOTES).'" class="gpinput full_width"/>';
	  	echo '</td></tr>';
		echo '</table>';
		echo '<p>';
		echo ' <input type="submit" name="aaa" value="'.$langmessage['save'].'" data-cmd="gpajax" class="gpsubmit"/>';
		echo ' <input type="submit" name="cmd" value="'.$langmessage['cancel'].'" class="admin_box_close gpcancel" />';
		echo '</p>';

		echo '</form>';
		echo '</div>';
	}



	public function SaveText(){
		global $config, $langmessage;

		if( !isset($_POST['key']) ){
			msg($langmessage['OOPS'].' (0)');
			return;
		}
		if( !isset($_POST['value']) ){
			msg($langmessage['OOPS'].' (1)');
			return;
		}

        $key = $this->sanitizeKey($_POST['key']);
		$default = $key;
		if( isset($langmessage[$key]) ){
			$default = $langmessage[$key];
		}

		$config['customlang'][$key] = $value = htmlspecialchars($_POST['value'], ENT_QUOTES);
		if( ($value === $default) || (htmlspecialchars($default, ENT_QUOTES) == $value) ){
			unset($config['customlang'][$key]);
		}


		$this->SaveConfig();
	}


	public function SaveAddonText(){
		global $langmessage,$config;

		$addon = \gp\tool\Editing::CleanArg($_REQUEST['addon']);
		$texts = $this->GetAddonTexts($addon);
		//not set up correctly
		if( $texts === false ){
			msg($langmessage['OOPS'].' (0)');
			return;
		}

		foreach($texts as $text){
			if( !isset($_POST['values'][$text]) ){
				continue;
			}

            $text = $this->sanitizeKey($text);  // Sanitize text key as well
			$default = $text;
			if( isset($langmessage[$text]) ){
				$default = $langmessage[$text];
			}

			$value = htmlspecialchars($_POST['values'][$text], ENT_QUOTES);

			if( ($value === $default) || (htmlspecialchars($default, ENT_QUOTES) == $value) ){
				unset($config['customlang'][$text]);
			}else{
				$config['customlang'][$text] = $value;
			}
		}


		if( $this->SaveConfig() ){
			$this->UpdateAddon($addon);
		}

	}



	public function UpdateAddon($addon){
		if( !function_exists('OnTextChange') ){
			return;
		}

		\gp\tool\Plugins::SetDataFolder($addon);

		OnTextChange();

		\gp\tool\Plugins::ClearDataFolder();
	}

	public function GetAddonTexts($addon){
		global $langmessage,$config;


		$addon_config = \gp\tool\Plugins::GetAddonConfig($addon);
		$addonDir = $addon_config['code_folder_full'];
		if( !is_dir($addonDir) ){
			return false;
		}

		//not set up correctly
		if( !isset($config['addons'][$addon]['editable_text']) ){
			return false;
		}

		$file = $addonDir.'/'.$config['addons'][$addon]['editable_text'];
		if( !file_exists($file) ){
			return false;
		}

		$texts = array();
		include($file);

		if( empty($texts) ){
			return false;
		}

		return $texts;
	}

    /**
     * Sanitize the key parameter.  Allow only alphanumeric characters, underscores, and hyphens.
     *
     * @param string $key The key to sanitize.
     * @return string The sanitized key.
     */
    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
    }
}