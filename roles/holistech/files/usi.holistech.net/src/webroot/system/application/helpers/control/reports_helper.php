<?
//////////////////////////////////////////////////////////////////////
//
//	controlReports.php
//	Jason Karcz
//	Report generator control panel applet 
//
//////////////////////////////////////////////////////////////////////
//
//	29 July 2004 - Created
//
//////////////////////////////////////////////////////////////////////

// Create a new instance to get the ball rolling
new controlReports();

require_once("../lib/graph.php");
require_once("../lib/dat.php");

class controlReports extends ControlPanelApplet
{
	// Instance Variables
	var $title     = array( 'admin'    => array( 'EN' => 'Create Reports',	 'ES' => 'Create Reports',		'ZH' => '撰写报告' )
			      , 'designer' => array( 'EN' => 'Create Reports',	 'ES' => 'Create Reports',		'ZH' => '撰写报告' )
			      , 'su'       => array( 'EN' => 'Create Reports',	 'ES' => 'Create Reports',		'ZH' => '撰写报告' )
			      , 'super'    => array( 'EN' => 'Create Reports',	 'ES' => 'Create Reports',		'ZH' => '撰写报告' )
			      , 'user'     => array( 'EN' => 'Report Card',	 'ES' => 'Registro de puntuaci&oacute;n',		'ZH' => '报告卡' )
		      	      );
	var $name      = 'reports';
	var $userLevel = 'user';
	var $csv       = '';

	function display( $stage )
	{
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
		
		// 'users' only see the individual report
		if ( !$GLOBALS{'user'}->hasSuper() ) 
		{
			$out = $this->user();
		}
		else
		{

			switch ( $stage )
			{
				case 'group':
					$out = $this->group();
					break;

				case 'user':
					$out = $this->user();
					break;

				case 'test':
					$out = $this->test();
					break;
					
				case 'demo':
					$out = $this->demo();
					break;
					
				case 'gradeTable':
					$out = $this->gradeTable();
					break;
					
				case 'monthly_registrations':
					$out = $this->monthly_registrations();
					break;
						
				case '':
					$out = <<<FIN
<P>Please choose the report that you'd like to see.</P>

<UL>
<!--	<LI><P><A HREF="?action=control&applet=reports&stage=group">All Users' Progress</A></P></LI>
	--><LI><P><A HREF="?action=control&applet=reports&stage=user">Individual User Report</A></P></LI>
	<LI><P><A HREF="?action=control&applet=reports&stage=monthly_registrations">Monthly Registrations</A></P></LI>
	<LI><P><A HREF="?action=control&applet=reports&stage=test">Test Analysis</A></P></LI>
FIN;

					foreach( glob( "reports/*.dat" ) as $file )
					{
						if( preg_match( "/\/([^\/]+?)\..+?$/", $file, $match ) )
						{
							$name = ereg_replace( '_', ' ', $match[1] );
			
							$out .= "<LI><P><A HREF=\"?action=control&applet=reports&stage={$file}\">" . $name . "</A></P></LI>\n";
						}
					}

					if ( $GLOBALS['user']->hasSU() )
						$out .= '<LI><P><A HREF="?action=control&applet=reports&stage=demo">Demo Logins</A></P></LI>';
						
					$out .= '</UL>';
					break;
					
				default:
					$out = $this->custom($stage);
					break;
			}
		}

		if ( $stage )
		{
			$_SESSION['data'] = store( $out );
			$_SESSION['csv'] = store( $this->csv );
			return "<P ALIGN=RIGHT><A HREF='?action=control&applet=reports&stage=print'>Print</A> " . ( $this->csv ? "<A HREF='?action=control&applet=reports&stage=csv'>CSV</A>" : "" ) . "</P>" . $out;
		}
		else
		{
			return $out;
		}
	}
	

