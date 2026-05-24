<?
//////////////////////////////////////////////////////////////////////
//
//	edit.php
//	Jason Karcz
//	AJAX Site Editor 
//
//////////////////////////////////////////////////////////////////////
//
//	3 September 2008 - Created
//
//////////////////////////////////////////////////////////////////////

// Create a new instance to get the ball rolling
new edit();

class edit extends ControlPanelApplet
{
	// Instance Variables
	var $title     = array( 
		'su'       => array( 'EN' => 'Edit Site',	 'ES' => 'Edit Site',		'ZH' => 'Edit Site' ),
		'designer'       => array( 'EN' => 'Edit Site',	 'ES' => 'Edit Site',		'ZH' => 'Edit Site' ),
		'admin'       => array( 'EN' => 'Edit Site',	 'ES' => 'Edit Site',		'ZH' => 'Edit Site' )
		      	      );
	var $name      = 'ajax';
	var $userLevel = 'admin';

	function display( $stage )
	{
		if ( !$GLOBALS['user']->hasType( $this->userLevel ) )
		{
			error( "You do not have permission to use this function (nice try, though.)" );
			return;
		}

		$editor = file_get_contents( "../lib/admin/editor.html" );	
		print $editor;
		exit();
	}
}
?>
