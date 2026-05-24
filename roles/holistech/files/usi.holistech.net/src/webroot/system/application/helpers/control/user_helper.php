<?
//////////////////////////////////////////////////////////////////////
//
//	controlUser.php
//	Jason Karcz
//	User management control panel applet 
//
//////////////////////////////////////////////////////////////////////
//
//	19 July 2004 - Created
//
//////////////////////////////////////////////////////////////////////

class control_user extends ControlPanelApplet
{
	// Instance Variables
	var $title     = array( 'user'     => array( 'EN' => 'Update Your Profile', 'ES' => 'Cambiar su informaci&oacute;n',	'ZH' => '更新自我档案' )
				, 'super'     => array( 'EN' => 'Update Your Profile', 'ES' => 'Cambiar su informaci&oacute;n',	'ZH' => '更新自我档案' )
			      //, 'super'    => array( 'EN' => 'Manage Users',	 'ES' => 'Manejar usuarios',		'ZH' => '管理用户' )
			      , 'admin'    => array( 'EN' => 'Manage Users',	 'ES' => 'Manejar usuarios',		'ZH' => '管理用户' )
			      , 'designer' => array( 'EN' => 'Manage Users',	 'ES' => 'Manejar usuarios',		'ZH' => '管理用户' )
			      , 'su'       => array( 'EN' => 'Manage Users',	 'ES' => 'Manejar usuarios',		'ZH' => '管理用户' )
		      	      );
	var $name      = 'user';
	var $userLevel = 'user';

	function control_user($user)
	{
		$this->object =& $user;
	}

