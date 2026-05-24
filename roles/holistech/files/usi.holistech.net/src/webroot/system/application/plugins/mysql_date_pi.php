<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * MySQL Date plugin
 * Takes timestamp and returns MySQL DATETIME formatted string
 *
 * @author Jason Karcz
 * @param	string	$date
 * @return	string
 */
function mysql_date($date)
{
	return date('Y-m-d H:i:s',$date);
}
