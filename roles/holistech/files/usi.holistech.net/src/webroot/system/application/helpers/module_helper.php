<?
//////////////////////////////////////////////////////////////////////
//
//      module.php
//      Jason Karcz
//      Module handling class
//
//////////////////////////////////////////////////////////////////////
//
//      16 October 2003 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading module.php');
class Module
{
	var $name;		// Name of the module
	var $courseName;	// Name of the course the module belongs to
	var $module_id;		// ID of the module
	var $type;		// Type of the module from the syllabus

	var $ci;

	function Module($module_id)
	{
		/*if ( preg_match('/\D/', $module_id) )
		{
			fatal("'$module_id' is not a valid module id.");
		}*/

		$this->module_id = $module_id;
		$this->ci =& get_instance();

	/* Get the module information from the DB 
		$result = $this->ci->db
			->from('modules')
			->where('id',$module_id)
			->get()
			->result_array();

		$module = $result[0];*/
		
		list($course,$name) = explode(':',$module_id);

		$this->name = $name;
		$this->courseName = $course;
		//$this->name = $module['module'];
		//$this->courseName = $module['course'];
		//$this->type = $module['type'];
	}

	function number()
	{
		if ( isset($GLOBALS['course']->syllabus[$this->module_id]) ) 
			return $GLOBALS['course']->syllabus[$this->module_id]['number'];
		else
			return NULL;
	}
}
?>