	function group()
	{
		$form = $this->readForm();

		$course = $GLOBALS['user']->course;

		if ( $course )
		{
			if ( count( $course->moduleSets ) == 1 )
			{
				$form['classification'] = array_pop( array_keys($course->moduleSets) );
			}

			// If the user is a supervisor (no Admin), no classification selection.
			if ( !$GLOBALS['user']->hasAdmin() )
			{
				$form['classification'] = $GLOBALS['user']->classification;
			}
			
			if ( isset( $form['classification'] ) )
			{
				$class = ( $form['classification'] == '-all-' ? '' : " AND classification='{$form['classification']}'" );
				
				$users = $GLOBALS['db']->query ( "SELECT userName, realName, type FROM users WHERE courseName='{$course->name}' {$class} AND type!='su' AND type!='designer' ORDER BY realName;" );

				if ( !count( $users ) ) return "There are no users in this classification.";

				$report = '<TABLE WIDTH="100%" BORDER=1 CELLPADDING=3>' . $this->getModuleSetHeader( $course->getModuleSet( $form['classification'] ) );
				$csv = $this->getCSVHeader( $course->getModuleSet( $form['classification'] ) );
				
				foreach ( $users as $row )
				{
					if ( $row['userName'] )
					{
						$user = getUser( $row['userName'] );
						$report .= $this->getUserRow( $user );
						$csv .= $this->getCSV( $user );
					}
				}

				$this->csv = $csv;
				return $report . '</TABLE>';
			}
			else
			{
				// This form is used to choose a classification
				$chooserForm = array( array( 'name'	=> 'courseName'
							   , 'admin'	=> array( 'type' => 'HIDDEN' )
							   , 'current'	=> $course->name
							   )
						    , array( 'name'	=> 'classification'
							   , 'text'	=> 'Classification'
							   , 'admin'	=> $this->classificationArray( $course )
							   )
						    );

				$this->stage = 'group';
				$this->submit = 'OK';
				return '<P>Choose a classification.</P>' 
					. $this->makeForm( $chooserForm );
			}
		}
		else
		{
			// This form is used to choose a course
			$chooserForm = array( array( 'name'	=> 'courseName'
						   , 'text'	=> 'Course'
						   , 'su'	=> $this->courseArray( 'su' )
					   	   )
					    );

			$this->stage = 'group';
			$this->submit = 'OK';
			return '<P>Choose a course.</P>' 
				. $this->makeForm( $chooserForm );
		}
	}

	function user()
	{
		$form = $this->readForm();
		
		if ( !$GLOBALS['user']->hasSuper() )
		{
			$user = $GLOBALS['user'];
		}
		elseif ( $form['userName'] && !$form['findName'] )
		{
			$user = getUser( $form['userName'] );
		}
		
		
		if ( $user )
		{
			return controlReports::getUserReport( $user );
		}
		else
		{
			// This form is used to choose a user
			$chooserForm = array( array( 'name'	=> 'userName'
						   , 'text'	=> 'Username'
						   , 'su'	=> $this->userNameArray( 'su', $form['findName'] )
						   , 'designer'	=> $this->userNameArray( 'designer', $form['findName'] )
						   , 'admin'	=> $this->userNameArray( 'admin', $form['findName'] )
						   , 'super'	=> $this->userNameArray( 'admin', $form['findName'] )
						   , 'user'	=> $this->userNameArray( 'user', $form['findName'] )
					   	   )
					    , array( 'name'	=> 'findName'
						   , 'text'	=> 'Find User'
						   , 'admin'	=> array( 'type' => 'text' )
						   , 'user'	=> array( 'type' => 'none' )
						   )
					    );

			$this->stage = 'user';
			$this->submit = 'View';
			return '<P>Choose a user whose report card you wish to view.</P>' 
				. $this->makeForm( $chooserForm );
		}
	}

