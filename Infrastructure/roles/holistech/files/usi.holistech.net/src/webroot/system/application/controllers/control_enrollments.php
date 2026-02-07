<?php

/**
 * Enrollments Control Panel
 */
class Control_Enrollments extends Controller
{

	function Control_Enrollments()
	{
		parent::Controller();

		$this->load->library('session');
		$this->session->set_userdata('control_selected','control_enrollments');
		$this->load->helper('formtable');
	}

	function index()
	{
		$user_id = $this->session->userdata('control_selected_user');
		if ( $user_id === FALSE )
			$user_id = $GLOBALS['user']->user_id;

		$user = getUser('user_id',$user_id);

	/* Calculate completion percentages and certification status for enrollments */
		foreach ( array_keys($user->enrollments) as $key ) {
			$user->enrollments[$key]['can_certify'] = $user->can_certify($key);
			$user->enrollments[$key]['has_certified'] = $user->has_certified($key);
			$user->enrollments[$key]['percent_complete'] = $user->percent_complete($key);
		}
		$this->load->view('control/enrollments/list',array(
			'enrollments' => $user->enrollments,
		));	
	}
}

