<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

function final_display()
{
	$ci =& get_instance();

	// Don't filter anything from the layout controller
	// since that's responsible for JS and CSS and would be unpredictable
	if ( $ci->uri->segment(1) == 'layout' )
	{
		$ci->output->_display();
		return;
	}

	$out = $ci->output->get_output();
	//$out = filterTags($out,'PAGE') . $ci->errors->debug_log;
	$out .= $ci->errors->debug_log;
	$ci->output->set_output($out);
	$ci->output->_display();
}