	function test()
	{
		require_once( "../lib/test.php" );
		// If we're getting the report on a specific question, let the question object handle the output.
		// If not, we need to find out where we are in the test-selection process and maybe display the stats of a test.
		if ( $_GET['questionNumber'] )
		{
			$question = new Question( $_GET['id'] );
			return $question->graph( $_GET['questionNumber'], $_GET['averageAttempts'] );
		}
		elseif ( $_GET['test'] )
		{
			$test = new Test( $_GET['test'] );
			
			return $test->graph( true );
		}			
		else
		{
			$form = $this->readForm();

			$course = $GLOBALS['user']->course;
			
			if ( $course )
			{
				if ( count( $course->moduleSets ) == 1 )
				{
					$form['classification'] = array_pop( array_keys($course->moduleSets) );
				}
				
				// If the user is a supervisor (no Admin), no classification selection.
				if ( !$GLOBALS['user']->hasAdmin() )
				{
					$form['classification'] = $GLOBALS['user']->classification;
				}
			
				if ( isset( $form['classification'] ) )
				{
					$moduleSet = $course->getModuleSet( $form['classification'] );

					$report = '<TABLE WIDTH="100%">';
					
					foreach ( $moduleSet->moduleSet as $number => $data )
					{
							if ( strtoupper( $data['type'] ) == 'TEST' )
							{
								// Grab the module
								$module = new Test( $data['module'], $GLOBALS['courseName'] );

								// Get the title of the module
								$title = $module->getMenuEntry();

								$graph = $module->graph();
								
								$report .= "<TR><TD STYLE='padding-left: 1em;'><A HREF='?action=control&applet=reports&stage=test&test={$data['module']}'>$title</A></TD><TD>$graph</TD></TR>\n";
							}
					}
					
					return $report . '</TABLE>';
				}
				else
				{
					// This form is used to choose a classification
					$chooserForm = array( array( 'name'	=> 'courseName'
								   , 'admin'	=> array( 'type' => 'HIDDEN' )
								   , 'current'	=> $course->name
								   )
							    , array( 'name'	=> 'classification'
								   , 'text'	=> 'Classification'
								   , 'admin'	=> $this->classificationArray( $course )
								   )
							    );

					$this->stage = 'test';
					$this->submit = 'OK';
					return '<P>Choose the classification whose tests you wish to analyze.</P>' 
						. $this->makeForm( $chooserForm );
				}
			}
			else
			{
				// This form is used to choose a course
				$chooserForm = array( array( 'name'	=> 'courseName'
							   , 'text'	=> 'Course'
							   , 'su'	=> $this->courseArray( 'su' )
							   )
						    );

				$this->stage = 'test';
				$this->submit = 'OK';
				return '<P>Choose a course.</P>' 
					. $this->makeForm( $chooserForm );
			}
		}
	}

	function demo()
	{
		if ( $GLOBALS['user']->hasSU() )
		{
			$demo = $GLOBALS['db']->get_table( 'demo' );

			$report = '<TABLE WIDTH="100%" BORDER=1 CELLPADDING=3>'
				. '<TR><TH>Name</TH><TH>E-Mail</TH><TH>Company</TH><TH>Title</TH><TH>Phone</TH><TH>Location</TH><TH>IP</TH><TH>Hostname</TH></TR>';

			foreach ( $demo as $row )
			{
				if ( !$row['hostname'] )
				{
					$row['hostname'] = gethostbyaddr( $row['ip'] );
					$GLOBALS['db']->save_row( 'demo', $row );
				}
				
				$report .= '<TR>'
					.  '<TD>' . $row['realName'] . '&nbsp;</TD>'
					.  '<TD>' . $row['email'] . '&nbsp;</TD>'
					.  '<TD>' . $row['company'] . '&nbsp;</TD>'
					.  '<TD>' . $row['title'] . '&nbsp;</TD>'
					.  '<TD>' . $row['location'] . '&nbsp;</TD>'
					.  '<TD>' . $row['phone'] . '&nbsp;</TD>'
					.  '<TD>' . $row['ip'] . '&nbsp;</TD>'
					.  '<TD>' . $row['hostname'] . '&nbsp;</TD>'
					.  '</TR>';
			}

			return $report . '</TABLE>';
		}
	}

