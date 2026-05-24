<?
//////////////////////////////////////////////////////////////////////
//
//	controlCodes.php
//	Jason Karcz
//	Code management control panel applet 
//
//////////////////////////////////////////////////////////////////////
//
//	19 July 2004 - Created
//
//////////////////////////////////////////////////////////////////////

// Create a new instance to get the ball rolling
require_once('../lib/code.php');
new controlCodes();

class controlCodes extends ControlPanelApplet
{
	// Instance Variables
	var $title     = array( 
				'admin'       => array( 'EN' => 'Manage Codes',	 'ES' => 'Manejar codigos',		'ZH' => '管理密码' ),
				'designer'       => array( 'EN' => 'Manage Codes',	 'ES' => 'Manejar codigos',		'ZH' => '管理密码' ),
				'su'       => array( 'EN' => 'Manage Codes',	 'ES' => 'Manejar codigos',		'ZH' => '管理密码' )
		      	      );
	var $name      = 'codes';
	var $userLevel = 'admin';

	function display( $stage )
	{
		if ( !$GLOBALS['user']->hasType( $this->userLevel ) )
		{
			error( "You do not have permission to use this function (nice try, though.)" );
			return;
		}


		if ( $stage == 'print' )
		{
			print '<HTML><BODY ONLOAD="print(); history.back();">' . unstore( $_SESSION['data'] ) . '</BODY></HTML>';
			exit;
		}
		elseif ( $stage == 'csv' )
		{
			header("Content-Disposition: attachment; filename=report.csv");
			header( "Content-type: text/comma-separated-values" );
			print unstore( $_SESSION['csv'] );
			exit;
		}		
		elseif ( $stage == '' )
		{
			$this->submit = '';
			
			return <<<FIN
<UL>
	<LI><P><A HREF="?action=control&applet=codes&stage=available">View Available Codes</A></P></LI>
	<LI><P><A HREF="?action=control&applet=codes&stage=generate">Generate New Codes</A></P></LI>
</UL>
FIN;
			
		}
		elseif ( $stage == 'available' )
		{
			$code = new Code();

			$codes = $code->availableCodes();

			$out = "<TABLE ALIGN=CENTER BORDER=1 CELLPADDING=5 WIDTH='100%'><TR>"
			//	. "<TH>Code</TH><TH>Type</TH><TH>Course</TH>"
			//	. "<TH>Code</TH><TH>Type</TH><TH>Course</TH>"
				. "<TH>Code</TH><TH>Type</TH><TH>Course</TH><TH>Label</TH><TH>Created By</TH>";
			
			$this->csv = "Code,Type,Course,Label\n";

			$i = 0;
			foreach ( $codes as $row )
			{
			//	if ( $i % 3 == 0 )
					$out .= "</TR><TR>";
					
				$out .= "<TD ALIGN=CENTER>{$row['code']}</TD><TD ALIGN=CENTER>{$row['type']}</TD><TD ALIGN=CENTER>{$row['course']}</TD><TD ALIGN=CENTER>{$row['label']}&nbsp;</TD><TD ALIGN=CENTER>{$row['createdBy']}&nbsp;</TD>";
				
				$this->csv .= "'{$row['code']}',{$row['type']},{$row['course']},{$row['label']}\n";
				
				$i++;	
			}
			
			$_SESSION['data'] = store( $out );
			$_SESSION['csv'] = store( $this->csv );
		
			return "<P ALIGN=RIGHT><A HREF='?action=control&applet=codes&stage=print'>Print</A> " . ( $this->csv ? "<A HREF='?action=control&applet=codes&stage=csv'>CSV</A>" : "" ) . "</P><P>There are $i available codes:</P>" . $out . '</TABLE>';

		}
		else
		{
			$this->object = new User();
				
			$this->object->courseName = $GLOBALS['user']->courseName;

			// This for is used to edit the details of the user
			$detailForm = array( array( 'name'	=> 'qty'
					   	  , 'text'	=> 'Quantity'
						  , 'current' => 1
						  , 'admin'	=> array( 'type' => 'float' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'label'
					   	  , 'text'	=> 'Label'
						  , 'admin'	=> array( 'type' => 'text' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'courseName'
					   	  , 'text'	=> 'Course'
						  , 'su'	=> $this->courseArray()
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'multiCourse'
					   	  , 'text'	=> 'MultiCourse List'
						  , 'su'	=> array( 'type' => 'textarea' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )						  
					   , array( 'name'	=> 'classification'
					   	  , 'text'	=> 'Classification'
						  , 'su'	=> $this->classificationArray( 'su' )
						  , 'admin'	=> $this->classificationArray( 'admin' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'level'
					   	  , 'text'	=> 'Level'
						  , 'super'	=> array( 'type' => 'float' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'type'
					   	  , 'text'	=> 'Type'
						  , 'current' => 'user'
						  , 'su'	=> array( 'type' => 'select'
						  			, 'values' => array( 'user' => 'User'
											   , 'super' => 'Supervisor'
											   , 'admin' => 'Administrator'
											   , 'designer' => 'Designer'
											   , 'su' => 'Superuser'
											   )
									)
						  , 'designer'	=> array( 'type' => 'select'
						  			, 'values' => array( 'user' => 'User'
											   , 'super' => 'Supervisor'
											   , 'admin' => 'Administrator'
											   , 'designer' => 'Designer'
											   )
									)
						  , 'admin'	=> array( 'type' => 'select'
						  			, 'values' => array( 'user' => 'User'
											   , 'super' => 'Supervisor'
											   , 'admin' => 'Administrator' 
										   	   )
									)
						  , 'super'	=> array( 'type' => 'select'
						  			, 'values' => array( 'user' => 'User'
											   , 'super' => 'Supervisor'
										   	   )
									)
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   );
			
			// Show the form
			$this->stage = 'apply';
			$this->submit = 'Apply';
			return $this->makeForm( $detailForm );
		}	
	}

	function apply()
	{
		$form = $this->readForm();
		
		$qty = $form['qty'];
		unset( $form['qty'] );
		
		$label = $form['label'];
		unset( $form['label'] );
		
		parent::apply();

		$code = new Code();
		$codes = $code->create( $this->object, $qty, $label );
		
		foreach ( $codes as $row )
		{
			$out .= $row['code'] . '<BR>';
		}

		return "<P>$qty code" . ( $qty > 1 ? 's' : '' ) . " created:</P><P>$out</P>";

		$this->submit = "OK";
	}
		
	function courseArray()
	{
		// Initialize the array
		$values = array();

		$db = $GLOBALS['db']->query ( "SELECT name FROM courses;" );
		
		foreach( $db as $row )
		{
			$values[ $row['name'] ] = $row['name'];
		}

		return array( 'type' => 'select', 'values' => $values );
	}

	function classificationArray( $level )
	{
		// Initialize the array
		$values = array();

		if ( $level == 'admin' )
		{
			$course = $GLOBALS['user']->course->name;
			$moduleSet = $GLOBALS['user']->course->moduleSets;

			// Let there be a blank value
			$values[''] = '';

			if ( $moduleSet )
			foreach ( $moduleSet as $class => $data )
			{
				if ( $class != '' )
				$values[ $class ] = $class;
			}
		}
		elseif ( $level == 'su' )
		{
			$db = $GLOBALS['db']->query ( "SELECT name FROM courses;" );
			
			// Let there be a blank value
			$values[''] = '';

			foreach( $db as $row )
			{
				$course = new Course( $row['name'] );
				$moduleSet = $course->moduleSets;
				
				if ( $moduleSet )
				foreach ( $moduleSet as $class => $data )
				{
					if ( $class != '' )
					$values[ $class ] = $row['name'] . "\t->\t" . $class;
				}
			}
		}
				
		return array( 'type' => 'select', 'values' => $values );
	}			
}
?>
