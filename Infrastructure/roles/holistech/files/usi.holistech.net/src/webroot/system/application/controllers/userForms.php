<?php

/**
 * Skills Controller
 */
class UserForms extends Controller
{
	function save()
	{
		$this->load->helper('userform');
		$form = new UserForm($this->input->post('module_id'));

		$this->output->set_output($form->submit());
	}
}
