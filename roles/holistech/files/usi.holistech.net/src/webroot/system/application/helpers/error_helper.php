<?
$ci =& get_instance();
$ci->load->library('errors');

function errors($raw=FALSE)
{
	$ci =& get_instance();
	
	$out = $ci->errors->get_errors($raw);
	
	if ( property_exists( $ci, 'message' ) )
		$out .= $ci->message->get_messages();

	return $out;
}
	
function error($message)
{
	$ci =& get_instance();

	$ci->errors->error($message);
}
	
function fatal($message, $redirect=NULL)
{
	$ci =& get_instance();

	$ci->errors->fatal($message,$redirect);
}
	
function warn($message)
{
	$ci =& get_instance();

	$ci->errors->warn($message);
}

function debug( $text, $varDump=FALSE )
{
	$ci =& get_instance();

	$ci->errors->debug($text,$varDump);
}

function debug_log( $text, $varDump=FALSE )
{
	$ci =& get_instance();

	$ci->errors->debug_log($text,$varDump);
}
