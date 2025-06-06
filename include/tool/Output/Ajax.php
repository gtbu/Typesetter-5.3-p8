<?php

namespace gp\tool\Output{

	defined('is_running') or die('Not an entry point...');


	class Ajax{

		public static $script_objects	= array(
									'/include/js/inline_edit/inline_editing.js'		=> 'gp_editing',
									'/include/thirdparty/ckeditor/ckeditor.js'	=> 'CKEDITOR',
									'/include/js/ckeditor_config.js'				=> 'CKEDITOR',
									);


		public static function quote($content){
			return \gp\tool::JsonEncode($content);
		}

		public static function JsonDo($do,$selector,$content){
			static $comma = '';

			$json = ['DO'=>$do,'SELECTOR'=>$selector,'CONTENT'=>$content];
			echo $comma;
			echo \gp\tool::JsonEncode($json);
			$comma = ',';
		}


		/**
		 * Handle HTTP responses made with $_REQUEST['req'] = json (when <a ... data-cmd="gpajax">)
		 * Sends JSON object to client
		 *
		 */
		public static function Response(){
			global $page;

			//admin toolbar
			self::AdminToolbar();

			//gadgets may be using gpajax/json request/responses
			\gp\tool\Output::TemplateSettings();
			\gp\tool\Output::PrepGadgetContent();


			self::Header();
			$callback = self::Callback();

			echo $callback;
			echo '([';

			//output content
			if( !empty($_REQUEST['gpx_content']) ){
				self::JsonDo($_REQUEST['gpx_content'], '', $page->contentBuffer);
				
			}elseif( in_array('#gpx_content', $page->ajaxReplace) ){
				$replace_id = '#gpx_content';

				if( isset($_GET['gpreqarea']) ){
					$replace_id = '#' . $_GET['gpreqarea'];
				}

				ob_start();
				$page->GetGpxContent(true);
				$content = ob_get_clean();
				self::JsonDo('replace', $replace_id,$content);
			}

			//other areas
			foreach($page->ajaxReplace as $arguments){
				if( is_array($arguments) ){
					$arguments += array(0 => '', 1 => '', 2 => '');
					self::JsonDo($arguments[0], $arguments[1], $arguments[2]);
				}
			}


			//always send messages
			self::Messages();
			echo ']);';
			die();
		}


		/**
		 * Add the admin toolbar content to the ajax response
		 *
		 */
		public static function AdminToolbar(){
			global $page;

			if( !isset($_REQUEST['gpreq_toolbar']) ){
				return;
			}

			ob_start();
			\gp\admin\Tools::AdminToolbar();
			$toolbar = ob_get_clean();
			if( empty($toolbar) ){
				return;
			}

			$page->ajaxReplace[] = array('replace','#admincontent_panel',$toolbar);
		}


		/**
		 * Add the messages to the response
		 *
		 */
		public static function Messages(){

			$content = GetMessages(false);

			if( !empty($content) ){
				self::JsonDo('messages','',$content);
			}
		}



		/**
		 * Check the callback parameter, die with an alert if the test fails
		 *
		 */
		public static function Callback(){
			global $page;

			if( !is_array($page->ajaxReplace) ){
				self::InvalidCallback();
			}

			if( !isset($_REQUEST['jsoncallback']) ){
				self::InvalidCallback();
			}

			if( !preg_match('#^[a-zA-Z0-9_]+$#',$_REQUEST['jsoncallback'], $match) ){
				self::InvalidCallback();
			}
			return $match[0];
		}


		/**
		 * Send a response with message content only
		 *
		 */
		public static function InvalidCallback(){

			echo '$gp.Response([';
			self::Messages();
			echo ']);';
			die();
		}


		/**
		 * Send a header for the javascript request
		 * Attempt to find an appropriate type within the accept header
		 *
		 */
		public static function Header(){

			$accepts	= ['application/javascript'=>0.001,'application/x-javascript'=>0.0001,'text/javascript'=>0.0001];
			$mime		= \gp\tool\Headers::AcceptMime($accepts);

			header('Content-Type: '.$mime.'; charset=UTF-8');
			Header('Vary: Accept,Accept-Encoding');// for proxies
		}


		public static function InlineEdit($section_data){

			$section_data			+= array('type'=>'','content'=>'');
			$scripts				= array();
			$scripts[]				= array('object'=>'gp_editing','file'=>'/include/js/inline_edit/inline_editing.js');



			$type = 'text';
			if( !empty($section_data['type']) ){
				$type = $section_data['type'];
			}
			switch($type){

				case 'gallery':
					$scripts = self::InlineEdit_Gallery($scripts);
				break;

				case 'include':
					$scripts = self::InlineEdit_Include($scripts);
				break;

				case 'text';
					$scripts = self::InlineEdit_Text($scripts);
				break;

				case 'image';
					echo 'var gp_blank_img = ' . \gp\tool::JsonEncode(\gp\tool::GetDir('/include/imgs/blank.gif')) . ';';
					$scripts[] = '/include/js/jquery.auto_upload.js';
					$scripts[] = '/include/js/inline_edit/image_common.js';
					$scripts[] = '/include/js/inline_edit/image_edit.js';
				break;
			}

			$scripts = \gp\tool\Plugins::Filter('InlineEdit_Scripts',array($scripts,$type));

			//replace resized images with their originals
			if( isset($section_data['resized_imgs']) && is_array($section_data['resized_imgs']) && count($section_data['resized_imgs']) ){
				$section_data['content'] = \gp\tool\Editing::RestoreImages($section_data['content'],$section_data['resized_imgs']);
			}

			//create the section object that will be passed to gp_init_inline_edit
			$section_object = \gp\tool::JsonEncode($section_data);


			//send scripts and call gp_init_inline_edit()
			echo '(function(){';
			self::SendScripts($scripts);

			echo ';if( typeof(gp_init_inline_edit) == "function" ){';
			echo 'gp_init_inline_edit(';
			echo \gp\tool::JsonEncode($_GET['area_id']);
			echo ','.$section_object;
			echo ');';
			echo '}else{alert("gp_init_inline_edit() is not defined");}';
			echo '})();';
		}