	function custom( $fileName )
	{
		$file = new File( $fileName );
		$dat = new dat( $file );
		$dat->read();
		preg_match( "/\/([^\/]+?)\..+?$/", $fileName, $match );
		$name = ereg_replace( '_', ' ', $match[1] );
		
		// Parse report DAT file
		foreach ( $dat->dat as $item )
		{
			switch ( $item['name'] )
			{
				case 'FIELDS':
					
					foreach ( $item['contents'] as $field )
					{
						if ( $field['name'] != 'FIELD' ) next;
						
						$fields[$field['args']['NAME']] = $field['args']['LABEL'];
						
						// If there's a ( in the field name, then it's pry a SQL Fn.
						if ( preg_match( '/\(/', $field['args']['NAME'] ) )
							$sqlFields[] = $field['args']['NAME'];
						else
							$sqlFields[] = '`' . $field['args']['TABLE'] . '`.`' . $field['args']['NAME'] . '`';
											
						$tables[$field['args']['TABLE']] = 1;
					}
					
				break;

				case 'CONDITIONS':
					
					foreach ( $item['contents'] as $field )
					{
						if ( $field['name'] != 'CONDITION' ) next;
												
						$where[] = $field['contents'];
					}
					
				break;
				
				case 'SORT':
					$sort = $item['contents'];
				break;
				
				case 'REFRESH':
					header('Refresh: '.$item['contents']);
				break;
				
				case 'ALLCOURSES':
					$allcourses = true;
				break;
				
				case 'DEBUG':
					$debug = true;
				break;
			}
		}
		
		// Add restrictions if users table is used
		if ( array_key_exists( 'users', $tables ) )
		{
			$where[] = "users.type != 'su'";
			if ( !($allcourses && $GLOBALS['user']->hasSU()) )
				$where[] = "users.courseName = '{$GLOBALS['courseName']}'";
		}

		// Make SQL query.
		$sql = "SELECT " . join( $sqlFields , ', ' ) 
		     . " FROM `" . join( array_keys( $tables ), '`, `' ) . '`'
			 . ( count( $where ) ? " WHERE " . join( $where, ' AND ' ) : '' )
			 . ( $sort ? " ORDER BY {$sort}" : '' )
			 . ';';
			 
		// Debug if requested
		if ( $debug )
			debug( $sql );

		// Query database
		$result = $GLOBALS['db']->query($sql);
		
		// Start report output
		$report = "<H2>{$name}</H2>" . '<TABLE WIDTH="100%" BORDER=1 CELLPADDING=3><TR><TH>' 
				. join( array_values( $fields ), "</TH><TH>" ) . "</TH></TR>";
		$this->csv = '"' . join( array_values( $fields ), '","' ) . "\"\n";
		
		// Make rows
		foreach ( $result as $row )
		{
			$report .= "<TR>";
			
			foreach ( $fields as $name => $label )
			{
				$row[$name] = htmlspecialchars( $row[$name] );
				if ( $name == 'userName' )
				{
					$courseName = $GLOBALS['db']->get_data( 'users', 'userName', $row[$name], 'courseName' );
					$row[$name] = "<A HREF='../{$courseName}/?action=control&applet=user&userName={$row[$name]}&stage=detail'>{$row[$name]}</A>";
				}
				$report .= "<TD>" . $row[$name] . "&nbsp;</TD>";
				$this->csv .= '"' . $row[$name] . '",';
			}
			
			$report .= "</TR>";
			rtrim( $this->csv, ',' );
			$this->csv .= "\n";
		}
		
		// End table
		$report .= "</TABLE>";
		
		return $report;
		
	}

