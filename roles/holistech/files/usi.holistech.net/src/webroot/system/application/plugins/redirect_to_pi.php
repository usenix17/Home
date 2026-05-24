<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * Redirects to the provided internal URI, or the referrer.
 * This function saves all errors and messages.
 *
 * @author Jason Karcz
 */
function redirect_to($url = 'referrer')
{
	// Strip trailing slash
	if ( $url && $url[0] == '/' )
		$url = substr($url,1);

	$ci =& get_instance();
	$ci->errors->store_errors();

	//return;

	if ( $url == 'referrer' || $url === NULL )
	{
		$ci->load->library('user_agent');
		header("Location: ".$ci->agent->referrer(), TRUE, 302);
		exit;
	}

	print "<SCRIPT>location.href = '".base_url()."$url';</SCRIPT>";
	//$ci->load->view('layout/redirect',array('url'=>$url));
	//redirect($url);
}

/* End file redirect_to_pi.php */
