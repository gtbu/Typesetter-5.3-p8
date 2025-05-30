<?php

namespace Addon\Example;

defined('is_running') or die('Not an entry point...');


class Admin{

	public function __construct(){
		echo '<h2>This is an Admin Only Script</h2>';

		echo '<p>This is an example of a Typesetter Addon in the form of a Admin page.</p>';

		echo '<p>Admin pages are only accessible to users with appropriate permissions on your installation of Typesetter CMS. </p>';

		echo '<p>';
		echo \common::Link('Special_Example','An Example Link');
		echo '</p>';

		echo '<p>You can download <a href="https://github.com/gtbu/Online-Plugins/blob/main/Plugin%20Examples.zip"> a plugin with addtional examples</a> from github.com/gtbu </p>';
	}
}


