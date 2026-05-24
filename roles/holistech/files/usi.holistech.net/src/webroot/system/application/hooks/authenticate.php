<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

function authenticate()
{
	$ci =& get_instance();
	//$ci->output->enable_profiler(true);
	//$GLOBALS['user'] =& getUser('root');
	//$ci->auth->allowAll = TRUE;
	$ci->auth->authenticate();
}
