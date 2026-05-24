<?php

/**
 * Skills Controller
 */
class Skills extends Controller
{
	function finish($module_id)
	{
		$module = getModule($module_id);

		if ( $GLOBALS['user']->hasPermission($module->name) 
		&& strtoupper($GLOBALS['course']->syllabus[$module_id]['type']) == 'SKILL' )
			$GLOBALS['user']->enrollment_token()->issue(new Token('module_skill',$module->module_id,COURSENAME));
	}

	function edit($module_id,$page)
	{
		$skill = getModule($module_id);

		if ( ! $GLOBALS['user']->has(new Token('edit','content',$skill->courseName)) )
			fatal('You are not allowed to edit course content.');

		$this->load->view('/skills/edit',array(
			'skill' => $skill,
			'page' => $page,
			'module_id' => $module_id,
		));
	}

	function save($module_id,$page)
	{
		$skill = getModule($module_id);

		if ( ! $GLOBALS['user']->has(new Token('edit','content',$skill->courseName)) )
			fatal('You are not allowed to edit course content.');

		if ( $this->input->post('title') !== FALSE )
			$skill->pages[$page]['title'] = array_merge($skill->pages[$page]['title'],$this->input->post('title'));
		$skill->pages[$page]['content1'] = array_merge($skill->pages[$page]['content1'],$this->input->post('content1'));
		$skill->languages = array_keys($this->input->post('content1'));

		$skill->write_XML();
		$skill->read_XML();

		$this->output->set_output($skill->getPage($page));
	}
}