	static function getModuleSetHeader( $moduleSet )
	{
		debug_log('Begin getModuleSetHeader()');
		$head = '<COLGROUP ALIGN=LEFT WIDTH="1*"><COLGROUP SPAN=' . ( count( $moduleSet->moduleSet ) + 1 )  . ' ALIGN=CENTER WIDTH="1*"><THEAD><TR><TH ALIGN=CENTER>' . say( 'Username' ) . ' (' . say( 'Name' ) . ') (Login Time)</TH>';

		foreach ( $moduleSet->moduleSet as $number => $data )
		{
				// Grab the module
				$module = getModule( $data['module'] );

				// Get the title of the module
				$title = $module->getMenuEntry();

				if ( is_array( $title ) )
					$title = $title[0]['title'];

				// Add the module to the list
				$head .= "<TH><A HREF=\"?action=display&module={$data['module']}&page=1\">{$title}</A></TH>";
		}

		debug_log('End getModuleSetHeader()');
		return $head . "</TR></THEAD>\n";
	}
	
	function getCSVHeader( $moduleSet )
	{
		$head = say( 'Username' ) . ',' . say( 'Name' ) . ',Login Time,';
		
		foreach ( $moduleSet->moduleSet as $number => $data )
		{
				// Grab the module
				$module = getModule( $data['module'] );

				// Get the title of the module
				$title = $module->getTitle();

				// Add the module to the list
				$head .= "{$title},";
		}

		return $head . "\n";
	}

	static function getUserRow( $user )
	{
		debug_log('Begin getUserRow()');
		//debug( $user );
		$level = $user->type == 'su' ? 99999 : $user->level;
		
		$moduleSet = $user->moduleSet;
		
		$time = date( 'j M Y H:i', $user->loginTime );

		$row = "<TD>{$user->userName} ({$user->realName}) {$time}</TD>";

		foreach ( $moduleSet->moduleSet as $number => $data )
		{
			if ( $data['requires'] > $level )
			{
				$row .= '<TD>&nbsp;</TD>';
			}
			elseif ( $data['requires'] == $level && strtoupper( $data['type'] ) != 'TEST'  )
			{
				$row .= '<TD BGCOLOR="#CCFFCC">Current</TD>';
			}
			elseif ( strtoupper( $data['type'] ) == 'TEST' )
			{
				// Grab the module
				$module = getModule( $data['module'] );
				
				$scores = array();

				if ( $data['requires'] == $level )
					$scores[] = 'Current';

				foreach ( $user->data['test'][$module->testName]['attempts'] as $n => $attempt )
				{
					$scores[] = "<A HREF='?action=control&applet=reports&stage=gradeTable&userName={$user->userName}&test={$module->testName}&attempt={$n}'>{$attempt['score']}%</A>";
				}
				
				$scores = join( '<BR>', $scores );
				
				$row .= '<TD BGCOLOR="#CCFFCC">' . $scores . '</TD>';
			}
			else
			{
				$row .= '<TD BGCOLOR="#CCFFCC">Completed</TD>';
			}
		}

		debug_log('End getUserRow()');
		return $row . "</TR>\n";
	}
	
