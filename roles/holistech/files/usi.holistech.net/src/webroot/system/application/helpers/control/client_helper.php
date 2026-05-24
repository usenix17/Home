<?
//////////////////////////////////////////////////////////////////////
//
//	controlCourse.php
//	Jason Karcz
//	User management control panel applet 
//
//////////////////////////////////////////////////////////////////////
//
//	19 July 2004 - Created
//
//////////////////////////////////////////////////////////////////////

// Create a new instance to get the ball rolling
new controlCourse();

class controlCourse extends ControlPanelApplet
{
	// Instance Variables
	var $title     = array( 'designer'	=> array( 'EN' => 'Update Course Profile',	 'ES' => 'Update Course Profile',	'ZH' => '更新客户档案 ' )
			      , 'su'    	=> array( 'EN' => 'Manage Courses',		 'ES' => 'Manejar coursees',	'ZH' => '客户管理' )
		      	      );
	var $name      = 'course';
	var $userLevel = 'designer';

	function display( $stage )
	{
		if ( !$GLOBALS['user']->hasType( $this->userLevel ) )
		{
			error( "You do not have permission to use this function (nice try, though.)" );
			return;
		}

		// Set the object to the current user (it will be selected in the user menu.)
		$this->object = $GLOBALS['user']->course;
		
		// Only display the chooser form if we're just entering into this applet AND the user is admin or higher.
		if ( $stage == '' && $GLOBALS['user']->hasSU() )
		{
			// This form is used to choose which user to edit
			$chooserForm = array( array( 'name'	=> 'name'
						   , 'text'	=> 'Course'
						   , 'su'	=> $this->courseNameArray()
					   	   )
					    , array( 'name'	=> 'newName'
						   , 'text'	=> 'New Course'
						   , 'user'	=> array( 'type' => 'text' )
						   )
					    , array( 'name'	=> 'copy'
						   , 'text'	=> 'Copy From'
						   , 'user'	=> array( 'type' => 'text' )
						   )
					    );

			$this->stage = 'detail';
			$this->submit = 'Edit';
			return '<P>Choose a user to edit, or enter a username to create a new user.</P>' 
				. $this->makeForm( $chooserForm );
		}
		elseif ( $stage == 'delete' )
		{
			// Read the form to get the object
			$form = $this->readForm();
			
			// Disable the submit button
			$this->submit = '';

			if ( $form['confirmdelete'] )
			{
				// Delete the object
				$this->object->delete();
				
				return "The course has been deleted.";
			}
			else
			{
				return "The confirmation box was not checked, so the course was not deleted.";
			}
		}
		else
		{
			// Read the form that's been submitted (null if just a user or admin)
			$form = $this->readForm();

			// Handle a new course
			if ( $form['newName'] ) 
			{
				$course = array( "name" => $form['newName'] );
				$form['name'] = $form['newName'];
				
				if ( $form['copy'] )
				{
					$course = $GLOBALS['db']->get_row( 'courses', 'name', $form['copy'] );
					$course['name'] = $form['newName'];
				}
				
				$GLOBALS['db']->save_row( "courses", $course );				
			}
			
			// Set the course that we're editing
			$this->object = $GLOBALS['user']->hasSU() ? new Course( $form['name'] ) : $GLOBALS['user']->course;

			// Find the population
			$this->object->population = $this->object->population();

			// This for is used to edit the details of the course
			$detailForm = array( array( 'name'	=> 'name'
						  , 'text'	=> 'Name'
						  , 'admin'	=> array( 'type' => 'text' )
						  , 'user'	=> array( 'type' => 'none' )
						  )
					   , array( 'name'	=> 'displayName'
					   	  , 'text'	=> 'Display Name'
						  , 'admin'	=> array( 'type' => 'textarea' )
					  	  )
					   , array( 'name'	=> 'email'
					   	  , 'text'	=> 'E-Mail Address'
						  , 'admin'	=> array( 'type' => 'email' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'minScore'
					   	  , 'text'	=> 'Minimum percentage to pass a test'
						  , 'admin'	=> array( 'type' => 'percent' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'useEbiz'
					   	  , 'text'	=> 'Use NAU EBusiness'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'lmid'
					   	  , 'text'	=> 'EBusiness LMID'
						  , 'admin'	=> array( 'type' => 'text' )
					  	  )
					   , array( 'name'	=> 'price'
					   	  , 'text'	=> 'EBusiness Price per Unit'
						  , 'admin'	=> array( 'type' => 'float' )
					  	  )
					   , array( 'name'	=> 'ebizURL'
					   	  , 'text'	=> 'EBusiness URL'
						  , 'admin'	=> array( 'type' => 'text' )
					  	  )
					   , array( 'name'	=> 'contactInfo'
					   	  , 'text'	=> 'Contact Information (for EBusiness)'
						  , 'admin'	=> array( 'type' => 'text' )
					  	  )
					   , array( 'name'	=> 'showResources'
					   	  , 'text'	=> 'Show Resources Menu'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'showEvents'
					   	  , 'text'	=> 'Show Events Menu'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'greyModules'
					   	  , 'text'	=> 'Show Future Modules (greyed out)'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'widePages'
					   	  , 'text'	=> 'Use wide pages in skills'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'roamingIP'
					   	  , 'text'	=> 'Allow Roaming IP'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'useCodes'
					   	  , 'text'	=> 'Use Login Codes'
						  , 'su'	=> array( 'type' => 'toggle' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'openReg'
					   	  , 'text'	=> 'Allow open registration'
						  , 'su'	=> array( 'type' => 'toggle' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'contentsOnFAQ'
					   	  , 'text'	=> 'Show contents on FAQ page'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'disableCorrectAnswers'
					   	  , 'text'	=> 'Disable questions after correct answer'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'supervisorAllUsers'
					   	  , 'text'	=> 'Allow supervisors to manage all users'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'expire'
					   	  , 'text'	=> 'Days after certification users expire'
						  , 'admin'	=> array( 'type' => 'int' )
					  	  )
					   , array( 'name'	=> 'bdayExpire'
					   	  , 'text'	=> 'Days after birthday users expire'
						  , 'admin'	=> array( 'type' => 'int' )
					  	  )
					   , array( 'name'	=> 'languages'
					   	  , 'text'	=> 'Available Languages'
						  , 'su'	=> array( 'type' => 'text' )
					  	  )
					   , array( 'name'	=> 'pdfCert'
					   	  , 'text'	=> 'Certificate in PDF format'
						  , 'admin'	=> array( 'type' => 'toggle' )
					  	  )
					   , array( 'name'	=> 'quota'
					   	  , 'text'	=> 'Quota'
						  , 'su'	=> array( 'type' => 'int' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'layout'
					   	  , 'text'	=> 'Layout File'
						  , 'su'	=> array( 'type' => 'text' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'moduleSets'
					   	  , 'text'	=> 'ModuleSets'
						  , 'su'	=> array( 'type' => 'textarea' )
						  , 'user'	=> array( 'type' => 'none' )
						  , 'current'	=> $this->moduleSets()
					  	  )
					   , array( 'name'	=> 'population'
					   	  , 'text'	=> 'Population'
						  , 'su'	=> array( 'type' => 'plain' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'delete'
					   	  , 'text'	=> 'Delete Course'
						  , 'su'	=> array( 'type' => 'toggle' )
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
		// Read the form that's been submitted (null if just a user)
		$form = $this->readForm();

		if ( is_array( $form ) && $form['delete'] )
		{
			// Create a confirmation form
			$deleteForm = array( array( 'name'  => 'confirmdelete'
						  , 'text'  => 'Confirm Delete'
						  , 'admin' => array( 'type' => 'toggle' )
						  , 'user'  => array( 'type' => 'none' )
					  	  )
					   );
			// Show the form
			$this->stage = 'delete';
			$this->submit = 'Delete';
			return '<P>Please check the box below to confirm deletion.</P>' . $this->makeForm( $deleteForm );
		}

		return parent::apply();
	}
		
	function courseNameArray()
	{
		// Initialize the array
		$values = array();
		
		// Get the list of courses

		$db = $GLOBALS['db']->query( "SELECT name, name FROM courses;" );

		if ( $db )
		foreach( $db as $row )
		{
			$values[ $row['name'] ] = $row['name'];
		}

		return array( 'type' => 'select', 'values' => $values );
	}

	function moduleSets( )
	{
		if ( $this->object->moduleSets )
		foreach ( $this->object->moduleSets as $class => $moduleSet )
		{
			$moduleSets .= $class . ( $moduleSet['name'] ? "->{$moduleSet['name']}" : '' ) . "\r";
		}
		$moduleSets = trim( $moduleSets );

		return $moduleSets;
	}
}
?>
