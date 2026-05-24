<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * User Error and Message Library
 *
 * @author Jason Karcz
 */
class Errors
{
	var $CI;
	var $errors = array();
	var $messages = array();
	var $debug = array();
	var $debug_log = '';
	var $disable_storage = FALSE;
	var $starttime;
	var $endtime;
	var $echo = TRUE;

	function Errors()
	{
		$this->CI =& get_instance();
		//$this->CI->load->library('session');
		//$errors = $this->CI->session->userdata('errors');
		//if ( is_array($errors) )
		//{
		//	$this->errors = $errors;
		//	$this->debug('Read errors: '.print_r($this->errors,TRUE));
		//}
		//$messages = $this->CI->session->userdata('messages');
		//if ( is_array($messages) )
		//{
		//	$this->messages = $messages;
		//}
		//$debug = $this->CI->session->userdata('debug');
		//if ( is_array($debug) )
		//{
		//	$this->debug = $debug;
		//}

		$this->starttime = time();
		$this->endtime = time();

		// Set echo to FALSE if this request was made via
		// XMLHttpRequest
		$headers = getallheaders();
		if ( isset($headers['X-Requested-With']) &&
			$headers['X-Requested-With'] == 'XMLHttpRequest' )
			$this->echo = FALSE;
	}

	/**
	 * Registers a fatal error
	 *
	 * @access	public
	 * @param	$message
	 * @param	$redirect
	 */
	function fatal($message, $redirect = NULL)
	{
		$this->error($message);
		$this->to_headers();
		if ( $redirect !== NULL )
			redirect_to($redirect);

		//print $this->CI->load->view('error', NULL, TRUE);
		exit;
	}

	/**
	 * Registers an error
	 *
	 * @access	public
	 * @param	$message
	 */
	function error($message)
	{
		$back = $this->get_backtrace();
		$line = $back['line'];
		$file = $back['file'];

		$this->log($message,'error',$file,$line);
		
		$this->store($message,'errors');
		//print 'ERROR: '.$message . $back['backtrace'];
		$this->store('ERROR: '.$message . $back['backtrace'],'debug');
		$this->store_errors();

		if ( $this->echo )
			echo 'ERROR: '.$message . $back['backtrace']."<BR>\n";
	}

	/**
	 * Registers a message
	 *
	 * @access	public
	 * @param	$message
	 */
	function warn($message)
	{
		$back = $this->get_backtrace();
		$line = $back['line'];
		$file = $back['file'];

		$this->log($message,'warning',$file,$line);

		$this->store($message,'messages');
		$this->store_errors();

		if ( $this->echo )
			echo 'WARN: '.$message . $back['backtrace']."<BR>\n";
	}

	/**
	 * For debugging 
	 *
	 * @access	public
	 * @param	$message
	 */
	function debug($text,$varDump=FALSE)
	{
		if ( $varDump )
		{
			ob_start();
			var_dump($text);
			$text = ob_get_contents();
			ob_end_clean();
		}
		else
			if ( is_array( $text ) || is_object( $text ) )
				$text = print_r( $text, 1 );

		$back = $this->get_backtrace();

		$this->store($text . $back['backtrace'],'debug');
		//$this->store_errors();

		if ( $this->echo )
			echo 'DEBUG: '.$text . $back['backtrace']."<BR>\n";
	}

	function debug_log($text,$varDump=FALSE)
	{
		if ( LOG_LEVEL < 1 )
			return;

		if ( $varDump )
		{
			ob_start();
			var_dump($text);
			$text = ob_get_contents();
			ob_end_clean();
		}

		$back = $this->get_backtrace();
		$line = $back['line'];
		$file = $back['file'];

		$timeparts = explode(' ',microtime());
		$endtime = $timeparts[1].substr($timeparts[0],1);
		$time = sprintf( "%.6f", $endtime - $this->starttime );
		$elapsed = sprintf( "%.6f", $endtime - $this->endtime );
		$this->endtime=$endtime;
		$text = number_format(memory_get_usage()) . ' - '.$time.' - '.$elapsed.' - '.$text;
		if ( $elapsed > 0.01 )
			$text = "<SPAN STYLE='color: #A00; font-size: 14pt'>$text</SPAN>";
		//else
		//	return;

		if ( LOG_LEVEL > 1 )
			print( $text. " ($file:$line)<BR>");
		else
			$this->debug_log .= $text. " ($file:$line)<BR>";
	}

	/**
	 * Disables(/enables) session storage of errors
	 *
	 * @access	public
	 *
	 * @param	bool	$disable
	 */
	function disable_storage($disable=TRUE)
	{
		$this->disable_storage = $disable;
	}

	/**
	 * Returns all registered errors as a DIV
	 *
	 * @access	public
	 * @return	array
	 */
	function store_errors()
	{
		if ( $this->disable_storage )
			return;

		//$store = array(
		//	'errors' => $this->errors,
		//	'messages' => $this->messages,
		//);

		//debug('Storing errors: '.print_r($store,TRUE));
		//$this->CI->session->set_userdata($store);
		//$this->CI->session->set_userdata('debug', $this->debug);
	}

	/**
	 * Sends current messages via JSON headers
	 */
	function to_headers()
	{
		$this->remove_duplicates();

		$json = array(
			'errors' => $this->errors,
			'messages' => $this->messages,
			'debug' => $this->debug,
		);

		header('errors-warnings: '.json_encode($json));

		//$this->CI->session->unset_userdata('debug');
	}

	function store($message,$type)
	{
		$uniq = uniqid();
	       	$this->{$type}[$uniq] = array('id'=>$uniq,'msg'=>$message);
	}

	function clear_all()
	{
		header('clear-errors: true');

		//$this->errors = array();
		//$this->messages = array();
		//$this->store_errors();
	}

	function get_backtrace()
	{
		$back = debug_backtrace();
		array_shift($back);
		array_shift($back);
		//array_shift($back);
		$backtrace = '';
		$file = '';
		$line = '';
		foreach ( $back as $row )
		{
			if ( !isset($row['file']) )
				continue;

			if ( empty($file) )
				$file = preg_replace( "/.*\/([^\/]*)$/", '\1', $row['file'] );
			if ( empty($line) )
				$line = $row['line'];

			$backtrace .= ' - ('.preg_replace( "/.*\/([^\/]*)$/", '\1', $row['file'] ).':'.$row['line'].')';
		}

		return compact('backtrace','file','line');
	}

	function remove_duplicates()
	{
		foreach ( array('errors','messages') as $type )
			foreach ( $this->$type as $i )
				foreach ( $this->$type as $j )
					if ( $i['msg'] == $j['msg'] && $i['id'] != $j['id'] )
					{
						//print "Duplicate: {$i['id']} = {$j['id']}";
						unset($this->{$type}[$i['id']]);	
					}
		$this->store_errors();
	}

	function log($message,$type,$file,$line)
	{
		$user = NULL;
		if ( isset($GLOBALS['user']) )
			$user = $GLOBALS['user']->username;

		$request = $_REQUEST;
		unset($request['password']);
		unset($request['password_verify']);
		
		// Log error
		$log = array(	'type'		=> $type
					,	'course'	=> COURSENAME
					,	'message'	=> $message
					,	'file'		=> $file
					,	'line'		=> $line
					,	'user'		=> $user
					,	'ip'		=> getenv('REMOTE_ADDR')
					,	'browser'	=> getenv('HTTP_USER_AGENT')
					,	'request'	=> print_r( $request, 1 )
					);
		$GLOBALS['db']->save_row( 'errors_log', $log );		
	}
}
