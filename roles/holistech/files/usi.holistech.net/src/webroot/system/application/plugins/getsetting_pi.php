<?
//////////////////////////////////////////////////////////////////////
//
//      getSetting.php
//      Jason Karcz
//      Grabs the desired setting from GET, POST, SESSION, or default
//
//////////////////////////////////////////////////////////////////////
//
//      16 October 2003 - Created
//
//////////////////////////////////////////////////////////////////////

function getSetting ( $name, $default = "" )
{
	// First priority is GET
	if ( isset($_GET[$name]) )
		$GLOBALS[$name] = $_GET[$name];
		
	// Next in line is POST
	elseif ( isset($_POST[$name]) )
		$GLOBALS[$name] = $_POST[$name];
		
	// Then we try SESSION
	elseif ( isset($_SESSION[$name]) )
		$GLOBALS[$name] = $_SESSION[$name];
		
	// Finally, it's not already set, so use the supplied default
	else
		$GLOBALS[$name] = $default;

	// Last, set the session variable
	$_SESSION[$name] = $GLOBALS[$name];
}

function setSetting( $name, $value )
{
	$GLOBALS[$name] = $value;
	$_SESSION[$name] = $GLOBALS[$name];
}
?>
