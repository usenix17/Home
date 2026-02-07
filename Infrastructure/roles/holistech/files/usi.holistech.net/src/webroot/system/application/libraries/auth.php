<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * Authentication Library
 *
 * @author Jason Karcz
 */
class Auth
{
	var $CI;
	var $config;
	var $user_id = NULL;
	var $allowAll = FALSE;

	function Auth($config)
	{
		$this->config = $config;
		$this->CI =& get_instance();
	}

	/**
	 * Logs a user in
	 *
	 * @access	public
	 * @param	key
	 * @param	password
	 */
	function login($key=NULL, $password=NULL)
	{
		$this->CI->errors->clear_all();

		if ( $this->allowAll ) return TRUE;

        if ( $key === NULL && $password === NULL )
        {
        /* Perform phpCAS authentication */
            require_once(BASEPATH.'application/libraries/phpCAS/CAS.php');

            $_SERVER['SERVER_PORT'] = 443;
            phpCAS::client( SAML_VERSION_1_1, $GLOBALS['course']->cas_host, (int) $GLOBALS['course']->cas_port, $GLOBALS['course']->cas_context);
            phpCAS::setNoCasServerValidation();
            phpCAS::forceAuthentication();
            $cas_username = strtolower(phpCAS::getUser());
            $cas_email = NULL;
            try {
                $cas_email = phpCAS::getAttributes()['emailAddress'];
            } catch (Exception $e) {
            }
            $_SERVER['SERVER_PORT'] = 80;
        
        /* Get the user object */
            $user = getUser('username', 'CAS::'.$cas_username);
            if ( !$user->exists() ) {
                $_POST['realName'] = $cas_username;
                $_POST['email'] = $cas_email === NULL ? str_replace('%u',$cas_username,$GLOBALS['course']->cas_email_template) : $cas_email;
                $user->save_from_post(TRUE);
            }
            $user = getUser('username', 'CAS::'.$cas_username);
        } 
        else 
        {
        /* Get the user object */
            $user = getUser($GLOBALS['course']->login_type, $key);
        }

	/* Check to see if the user's disabled */
		if ( is_object($user) && $user->disable )
		{
			fatal( 'This account has been disabled.  Please contact your supervisor for further assistance.' );
			return false;
		}

	/* Make sure the site isn't shutting down */
		if ( $GLOBALS['shutDownLevel'] >= 2 && !$this->has(new Token('auth','ignore_shutdown')) )
		{
			fatal( $GLOBALS['shutDownMessage'.$GLOBALS['shutDownLevel']] );
			return false;
		}

	/* If the user exists and has provided a valid password */
		if ( is_object($user) && $user->exists() && ( $GLOBALS['course']->useCAS || $user->validatePasswd($password) ) )
		{
			// Check to see if they're expired
			//if ( $user->expired() )
			//{
			//	fatal( 'This account has expired.  Please contact your supervisor for further assistance.' );
			//	return false;
			//}

			// Check to see if they're logging in to the right course
			//if ( ! $user->is_enrolled(COURSENAME) )
			//{
			//	warn( "You are not enrolled in this course." );
			//}

			// All systems are go...log the user in
			
			$user->lastRequest = $_SERVER['QUERY_STRING'];

			// Log login
			$log = array(	'course'	=> COURSENAME
						,	'lang'		=> $this->CI->input->post('lang')
						,	'user_id'	=> $user->user_id
						,	'ip'		=> getenv('REMOTE_ADDR')
						,	'browser'	=> getenv('HTTP_USER_AGENT')
						,	'width'		=> $this->CI->input->post('width')
						,	'height'	=> $this->CI->input->post('height')
						);
			$GLOBALS['db']->save_row( 'login_log', $log );

			$data = array(
				'user_id' => $user->user_id,
				'time' => time(),
				'ip' => $this->CI->input->ip_address()
			);

			$this->CI->session->set_userdata($data);
			header('update-utility: true');

			// Set the user as the globally logged-in user
			$GLOBALS['user'] =& $user;

			return TRUE;
		}

	/* This login has failed. */

		$this->logout();
		fatal('Login Failed - Invalid username or password.');
	}
    

	/**
	 * Logs the user out
	 *
	 * @access	public
	 */
	function logout()
	{
		$this->CI->errors->clear_all();
		$this->CI->session->sess_destroy();
		setcookie("PHPSESSID","",time()-3600,"/"); // delete session cookie  
	}

