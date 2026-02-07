<?php

/**
 * Control_Codes Controller
 */
class Control_Codes extends Controller
{
	function Control_Codes()
	{
		parent::Controller();

		$this->load->helper('codes');
	}

	function index()
	{
		if ( $GLOBALS['user']->has(new Token('auth','create_codes',COURSENAME)) )
			$this->load->view('/control/codes/create');

		$this->load->view('/control/codes/show_codes_wrapper');
		$this->load->view('/control/codes/load_codes');
	}

	function show_codes()
	{
		$this->load->view('/control/codes/show_codes',array(
			'codes' => Codes::list_codes(),
		));
	}

	function create($quantity,$label='')
	{
		$new_codes = Codes::create($quantity,$label);

		$this->load->view('/control/codes/show_codes',array(
			'new_codes' => $new_codes,
			'codes' => Codes::list_codes(),
		));
	}
}
