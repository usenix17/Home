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
new support();

class support extends ControlPanelApplet
{
	// Instance Variables
	var $title     = array( 'su'       => array( 'EN' => 'Tech Support',	 'ES' => 'Tech Support',		'ZH' => '技术支持' )
				, 'designer'       => array( 'EN' => 'Tech Support',	 'ES' => 'Tech Support',		'ZH' => '技术支持' )
				, 'admin'       => array( 'EN' => 'Tech Support',	 'ES' => 'Tech Support',		'ZH' => '技术支持' )
		      	      );
	var $name      = 'tech';
	var $userLevel = 'admin';

	function display( $stage )
	{

		if ( !$GLOBALS['user']->hasType( $this->userLevel ) )
		{
			error( "You do not have permission to use this function (nice try, though.)" );
			return;
		}


		$form = $this->readForm();

		$codeForm = array( array( 'name'	=> 'search'
			   , 'text'	=> 'Search'
			   , 'admin'	=> array( 'type' => 'text' )
			   )
			);
		
		$this->stage = 'execute';
		$out = $this->makeForm( $codeForm );
		$out .= "<P><TT>CMD HELP</TT> for command reference.</P>";

		//if ( $stage == 'execute' )
		//{
			$search = $form['search'];
			if ( substr( $search, 0, 3 ) == 'CMD' )
				return $out . $this->command( $search );
			$search = ereg_replace( "'", "\"", $search );
			$search = ereg_replace( ';', ",", $search );

		
			if ( !$GLOBALS['user']->hasSU() )
			{
				if ( $GLOBALS['user']->multiCourse )
				{
					$courses = split( "\n", $GLOBALS['user']->multiCourse );
					foreach ( $courses as $item )
					{
						$coursesSql[] = "courseName='" . trim( $item ) . "'";
					}
					$course = '(' . join( ' || ', $coursesSql ) . ') AND';
				}
				else
				{
					$course = "courseName='" . $GLOBALS['courseName'] . "' AND";
				}
			}
				
			$db = $GLOBALS['db']->query( "SELECT first_name, last_name, email, phone, codes, courseName, FROM_UNIXTIME(time), FROM_UNIXTIME(time_in), date_time FROM purchase WHERE {$course} ( first_name LIKE '%{$search}%' OR last_name LIKE '%{$search}%' OR email LIKE '%{$search}%' OR phone LIKE '%{$search}%' OR codes LIKE '%{$search}%' ) ORDER BY time DESC LIMIT 0, 30;" );
			
			$out .= '<STYLE TYPE="text/css">
						table#SUPPORT TD { white-space: nowrap; padding: 5px; }
						tr.odd { background: #EEE; }
					</STYLE>';
			
			$out .= '<TABLE ID=SUPPORT WIDTH="100%" CELLPADDING=0 CELLSPACING=0><TR><TH>First</TH><TH>Last</TH><TH>Request</TH><TH>Response</TH><TH>Completion</TH><TH>E-Mail</TH><TH>Phone</TH><TH>Code (Username)</TH><TH>Course</TH></TR>';
			
			$class = ' CLASS="odd" ';
			
			foreach ( $db as $row )
			{
				$codes = preg_replace( "/(\d+)/e", "\$this->fixCode( '\\1' );", $row['codes'] );
				$codes = ereg_replace( "\n", "<BR>", $codes );
				
				$out .= "<TR {$class}><TD>{$row['first_name']}</TD><TD>{$row['last_name']}</TD><TD>{$row['FROM_UNIXTIME(time)']}</TD><TD>{$row['FROM_UNIXTIME(time_in)']}</TD><TD>{$row['date_time']}</TD><TD>{$row['email']}</TD><TD>{$row['phone']}</TD><TD>{$codes}</TD><TD>{$row['courseName']}</TD></TR>";
				
				$class = ( $class == ' CLASS="odd" ' ? ' CLASS="even" ' : ' CLASS="odd" ' );
			}
			
			$out .= "</TABLE>";
		//}
		//else
		//	$out .= "<P><TT>CMD HELP</TT> for command reference.</P>";
		
		return $out;
	}
	
	function fixCode( $code )
	{
		$x = intval( substr( $code, 0, 5 ) );
		
		$userName = $GLOBALS['db']->get_data( 'codes', 'code', $x, 'username' );
		$courseName = $GLOBALS['db']->get_data( 'users', 'userName', $userName, 'courseName' );
		
		if ( !$userName )
			return $code;
			
		return "{$code} (<A HREF='../{$courseName}/?action=control&applet=user&userName={$userName}&stage=detail'>{$userName}</A>)";
	}
	
	function command( $cmd )
	{
		//error_reporting( E_ALL );
		if ( !$GLOBALS['user']->hasAdmin() )
		{
			error( 'You do not have permission to use this function.' );
			return;
		}
		
		$args = split( ' ', $cmd );
		
		switch ( strtoupper( $args[1] ) )
		{
			case 'HELP':
				return <<<FIN
<TT>
CMD HELP - Shows this help<BR>
CMD SHOW ( USER | SKILL | TEST | COURSE | QUESTION ) <I>objectName</I> [<I>courseName</I>]- Dumps object<BR>
CMD VOID <I>code</I> - Voids given code<BR>
CMD PRUNEMEDIA - Lists unused media files<BR>
</TT>
FIN;
				break;
		
			case 'SHOW':
				if ( !$args[4] )
					$args[4] = $GLOBALS['courseName'];
				switch ( strtoupper( $args[2] ) )
				{
					case 'USER':
						$obj = getUser( $args[3] );
						break;
						
					case 'SKILL':
						$obj = new Skill( $args[3], $args[4] );
						break;
						
					case 'TEST':
						$obj = new Test( $args[3], $args[4] );
						break;
						
					case 'COURSE':
						$obj = new Course( $args[3] );
						break;
						
					case 'QUESTION':
						$obj = new Question( $args[3] );
						break;
				}
				return '<PRE>' . print_r( $obj, 1 ) . '</PRE>';
				break;
				
			case 'VOID':
				return Code::void( $args[2] );
				break;

			case 'PRUNEMEDIA':
				// Force a global parse
				$_GET['parse'] = 1;
				Menu::getModuleMenu();

				$diff = array_diff( array_merge( glob( 'images/*' ), glob( 'flash/*' ), glob( 'images/*/*' ), glob( 'flash/*/*' ) ) , $GLOBALS['media'], glob( 'images/Arrow*' ) );

				foreach ( $diff as $file )
				{
					if ( filetype( $file ) == 'file' )
					{
						shell_exec( "mkdir -p prune/" . dirname( $file ) );
						shell_exec( "mv {$file} prune/" . dirname( $file ) );
						$out .= "Pruned file '{$file}'<BR>";
					}
				}
				return $out;
				break;


			default:
				return "Unknown command: {$args[1]}.";
				break;
		}
	}	
	
	function status( $text = '' )
	{
		if ( $text )
			print $text . "<BR><SCRIPT LANG='text/javascript'>document.body.scrollTop = document.body.scrollHeight;</SCRIPT>";
		else
			print ".<SCRIPT LANG='text/javascript'>document.body.scrollTop = document.body.scrollHeight;</SCRIPT>";
		ob_flush();
	}
}
?>
