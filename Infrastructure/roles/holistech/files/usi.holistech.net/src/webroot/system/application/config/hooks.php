<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	http://codeigniter.com/user_guide/general/hooks.html
|
*/

// The hook "post_controller_parent_constructor" was added to the system by Jason Karcz
// It's called from the constructor of the Controller library.

$hook['post_controller_parent_constructor'][] = array(
	'class' => '',
	'function' => 'loadCourse',
	'filename' => 'loadCourse.php',
	'filepath' => 'hooks'
);

$hook['post_controller_parent_constructor'][] = array(
	'class' => '',
	'function' => 'authenticate',
	'filename' => 'authenticate.php',
	'filepath' => 'hooks'
);

$hook['post_controller'][] = array(
	'class' => '',
	'function' => 'saveUsers',
	'filename' => 'saveUsers.php',
	'filepath' => 'hooks'
);

$hook['post_controller'][] = array(
	'class' => '',
	'function' => 'errorsToHeaders',
	'filename' => 'errors.php',
	'filepath' => 'hooks'
);

$hook['display_override'][] = array(
	'class' => '',
	'function' => 'final_display',
	'filename' => 'final_display.php',
	'filepath' => 'hooks'
);


/* End of file hooks.php */
/* Location: ./system/application/config/hooks.php */
