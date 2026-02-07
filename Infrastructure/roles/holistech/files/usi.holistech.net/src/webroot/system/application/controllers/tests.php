<?php

/**
 * Tests Controller
 *
 * Handles interactions with tests.
 */
class Tests extends Controller
{
	function load($testName,$page)
	{
		if( ! $GLOBALS['user']->hasPermission($testName) )
		{
			$this->load->view('forbidden',array(
				'module' => $testName,
				'page' => $page
			));;
		}
		else
		{
			$this->load->helper('test');
			$test = new Test($testName);
			$this->load->view('content',array(
				'content' => $test->display(),
			));
		}
	}
		
	function grade($module_id)
	{
		$this->load->helper('test');

		$test = new Test($module_id);

		$this->output->set_output(filterTags($test->grade(),'PAGE',$this->input->post('lang')));
	}

	function edit($module_id)
	{
		$test = getModule($module_id);

		if ( ! $GLOBALS['user']->has(new Token('edit','content',$test->courseName)) )
			fatal('You are not allowed to edit course content.');

		$this->load->view('/tests/edit',array(
			'test' => $test,
			'module_id' => $module_id,
		));
	}

	function save($module_id)
	{
		$test = getModule($module_id);

		if ( ! $GLOBALS['user']->has(new Token('edit','content',$test->courseName)) )
			fatal('You are not allowed to edit course content.');

		$test->read_POST();
		$test->write_XML();
		$test->read_XML();
		$this->output->set_output($test->getPage(0));
	}
}	
