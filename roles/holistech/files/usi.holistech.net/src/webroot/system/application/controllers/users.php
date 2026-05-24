<?php

/**
 * Users Controller
 */
class Users extends Controller
{
	function show_login()
	{
		// Clear all errors
		//$this->errors->clear_all();

		// Unset purchasing userdata
		$this->load->library('session');
		$this->session->unset_userdata(array(
			'code' => '',
			'num_codes' => '',
			'myself' => '',
		));

		// Don't show the login page if the user's not authenticated
		if ( $this->check_auth() )
			return;

		$this->auth->logout();

		$this->load->helper('file');
		$this->load->helper('formtable');

	/* Grab each login page based on the course languages */

		$text = lang_files('login');

		$this->load->view('/users/login',array('text'=>$text));
	}	

	function check_auth()
	{
		if ( $this->auth->authenticate(TRUE,FALSE,TRUE) )
		{
			// Show the password reset screen if neede
			if ( !$GLOBALS['course']->useCAS && $GLOBALS['user']->empty_password() )
				$this->show_reset_password();
            elseif ( ! $GLOBALS['user']->is_enrolled(COURSENAME) ) {
                print "<SCRIPT>login.purchase();</SCRIPT>";
                exit;
			} else
				$this->init_pager();
			return TRUE;
		}

		return FALSE;

	}

    /**
     * cas_login()
     *
     * Emulates login except uses phpCAS to get an authenticated username
     */
    function cas_login()
    {
        $this->auth->login();

        print "<SCRIPT>window.location.href = '".base_url()."'</SCRIPT>";
        exit;
    }

    /**
     * cas_logout()
     *
     * Logs out of a CAS
     */
    function cas_logout()
    {
        $this->auth->cas_logout();
    }

	function do_login()
	{
		$this->auth->login($this->input->post($GLOBALS['course']->login_type),$this->input->post('password'));

		if ( $GLOBALS['user']->empty_password() )
			$this->show_reset_password();
		else {
			if ( ! $GLOBALS['user']->is_enrolled(COURSENAME) ) {
				if ( $GLOBALS['course']->openReg ) {
                    $GLOBALS['user']->enroll(COURSENAME);
                } else {
                    error( "You are not enrolled in this course." );
                    $this->errors->to_headers();
                    exit;
                }
			}
			$this->init_pager();
		}
	}

	function logout()
	{
		$this->auth->logout();

        $url = base_url();
        if ( $GLOBALS['course']->useCAS ) { $url .= "users/cas_logout"; }
		$this->load->view('users/logout',array('url' => $url));
	}

	function init_pager($parent=FALSE)
	{
		$this->auth->authenticate(FALSE,FALSE,TRUE);

		if ( $GLOBALS['user']->is_enrolled(COURSENAME) ) {
			$syllabus_JSON = $GLOBALS['course']->get_syllabus_JSON();
			$session = $GLOBALS['user']->getSession();
            $scope = $parent ? 'window.opener.' : '';
            $close = $parent ? 'window.close();' : '';
			$this->load->view('/users/init_pager',array(
				'modules' => $syllabus_JSON,
				'module_id' => $session['module'],
				'page' => $session['page'],
                'scope' => $scope,
                'close' => $close,
			));
		} else {
			exit;
			error( "You are not enrolled in this course." );
			$this->errors->to_headers();
			print "<SCRIPT>pager.update_utility(); pager.unpaged_show('/users/show_login')</SCRIPT>";
			$this->auth->logout();
			exit;
		}

	}
	
	function show_reset_password()
	{
		$this->output->set_output('<SCRIPT>pager.unpaged_show("/control_user/change_password");</SCRIPT>');
	}
}