	function getCSV( $user )
	{
		$level = $user->type == 'su' ? 99999 : $user->level;
		
		$moduleSet = $user->moduleSet;

		$time = date( 'j M Y H:i', $user->loginTime );

		$row = "{$user->userName},{$user->realName},{$time},";

		foreach ( $moduleSet->moduleSet as $number => $data )
		{
			if ( $data['requires'] > $level )
			{
				$row .= ',';
			}
			elseif ( $data['requires'] == $level )
			{
				$row .= 'Current,';
			}
			elseif ( strtoupper( $data['type'] ) == 'TEST' )
			{
				// Grab the module
				$module = getModule( $data['module'] );
				
				$row .= $user->data['test'][$module->name]['highscore'] . '%,';
			}
			else
			{
				$row .= 'Completed,';
			}
		}

		return $row . "\n";
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
				$class = $GLOBALS['user']->classification;
				$db = $GLOBALS['db']->query ( "SELECT userName, realName FROM users WHERE ( courseName='{$courseName}' OR multiCourse LIKE '%{$courseName}%' ) AND classification='{$class}' AND type!='su' AND type!='designer' AND type!='admin';" );
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
	
	function classificationArray( $course )
	{
		// Initialize the array
		$values = array();

		$moduleSet = $course->moduleSets;

		// Let there be a blank and an all value
		$values['-all-'] = '-all-';
		$values[''] = '';

		if ( $moduleSet )
		foreach ( $moduleSet as $class => $data )
		{
			if ( $class != '' )
			$values[ $class ] = $class;
		}
				
		return array( 'type' => 'select', 'values' => $values );
	}			
	
	function apply()
	{
	}
	
	function gradeTable()
	{
		$user = getUser( $_REQUEST['userName'] );
//print_r( $user );	
		
		if ( 
			( !$GLOBALS['user']->hasSuper() && $GLOBALS['user']->userName != $_REQUEST['userName'] ) ||
			( $GLOBALS['user']->type == 'super' && $GLOBALS['user']->classification != $user->classification ) ||
			( $GLOBALS['user']->hasAdmin() && $GLOBALS['user']->courseName != $user->courseName )
			)
		{
			error( "You are not authorized to view this page" );
			return;
		}
		
		return $user->data['test'][$_REQUEST['test']]['attempts'][$_REQUEST['attempt']]['gradeTable'];
	}
	
		function monthly_registrations()
	{
			$form = $this->readForm();

			$dateForm = array( array( 'name'	=> 'startDate'
						   , 'text'	=> 'Start Date (inclusive)'
						   , 'admin'	=> array( 'type' => 'text' )
						   , 'user'	=> array( 'type' => 'none' )
						   , 'current'	=> $form['startDate']
					   	   )
					    , array( 'name'	=> 'endDate'
						   , 'text'	=> 'End Date (non-inclusive)'
						   , 'admin'	=> array( 'type' => 'text' )
						   , 'user'	=> array( 'type' => 'none' )
						   , 'current'	=> $form['endDate']
						   )
					    );

			$this->stage = 'monthly_registrations';
			$this->submit = 'OK';
			$out = '<P>Pick a date range, or leave it blank for no limit.</P>' 
				. $this->makeForm( $dateForm )
				. '<P><input type=submit name=OK value=Filter></P>';
			
			$startDate = strtotime( $form['startDate'] );
			$endDate = strtotime( $form['endDate'] );
			
			$out .= '<P>Showing registrations made ';
			
			// Set up the SQL date filter and the filter display text
			if ( !$startDate && !$endDate )
				$out .= 'anytime.</P>';
			elseif ( !$startDate && $endDate )
			{
				$out .= 'before ' . date( 'j F Y', $endDate ) . '</P>';
				$dateFilter .= "AND users.ctime < {$endDate} ";
			}
			elseif ( $startDate && !$endDate )
			{
				$out .= 'after ' . date( 'j F Y', $startDate ) . '</P>';
				$dateFilter .= "AND users.ctime >= {$startDate} ";
			}
			else
			{
				$out .= 'between ' . date( 'j F Y', $startDate ) . ' and ' . date( 'j F Y', $endDate ) . '</P>';
				$dateFilter .= "AND users.ctime >= {$startDate} AND users.ctime < {$endDate} ";
			}	

			$sql = "SELECT ctime, userName FROM users WHERE ctime > 0 AND courseName='{$GLOBALS['courseName']}' {$dateFilter} ORDER BY ctime";
			
			$db = $GLOBALS['db']->query( $sql );
			
			$registrations = array();
			
			foreach ( $db as $row )
			{
				$registrations[ date( 'M Y', $row['ctime'] ) ]++;
			}
			
			//$out .= '<PRE>' . print_r( $registrations, 1 ) . '</PRE>';
			
			$graph = new Graph( $registrations, "Registrations by Month" );
			
			$out .= $graph->bar();
			
			return $out;
	}

	static function getUserReport( $user )
	{
		return '<TABLE WIDTH="100%" BORDER=1 CELLPADDING=3>' . controlReports::getModuleSetHeader( $user->moduleSet ) . controlReports::getUserRow( $user ) . '</TABLE>';
	}


}


?>
