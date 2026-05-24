<?
//////////////////////////////////////////////////////////////////////
//
//	controlPanelApplet.php
//	Jason Karcz
//	Control panel applet class - ABSTRACT
//
//////////////////////////////////////////////////////////////////////
//
//	19 July 2004 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading controlPanelApplet.php');

class ControlPanelApplet
{
	// Instance Variables
	var $title;		// User level / ISO639-keyed array of the applet name
	var $name;		// Applet name
	var $userLevel;		// Minimum requirement (user, admin, su)
	var $object;		// Object to be updated
	var $stage = '';	// Set to apply to activate automatic updating
	var $submit = 'OK';	// Label for the submit button.  Set to '' for no buttons.
	var $buttons = '';	// Override HTML for the Submit and Cancel buttons.
	
	// Constructor - Registers this applet into the system
	function ControlPanelApplet()
	{
		debug_log('New ControlPanelApplet()');
		$GLOBALS['controlPanelApplets'][$this->name] = $this;
	}
	
	function compare( $a, $b )
	{
		return strcmp( $a->title[ $GLOBALS['user']->type ][ $GLOBALS['lang'] ], $b->title[ $GLOBALS['user']->type ][ $GLOBALS['lang'] ] );
	}
		
	// Convert a special array to a form
	// $standAlone makes a form that will not be read by readForm, so 'control_' is not prepended and $this->object is ignored.
	function makeForm( $array, $standAlone = 0 )
	{
		// Store the array and object for transmission,
		// prep the output, and get the user's level
		$form = "<TABLE WIDTH=\"100%\" CELLPADDING=5>\n";

		if ( !$standAlone )
		$form .= "<INPUT TYPE=HIDDEN NAME=controlarray VALUE='" . store( $array ) . "'>\n"
		      .  "<INPUT TYPE=HIDDEN NAME=controlobject VALUE='" . store( $this->object ) . "'>\n";
		      
		$level = ( isset($GLOBALS['user']) && $GLOBALS['user']->type ? $GLOBALS['user']->type : 'user' );
		
		// Iterate through each item
		foreach( $array as $item )
		{
			// Fill in empty level entries in the item (i.e. !admin => user, !su => admin )
			if ( ! isset($item['super']) ) $item['super'] = $item['user'];
			if ( ! isset($item['admin']) ) $item['admin'] = $item['super'];
			if ( ! isset($item['designer']) ) $item['designer'] = $item['admin'];
			if ( ! isset($item['su']) )    $item['su']    = $item['designer'];
			
			// Get the name of the field to be edited, 
			// the label to display,
			// the type of field,
			// the possible values for the current user's level,
			// and the current value of the field.
			$name    = ( $standAlone ? '' : 'control_' ). $item['name'];
			$text    = ( isset($item['text']) ? $item['text'] : '' ); // TODO: Change to say( $item['text'] ) and fill in sayings
			$type    = strtoupper( $item[ $level ]['type'] );
			$values  = ( isset($item[$level]['values']) ? $item[ $level ]['values'] : NULL );
			
			$current = '';
			if ( isset($item['current']) )
			{
				$current = $item['current'];
			}
			elseif ( !$standAlone )
			{
				$current = $this->object->{$item['name']};
				//eval( '$current = $this->object->' . $item['name'] . ';' );
			}

			if ( $type == 'HIDDEN' )
			{
				$form .= "<INPUT TYPE=HIDDEN NAME={$name} VALUE=\"{$current}\">";
			}
			elseif ( $type != 'NONE' )
			{
				// Start with the text
				$form .= '<TR><TD ALIGN=RIGHT CLASS=controlItem>' . $text . ':</TD><TD WIDTH="75%" STYLE="padding-right: 0;">';

				// Deal with the various types of input
				switch ( $type )
				{
					case 'DISPLAY':
						$form .= "$current";
						break;

					case 'PLAIN':
						$form .= "$current<INPUT TYPE=HIDDEN NAME={$name} VALUE=\"{$current}\">";
						break;

					case 'TEXT':
					case 'EMAIL':
						$form .= "<INPUT TYPE=TEXT NAME={$name} VALUE=\"{$current}\">";
						break;

					case 'INT':
						$current = intval( $current );
						$form .= "<INPUT TYPE=TEXT NAME={$name} VALUE=\"{$current}\">";
						break;

					case 'FLOAT':
						$current = floatval( $current );
						$form .= "<INPUT TYPE=TEXT NAME={$name} VALUE=\"{$current}\">";
						break;

					case 'PERCENT':
						$current = intval( $current );
						$form .= "<INPUT TYPE=TEXT NAME={$name} VALUE=\"{$current}%\">";
						break;

					case 'PASSWORD':
						$form .= "<INPUT TYPE=PASSWORD NAME={$name}>";
						break;

					case 'VERIFY':
						$form .= "<INPUT TYPE=PASSWORD NAME={$name}> Verify: <INPUT TYPE=PASSWORD NAME={$name}_verify>";
						break;

					case 'TEXTAREA':
						$form .= "<TEXTAREA NAME={$name} ROWS=10 COLS=50>{$current}</TEXTAREA>";
						break;

					case 'SELECT':
						$form .= "<SELECT NAME={$name}>";
						$values = array_merge( array( "Please choose..." => "Please choose..." ), $values ); 
						foreach ( $values as $value => $text )
						{
							$form .=  "<OPTION VALUE=\"{$value}\""
								. ( $value == $current ? ' SELECTED' : '' )
								. ">{$text}</OPTION>\n";
						}
						$form .= "</SELECT>";
						break;

					case 'TOGGLE':
						$form .= "<INPUT TYPE=CHECKBOX NAME={$name} VALUE=1" 
							. ( $current ? ' CHECKED' : '' ) . '>';
						break;
						
					case 'DATE':
						$current = date( 'j F Y', $current );
						$form .= "<INPUT TYPE=TEXT NAME={$name} VALUE=\"{$current}\">";
						break;
						
				}

				// Close out the row
				$form .= "</TD></TR>\n";
			}
		}

		// Close out and return the table.
		$form .= "</TABLE>\n";

		return $form;
	}

