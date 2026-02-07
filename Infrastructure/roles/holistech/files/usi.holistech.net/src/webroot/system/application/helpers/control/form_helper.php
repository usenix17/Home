<?
//////////////////////////////////////////////////////////////////////
//
//	controlForm.php
//	Jason Karcz
//	Form number lookup applet 
//
//////////////////////////////////////////////////////////////////////
//
//	19 July 2004 - Created
//
//////////////////////////////////////////////////////////////////////

// Create a new instance to get the ball rolling
new controlForm();

class controlForm extends ControlPanelApplet
{
	// Instance Variables
	var $title     = array( 'su'       => array( 'EN' => 'Look Up Form Number',	 'ES' => 'Look Up Form Number',	'ZH' => '查询表号码' )
				, 'designer'       => array( 'EN' => 'Look Up Form Number',	 'ES' => 'Look Up Form Number',	'ZH' => '查询表号码' )
				, 'admin'       => array( 'EN' => 'Look Up Form Number',	 'ES' => 'Look Up Form Number',	'ZH' => '查询表号码' )
		      	      );
	var $name      = 'form';
	var $userLevel = 'admin';

	function display( $stage )
	{
		if ( !$GLOBALS['user']->hasType( $this->userLevel ) )
		{
			error( "You do not have permission to use this function (nice try, though.)" );
			return;
		}


		$form = $this->readForm();

		$codeForm = array( array( 'name'	=> 'code'
			   , 'text'	=> 'Form Number'
			   , 'admin'	=> array( 'type' => 'text' )
			   )
			);
		
		$this->stage = 'execute';
		$out = $this->makeForm( $codeForm );

		if ( $stage == 'execute' )
		{
			$db = $GLOBALS['db']->query( 'SELECT realName FROM users;' );
			
			foreach ( $db as $row )
			{
				if( substr( sprintf( "%u", crc32( $row['realName'] ) ), 0, 4 ) == $form['code'] )
				{
					$out .= "<P>{$row['realName']}</P>";
				}
			}
		}
		
		return $out;
	}
}
?>