    /**
     * Logs out of a CAS system
     */
    function cas_logout()
    {
	$this->logout();
        require_once(BASEPATH.'application/libraries/phpCAS/CAS.php');

        phpCAS::client( CAS_VERSION_2_0, $GLOBALS['course']->cas_host, (int) $GLOBALS['course']->cas_port, $GLOBALS['course']->cas_context);
        phpCAS::logoutWithUrl(base_url());
    }

	/**
	 * Authenticates a user
	 *
	 * @access	public
	 * @param	$return - Whether to return a value, or exit on failure
	 */
	function authenticate( $return = FALSE, $ignore_no_session = FALSE, $ignore_allowed_pages = FALSE )
	{
		$message = NULL;
	/* Set the language */
	//	define('LANG', 'EN' );

		if ( $this->allowAll ) return TRUE;

	/* Initialize flags */
		$authenticated = TRUE;
		$logged_in = TRUE;
		$redirect = NULL;

	/* Test if the page is allowed without starting the session */
		if ( ! ( $ignore_no_session || $ignore_allowed_pages ) )
		if ( in_array('/'.$this->CI->uri->uri_string(), $this->config['allow_pages_no_session']) 
		       || in_array($this->CI->uri->segment(1), $this->config['allow_controllers_no_session']) )
		{
			return TRUE;
		}

	/* Start the session and look for user_id */
		$this->CI->load->library('session');
		$user_id = $this->CI->session->userdata('user_id');
		//debug($this->CI->session,TRUE);

	/* Test if the page is allowed */
		if ( !$ignore_allowed_pages && 
		       (  in_array('/'.$this->CI->uri->uri_string(), $this->config['allow_pages']) 
		       || in_array($this->CI->uri->segment(1), $this->config['allow_controllers']) )
		   )
		{
			$authenticated = TRUE;
			$logged_in = TRUE;
		}

	/* Test if the user is even logged in */
		elseif ( $user_id === FALSE )
		{
			// The user is not logged in at all
			$authenticated = FALSE;
			$logged_in = FALSE;
			//$message = 'You must be logged in to view this page.';
			$redirect = $this->config['login_page'];
		}

	/* Test for IP change */
		elseif ( ! $GLOBALS['course']->roamingIP 
				&& $this->CI->session->userdata('ip') != $this->CI->input->ip_address() )
		{
			// The user's IP address has changed
			$authenticated = FALSE;
			$logged_in = FALSE;
			$message = 'Your IP address has changed, and you have been logged out. ';
			$redirect = $this->config['login_page'];
		}

	/* Test for timeout */
		elseif ( $this->config['timeout'] > 0 && 
			time() - $this->CI->session->userdata('time') > $this->config['timeout'] )
		{
			// The user's session has expired
			$authenticated = FALSE;
			$logged_in = FALSE;
			$message = 'Your session has expired.  Please log in again.';
			$redirect = $this->config['login_page'];
		}

	/* All tests are done...let's look at the outcome */
		if ( ! $authenticated )
		{
			if ( $message !== NULL )
				error( $message );
			if ( $return )
				return FALSE;
			$this->CI->errors->to_headers();
			print "<SCRIPT>pager.update_utility(); pager.unpaged_show('/users/show_login')</SCRIPT>";
			$this->logout();
			exit;
		}

	/* Set the logged-in parameters */
		$this->CI->session->set_userdata('time', time());

		if ( $user_id !== FALSE )
			$GLOBALS['user'] = getUser('user_id',$user_id);

		return TRUE;
	}

	/**
	 * Returns currently logged in user's real name and title
	 *
	 * @access	public
	 * @return	string
	 */
	function from_line()
	{
		$id = $this->user_id();
		$out = $this->CI->person_model->real_name($id);
		$title = $this->CI->person_model->title($id);
		
		if ( $title )
			$out .= ', '.$title;

		return $out.', NAU Exploritas';
	}

	/**
	 * Returns currently logged in user's real name
	 *
	 * @access	public
	 * @return	string
	 */
	function real_name()
	{
		$user_id = $this->CI->session->userdata('user_id');
		return $this->CI->person_model->real_name($user_id);
	}

	/**
	 * Returns currently logged in user's user_id
	 *
	 * @access	public
	 * @return	string
	 */
	function user_id()
	{
		return $this->CI->session->userdata('user_id');
	}
}
