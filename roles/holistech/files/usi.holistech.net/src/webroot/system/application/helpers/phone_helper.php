<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * Phone number management
 *
 * @author Jason Karcz
 */
class Phone
{
	static function format($number,$ext='')
	{
		return preg_replace('/(\d\d\d)(\d\d\d)(\d\d\d\d)/', '(\1) \2-\3', $number)
			. ( $ext == '' ? '' : ' x'.$ext );
	}

	static function unformat($number)
	{
		return preg_replace('/\D/', '', $number);
	}
}