	function display( $stage )
	{
		// Set the object to the current user (it will be selected in the user menu.)
		$this->object = $GLOBALS['user'];
		
		// Read the form to get the object
		$form = $this->readForm();
		
		// Only display the chooser form if we're just entering into this applet AND the user is admin or higher.
		if ( ( $stage == '' || $form['findName'] ) && $GLOBALS['user']->hasAdmin() )
		{
			// This form is used to choose which user to edit
			$chooserForm = array( array( 'name'	=> 'userName'
						   , 'text'	=> 'Username'
						   , 'su'	=> $this->userNameArray( 'su', $form['findName'] )
						   , 'designer'	=> $this->userNameArray( 'designer', $form['findName'] )
						   , 'admin'	=> $this->userNameArray( 'admin', $form['findName'] )
						   , 'super'	=> $this->userNameArray( 'super', $form['findName'] )
						  // , 'user'	=> $this->userNameArray( 'user', $form['findName'] )
					   	   )
					    , array( 'name'	=> 'newName'
						   , 'text'	=> 'New User'
						   , 'admin'	=> array( 'type' => 'text' )
						   , 'user'	=> array( 'type' => 'none' )
						   )
					    , array( 'name'	=> 'findName'
						   , 'text'	=> 'Find User'
						   , 'admin'	=> array( 'type' => 'text' )
						   , 'user'	=> array( 'type' => 'none' )
						   )
					    );

			$this->stage = 'detail';
			$this->submit = 'Edit';
			return '<P>Choose a user to edit, or enter a username to create a new user.</P>' 
				. $this->makeForm( $chooserForm );
		}
		elseif ( $stage == 'delete' )
		{
			if ( !$GLOBALS['user']->hasAdmin() )
			{
				error("You don't have permission to do that.");
				return;
			}

			// Disable the submit button
			$this->submit = '';

			if ( $form['confirmdelete'] )
			{
				// Delete the object
				$this->object->delete();
				
				return "The user has been deleted.";
			}
			else
			{
				return "The confirmation box was not checked, so the user was not deleted.";
			}
		}
		elseif ( $stage == 'resetAccount' )
		{
			if ( !$GLOBALS['user']->hasAdmin() )
			{
				error("You don't have permission to do that.");
				return;
			}

			// Disable the submit button
			$this->submit = '';

			if ( $form['confirmreset'] )
			{
				// Delete the object
				$this->object->reset();
				
				return "The user has been reset.";
			}
			else
			{
				return "The confirmation box was not checked, so the user was not reset.";
			}
		}
		else
		{
			// Handle new users
			if ( $form['newName'] ) 
			{
				if ( !$GLOBALS['user']->hasAdmin() )
				{
					error("You don't have permission to do that.");
					return;
				}

				$this->object = getUser( $form['newName'] );
				
				// SUs don't have to worry about quotas.
				if ( !$GLOBALS['user']->hasSU() )
				{	
					// Don't let courses exceed their quota
					if ( $GLOBALS['user']->course->population() >= $GLOBALS['user']->course->quota )
					{
						return "You have exceeded your quota of users.  You cannot add any more users.";
					}
					
					// Don't let courses type names of other courses' users to edit them (or add new of the same name)
					if ( $this->object->exists() )
					{
						return "<P>That username already exists.  Please enter another username.</P>" . $this->display();
					}
				}
				
				$this->object->courseName = $GLOBALS['user']->courseName;
				$this->object->saveData();
				//$this->object = getUser( $form['newName'] );
			}
			else
			{
				// $form['userName'] comes from the previous stage
				// $_REQUEST['userName'] comes from the Tech Support applet.
				$this->object = $GLOBALS['user']->hasAdmin() ? getUser( ( $form['userName'] ? $form['userName'] : $_REQUEST['userName'] ) ) : $GLOBALS['user'];
			}
			
			// This for is used to edit the details of the user
			$detailForm = array( array( 'name'	=> 'userName'
						  , 'text'	=> say( 'Username' )
						  , 'user'	=> array( 'type' => 'plain' )
						  )
					   , array( 'name'	=> 'realName'
					   	  , 'text'	=> say( 'Name' )
						  , 'admin'	=> array( 'type' => 'text' )
						  , 'user'	=> array( 'type' => ( $this->object->completion ? 'plain' : 'text' ) )
					  	  )
					   , array( 'name'	=> 'password'
					   	  , 'text'	=> say( 'Password' )
						  , 'user'	=> array( 'type' => 'verify' )
					  	  )
					   , array( 'name'	=> 'reset'
					   	  , 'text'	=> 'Reset Password'
						  , 'admin'	=> array( 'type' => 'toggle' )
						  , 'user'	=> array( 'type' => 'none' )
						  , 'current'	=> false
					  	  )
					   , array( 'name'	=> 'email'
					   	  , 'text'	=> say( 'E-Mail Address' )
						  , 'user'	=> array( 'type' => 'email' )
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
					   , array( 'name'	=> 'code'
					   	  , 'text'	=> 'Registration Code'
						  , 'super'	=> array( 'type' => 'plain' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'classification'
					   	  , 'text'	=> 'Classification'
						  , 'su'	=> $this->classificationArray( 'su' )
						  , 'admin'	=> $this->classificationArray( 'admin' )
						  , 'super'	=> array( 'type' => 'plain' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'level'
					   	  , 'text'	=> 'Level'
						  , 'admin'	=> array( 'type' => 'float' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'type'
					   	  , 'text'	=> 'Type'
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
						//  , 'super'	=> array( 'type' => 'select'
						//  			, 'values' => array( 'user' => 'User'
						//					   , 'super' => 'Supervisor'
						//				   	   )
						//			)
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'roamingIP'
					   	  , 'text'	=> 'Allow Roaming IP'
						  , 'admin'	=> array( 'type' => 'toggle' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'disable'
					   	  , 'text'	=> 'Disable user'
						  , 'admin'	=> array( 'type' => 'toggle' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'resetAccount'
					   	  , 'text'	=> 'Reset Account'
						  , 'admin'	=> array( 'type' => 'toggle' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   , array( 'name'	=> 'delete'
					   	  , 'text'	=> 'Delete user'
						  , 'admin'	=> array( 'type' => 'toggle' )
						  , 'user'	=> array( 'type' => 'none' )
					  	  )
					   );
			
			if ( $this->object->completion )
			{
				array_push( $detailForm
					   , array( 'name'	=> ''
					   	  , 'text'	=> 'Certificate'
						  , 'admin'	=> array( 'type' => 'display' )
						  , 'user'  => array( 'type' => 'none' )
						  , 'current'	=> '[CERTIFICATE LINK=YES USERNAME="' . $this->object->userName . '"]'
					  	  )
					   , array( 'name'	=> ''
					   	  , 'text'	=> 'Certification Date'
						  , 'admin'	=> array( 'type' => 'display' )
						  , 'user'  => array( 'type' => 'none' )
						  , 'current'	=> date( "j F Y", $this->object->completion )
					  	  )
					   );
			}
					   
			if ( file_exists( 'user.php' ) )
				include( 'user.php' );
			
			if ( $GLOBALS['user']->hasAdmin() )
				$userRow = controlReports::getUserReport( $this->object );	

			// Show the form
			$this->stage = 'apply';
			$this->submit = 'Apply';
			return $this->makeForm( $detailForm ) . $userRow;
		}	
	}

	function apply()
	{
		// Read the form that's been submitted (null if just a user)
		$form = $this->readForm();

		if ( is_array( $form ) && $form['delete'] && $this->object->userName != $GLOBALS['user']->userName )
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

		if ( is_array( $form ) && $form['resetAccount'] )
		{
			// Create a confirmation form
			$deleteForm = array( array( 'name'  => 'confirmreset'
						  , 'text'  => 'Confirm Reset'
						  , 'admin' => array( 'type' => 'toggle' )
						  , 'user'  => array( 'type' => 'none' )
					  	  )
					   );
			// Show the form
			$this->stage = 'resetAccount';
			$this->submit = 'Reset';
			return '<P>This will reset all test scores and levels.</P>' . $this->makeForm( $deleteForm );
		}

		return parent::apply();
	}
		
	function userNameArray( $level, $findName = '' )
	{
		// Initialize the array
		$values = array();

		switch ( $level )
		{
			case 'user':
				return array( 'type' => 'plain' );
				break;

			case 'designer':
				$courseName = $GLOBALS['user']->courseName;
				$db = $GLOBALS['db']->query ( "SELECT userName, realName FROM users WHERE ( courseName='{$courseName}' OR multiCourse LIKE '%{$courseName}%' ) AND type!='su';" );
				break;

			case 'admin':
				$courseName = $GLOBALS['user']->courseName;
				$db = $GLOBALS['db']->query ( "SELECT userName, realName FROM users WHERE ( courseName='{$courseName}' OR multiCourse LIKE '%{$courseName}%' ) AND type!='su' AND type!='designer';" );
				break;

			case 'super':
				$courseName = $GLOBALS['user']->courseName;
				$class = '';
				if ( !$GLOBALS['user']->course->supervisorAllUsers )
					$class = " AND classification='{$GLOBALS['user']->classification}'";
				$db = $GLOBALS['db']->query ( "SELECT userName, realName FROM users WHERE ( courseName='{$courseName}' OR multiCourse LIKE '%{$courseName}%' ) {$class} AND type!='su' AND type!='designer' AND type!='admin';" );
				break;

			case 'su':
				$courseName = $GLOBALS['courseName'];
				$db = $GLOBALS['db']->query ( "SELECT userName, realName FROM users WHERE ( courseName='{$courseName}' OR multiCourse LIKE '%{$courseName}%' );" );
				break;
		}

		if ( $db )
		foreach( $db as $row )
		{
			if ( !$findName || preg_match( "/" . $findName . "/i", $row['userName'] . " (" . $row['realName'] . ")" ) )
				$values[ $row['userName'] ] = $row['userName'] . " (" . $row['realName'] . ")";
		}

		ksort( $values );

		return array( 'type' => 'select', 'values' => $values );
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
			$course = $this->object->course->name;
			$moduleSet = $this->object->course->moduleSets;

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
			$db = $GLOBALS['db']->query ( "SELECT name,moduleSets FROM courses;" );
			
			// Let there be a blank value
			$values[''] = '';

			foreach( $db as $row )
			{
				$moduleSet = unstore($row['moduleSets']);
				
				if ( is_array($moduleSet) )
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
