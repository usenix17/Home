<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

function valid_languages($text)
{
	$languages = explode(',',$text);

	foreach ( $languages as $l )
	{
		if ( ! array_key_exists($l,$GLOBALS['iso639']) )
		{
			$ci =& get_instance();		
			$ci->form_validation->set_message('valid_languages', $l.' is not a valid ISO-639 language code.');
			return FALSE;
		}
	}

	return TRUE;
}

