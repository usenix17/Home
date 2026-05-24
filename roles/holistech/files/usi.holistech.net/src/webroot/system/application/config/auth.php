<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
// Location of the login page
$config['login_page'] = '/';

// Whether to check the IP address
$config['check_ip'] = TRUE;

// Time in seconds a session expires (0 for never)
$config['timeout'] = 0;

// Pages/controllers that don't require authentication
$config['allow_pages'] = array( '/', '/users/show_login', '/users/do_login',
	'/users/cas_login', '/users/cas_logout', '/users/logout', '/users/check_auth' );
$config['allow_pages_no_session'] = array( );
$config['allow_controllers'] = array( 'error' );
$config['allow_controllers_no_session'] = array( 'layout', 'purchase', 'images', 'postback' );
