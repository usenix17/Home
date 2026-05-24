<?php

/**
 * Control Panel Controller
 */
class Control extends Controller
{
	function Control()
	{
		parent::Controller();

		$this->load->view('control/close');
		$this->load->helper('controlpanelapplet');

		// Reset user and course selections
		$this->session->unset_userdata(array(
			'control_selected_course' => '',
			'control_selected_user' => ''
		));
	}

	function index()
	{
		$this->show_tabs();
	}

	function show_tabs()
	{
		$this->errors->clear_all();
		$selected = $this->session->userdata('control_selected');

		if ( $GLOBALS['user']->has(new Token('auth','tech_support',COURSENAME)) 
		  || $GLOBALS['user']->has(new Token('edit','user_information',COURSENAME))
		  || $GLOBALS['user']->has(new Token('edit','user_information')))
		{
			$tabs['Users'] = 'tech_support';
		}

		$tabs['Personal Info'] = 'control_user';
		$tabs['Enrollments'] = 'control_user/enrollments';

		if ( $GLOBALS['user']->has(new Token('edit','content',COURSENAME)) )
		{
			//$tabs['Course Syllabus'] = 'control_syllabus';
			$tabs['Course Settings'] = 'control_course';
			$tabs['Edit Syllabus'] = 'control_course/edit_syllabus';
		}

		if ( $GLOBALS['user']->has(new Token('auth','view_codes',COURSENAME)) && $GLOBALS['course']->useCodes )
		{
			$tabs['Registration Codes'] = 'control_codes';
		}
		if ( $GLOBALS['user']->has(new Token('auth','view_reports',COURSENAME)) )
		{
			$tabs['Reports'] = 'control_reports';
		}

		$this->load->view('control/tabs',array(
			'tabs' => $tabs,
			'selected' => $selected,
		));
	}
}