		/**
		 * Send content of all files in the $scripts array to the client
		 *
		 */
		public static function SendScripts($scripts){
			global $dataDir, $dirPrefix;

			self::Header();

			$sent				= array();
			$scripts			= self::RemoveSent($scripts);


			//send all scripts
			foreach($scripts as $script){

				if( is_array($script) ){

					if( !empty($script['code']) ){
						echo "\n\n/** Code **/\n\n";
						echo $script['code'];
					}

					if( empty($script['file']) ){
						continue;
					}
					$script = $script['file'];
				}



				//absolute paths don't need $dataDir
				$full_path = $script;
				if( !empty($dataDir) && strpos($script,$dataDir) !== 0 ){

					//fix addon paths that use $addonRelativeCode
					if( !empty($dirPrefix) && strpos($script,$dirPrefix) === 0 ){
						$script = substr($script,strlen($dirPrefix));
					}
					$full_path = $dataDir.$script;
				}

				//only send each script once
				if( isset($sent[$full_path]) ){
					continue;
				}
				$sent[$full_path] = true;

				if( !file_exists($full_path) ){
					$msg = 'Admin Notice: The following file could not be found: \n\n'.$full_path;
					echo ';if(isadmin){alert('.json_encode($msg).');}';
					continue;
				}

				echo "\n\n/** $script **/\n\n";
				readfile($full_path);
			}
		}


		/**
		 * Remove scripts that have already been sent to the server
		 *
		 */
		public static function RemoveSent(array $scripts): array
        {
        $definedObjects = [];

        if (isset($_GET['defined_objects']) && is_string($_GET['defined_objects'])) {
        $definedObjects = explode(',', $_GET['defined_objects']);
        $definedObjects = array_map('trim', $definedObjects); // Trim whitespace

        } else {
        // Log or handle missing 'defined_objects' here
        error_log("Warning: 'defined_objects' not found in \$_GET. Continuing with empty array."); //Example log
        throw new InvalidArgumentException("'defined_objects' parameter is required.");
        }
		
        $cleansed = [];
        foreach ($scripts as $script) {
        $object = false;

        if (is_array($script) && !empty($script['object'])) {
            $object = $script['object'];
        } elseif (is_string($script) && isset(self::$script_objects[$script])) {
            $object = self::$script_objects[$script];
        }

        if ($object !== false && in_array($object, $definedObjects, true)) {
            error_log("Object $object already defined");
            continue;
        }

        $cleansed[] = $script;
        }

        return $cleansed;
        }

		/**
		 * Get scripts for editing inline text using ckeditor
		 *
		 */
		public static function InlineEdit_Text($scripts){

			// autocomplete
			$scripts[]		= array(
								'code'		=> \gp\tool\Editing::AutoCompleteValues(true),
								'object'	=> 'gptitles',
								);

			// ckeditor basepath and configuration
			$options = array(
							'extraPlugins' => 'sharedspace',
							'sharedSpaces' => array( 'top' => 'ckeditor_top', 'bottom' =>' ckeditor_bottom' )
							);

			$ckeditor_basepath = \gp\tool::GetDir('/include/thirdparty/ckeditor/');
			echo 'CKEDITOR_BASEPATH = ' . \gp\tool::JsonEncode($ckeditor_basepath) . ';';

			// config
			$scripts[]		= array(
								'code'		=> 'var gp_ckconfig = '.\gp\tool\Editing::CKConfig( $options, 'json', $plugins ).';',
								'object'	=> 'gp_ckconfig',
								);


			// extra plugins
			$scripts[]		= array(
								'code'		=> 'var gp_add_plugins = '.json_encode( $plugins ).';',
								'object'	=> 'gp_add_plugins',
								);


			// CKEDITOR
			$scripts[]		= array(
								'file'		=> '/include/thirdparty/ckeditor/ckeditor.js',
								'object'	=> 'CKEDITOR',
								);

			$scripts[]		= array(
								'file'		=> '/include/js/ckeditor_config.js',
								'object'	=> 'CKEDITOR',
								);

			$scripts[] = '/include/js/inline_edit/inlineck.js';

			return $scripts;
		}

		public static function InlineEdit_Include($scripts){
			$scripts[] = '/include/js/inline_edit/include_edit.js';
			return $scripts;
		}

		public static function InlineEdit_Gallery($scripts){
			$scripts[] = '/include/js/jquery.auto_upload.js';
			$scripts[] = '/include/js/inline_edit/image_common.js';
			$scripts[] = '/include/js/inline_edit/gallery_edit_202.js';
			return $scripts;
		}

	}
}

namespace{
	class gpAjax extends \gp\tool\Output\Ajax{}
}
