<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

function loadCourse()
{
	if ( ! isset($GLOBALS['course']) )
		$GLOBALS['course'] = new Course(COURSENAME);
}
