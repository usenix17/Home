<?php

/**
 * Pager Controller
 */
class Pager extends Controller
{
	function load($module_id,$page)
	{
		$module = getModule($module_id);

		if( ! $GLOBALS['user']->hasPermission($module->name) )
		{
			$this->load->view('forbidden',array(
				'module' => $module->name,
				'page' => $page,
				'requirement' => $GLOBALS['course']->getRequirementTitle($module_id),
			));
		}
		else
		{
			if ( $module->type == 'dbForm' )
			{
				$module->_set_post();

				$this->load->view($module->view_path,array(
					'form' => $module,
					'data' => $module->result,
					'name' => $module,
				));
			}
			else
			{
				$this->output->set_output($module->getPage($page));

				//$this->load->view('content',array(
				//	'content' => $module->getPage($page),
				//));
			}
		}
	}

	function saveSession($module,$page)
	{
		$GLOBALS['user']->saveSession($module,$page);
	}

}
