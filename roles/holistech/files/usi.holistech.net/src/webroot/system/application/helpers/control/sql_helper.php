<?
//////////////////////////////////////////////////////////////////////
//
//	sql.php
//	Jason Karcz
//	Raw sql execution 
//
//////////////////////////////////////////////////////////////////////
//
//	19 July 2004 - Created
//
//////////////////////////////////////////////////////////////////////

// Create a new instance to get the ball rolling
new sql();

class sql extends ControlPanelApplet
{
	// Instance Variables
	var $title     = array( 'su' => array( 'EN' => 'Execute SQL', 'ES' => 'Execute SQL', 'ZH' => 'Execute SQL' ) );
	var $name      = 'sql';
	var $userLevel = 'su';
	var $submit = 'Execute';

	function display( $stage )
	{
		$form = $this->readForm();

		$sqlForm = array( array( 'name'	=> 'sql'
			   , 'text'	=> 'SQL'
			   , 'su'	=> array( 'type' => 'textarea' )
			   , 'admin'	=> array( 'type' => 'none' )
			   , 'current'	=> stripslashes( $form['sql'] )
			   )
			);
		
		$this->stage = 'execute';
		$out = $this->makeForm( $sqlForm );

		if ( $stage == 'execute' )
		{
			$out .= "[PRE]<PRE>" . print_r( $GLOBALS['db']->query( stripslashes( $form['sql'] ) ), 1 ) . "</PRE>[/PRE]";
		}
		
		return $out;
	}
}
?>