	// Retrieve POST data given the array.
	function readForm()
	{
		// Leave if there is no form to read
		if ( !$_POST['controlarray'] )
		return;

		// Retrieve the array.
		$array = unstore( $_POST['controlarray'] );

		// Unpack the object to update
		$this->object = unstore( $_POST['controlobject'] );

		// Get the user's level
		$level = ( $GLOBALS['user'] && $GLOBALS['user']->type ? $GLOBALS['user']->type : 'user' );
		
		// Initialize error variable
		$error = '';
			
		// Iterate through each item
		foreach( $array as $item )
		{
			// Fill in empty level entries in the item (i.e. !admin => user, !su => admin )
			if ( !$item['super'] ) $item['super'] = $item['user'];
			if ( !$item['admin'] ) $item['admin'] = $item['super'];
			if ( !$item['designer'] ) $item['designer'] = $item['admin'];
			if ( !$item['su'] )    $item['su']    = $item['admin'];
			
			// Get the value(s) to be displayed (for this user's level),
			// the type of input,
			// and the desired name
			$name    = $item['name'];
			$type    = strtoupper( $item[ $level ]['type'] );
			$values  = $item[ $level ]['values'];
			$current = $item['current'];

			// Handle mandatory values
			if ( $item['mandatory'] && !$_POST[ 'control_' . $name ] )
			{
				error( "The field '{$item['text']}' is mandatory." );
				$error = 'error';
			}

			if ( $type != 'NONE' )
			{
				// Deal with the various types of input
				switch ( $type )
				{
					case 'PLAIN':
					case 'HIDDEN':
					case 'TEXT':
					case 'TEXTAREA':
					case 'PASSWORD':
						$form[ $name ] = $_POST[ 'control_' . $name ];
						break;

					case 'EMAIL':
						if ( preg_match( "/^([A-Za-z0-9_+\.\-]+\@([A-Za-z0-9_+\-]+\.)+[A-Za-z0-9_+\-]+)?$/"
							       , $_POST[ 'control_' . $name ] ) )
						{
							$form[ $name ] = $_POST[ 'control_' . $name ];
						}
						else
						{
							$error = "The email address that you entered is invalid.";
						}
						break;

					case 'FLOAT':
						$form[ $name ] = floatVal( $_POST[ 'control_' . $name ] );
						break;

					case 'INT':
					case 'PERCENT':
						$form[ $name ] = intVal( $_POST[ 'control_' . $name ] );
						break;

					case 'VERIFY':
						if ( $_POST[ 'control_' . $name ] == $_POST[ 'control_' . $name . '_verify' ] )
						{
							if ( $_POST[ 'control_' . $name ] != '' )
							$form[ $name ] = $_POST[ 'control_' . $name ];
						}
						else
						{
							$error = "The passwords that you entered don't match.  No changes have been saved.";
						}
						break;

					case 'SELECT':
						foreach ( $values as $value => $text )
						{
							if ( $_POST[ 'control_' . $name ] == $value )
							$form[ $name ] = $_POST[ 'control_' . $name ];
						}
						break;

					case 'TOGGLE':
						if ( $_POST[ 'control_' . $name ] )
							$form[ $name ] = 1;
						else
							$form[ $name ] = 0;
							
						break;
						
					case 'DATE':
						$form[ $name ] = strtotime( $_POST[ 'control_' . $name ] );
						break;
				}
			}
		}

		return ( $error == '' ) ? $form : $error;
	}
	
	function apply()
	{
		debug_log("ControlPanelApplet::apply");
		// Read the form
		$form = $this->readForm();
		
		// Send the results to be applied, or display an error message
		if ( is_array( $form ) )
		{
			// Make the changes
			debug_log("Calling update on CPA object");
			$this->object->update( $form );

			// Disable the submit button
			$this->submit = '';

			return "Changes applied successfully.";
		}
		else
		{
			return '<P CLASS=error>' . $form . '</P>';
		}
	}

}

?>
