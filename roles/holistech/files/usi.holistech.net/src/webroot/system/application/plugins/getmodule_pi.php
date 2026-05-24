<?
//////////////////////////////////////////////////////////////////////
//
//      getModule.php
//      Jason Karcz
//
//////////////////////////////////////////////////////////////////////
//
//      16 October 2003 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading getModule.php');

// Initialize module cache
$GLOBALS['moduleCache'] = array();

function getModule( $module_id, $coursename = NULL )
// Gets the correct type of module for target
{
	$ci =& get_instance();
	$course = getCourse($coursename === NULL ? COURSENAME : $coursename);

	debug_log('getModule('.$module_id.')');

	// Return cached copy if exists
	if ( isset( $GLOBALS['moduleCache'][$module_id] ) )
		return $GLOBALS['moduleCache'][$module_id];

	if ( !isset($course->syllabus[$module_id]) )
	{
		fatal('The module with id '.$module_id.' is not part of this course.');
	}

	switch ( strtoupper($course->syllabus[$module_id]['type']) )
	{
		case "SKILL":
			$ci->load->helper('skill');
			$GLOBALS['moduleCache'][$module_id] = new Skill( $module_id );
			break;
		case "TEST":
			$ci->load->helper('test');
			$GLOBALS['moduleCache'][$module_id] = new Test( $module_id );
			break;
		case "INDEX":
			$ci->load->helper('modIndex');
			$GLOBALS['moduleCache'][$module_id] = new modIndex();
			break;
		case "DBFORM":
			$ci->load->helper('userForm');
			$GLOBALS['moduleCache'][$module_id] = new userForm( $module_id );
			break;
		case "SURVEY":
			$ci->load->helper('survey');
			$GLOBALS['moduleCache'][$module_id] = new Survey( $module_id );
			break;
		default:
			error( "Invalid module type: '$type'." );
			$module = "";
	}

	return $GLOBALS['moduleCache'][$module_id];
}

?>
