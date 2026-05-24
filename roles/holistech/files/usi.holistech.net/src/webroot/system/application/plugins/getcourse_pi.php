<?
//////////////////////////////////////////////////////////////////////
//
//      getCourse.php
//      Jason Karcz
//
//////////////////////////////////////////////////////////////////////
//
//      29 Jun 2010 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading getCourse.php');

// Initialize module cache
$GLOBALS['courseCache'] = array();

function &getCourse( $coursename )
{
	debug_log("getCourse($coursename)");

	if ( !isset($GLOBALS['courseCache'][$coursename]) )
	{
		debug_log("Writing user to cache");
		$GLOBALS['courseCache'][$coursename] = new Course($coursename);
	}

	return $GLOBALS['courseCache'][$coursename];
}
