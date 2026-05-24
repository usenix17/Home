<?
//////////////////////////////////////////////////////////////////////
//
//      moduleSet.php
//      Jason Karcz
//      Maintains a set of dependent modules
//
//////////////////////////////////////////////////////////////////////
//
//      16 October 2003 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading moduleSet.php');

class ModuleSet
{
	// Instance variables
	var $moduleSet;
	var $modules;
	var $name;
	var $courseName;

	// Constructor
	function ModuleSet( $name, $courseName )
	{
		debug_log('New ModuleSet('.$name.','.$courseName.')');
		$this->name = $name;
		$this->courseName = $courseName;
			
		$db = $GLOBALS['db']->query( "SELECT * FROM moduleSets WHERE name='{$name}' AND courseName='{$courseName}' AND type!='header' ORDER BY position;" );

		$requires = 0;
		$position = 0;
		foreach ( $db as $row )
		{
			switch ( $row['availability'] )
			{
			case 'always':
				$row['provides'] = 0;
				$row['requires'] = 0;
				break;
			case 'sequential':
				$row['provides'] = $requires + 1;
				$row['requires'] = $requires;
				$requires++;
				break;
			case 'after':
				$row['provides'] = $this->moduleSet[$this->modules[$row['after']]]['provides'];
				$row['requires'] = $this->moduleSet[$this->modules[$row['after']]]['provides'];
				break;
			}

			$this->moduleSet[$position] = $row;
			$this->modules[$row['module']] = $position;

			$position++;
		}

		return;
	}

	function import( $name, $courseName )
	{
		//------------------------------------------------------------------------------------------------
		//Old file-based code from this point - necessary only for importing legacy modulesets (not likely)
		//
		// Set an instance variable to the moduleset file
		require_once( '../lib/file.php' );
		$file = new File( "../{$courseName}/modulesets/" . $name . ".moduleset" );
			
		if ( $file->exists() )
		{
			// Create the header row
			$row = array( 'type' => 'header', 'module' => 'header', 'name' => $name, 'courseName' => $courseName, 'position' => 'null' );
			$GLOBALS['db']->save_row( 'moduleSets', $row );

			// Read in the moduleset file
			$moduleSet = $file->read();
			$moduleSet = trim( $moduleSet );

			// Split the lines
			$moduleSet = explode( "\n", $moduleSet );

			// Initialize a counter
			$count = 0;
			
			// Iterate through each line
			foreach ( $moduleSet as $module )
			{
				$row = array();
				if ( substr( $module, 0, 1 ) != '#' )
				{
					// Split by tabs
					$module = explode( "\t", $module );

					// Assign the fields to the $moduleSet instance variable
					// where provides gives the level that module provides
					// and requires gives the requirements of that level.
					// $modules is a reverse lookup array
					$row['type'] = trim( $module[0] );
					$row['module'] = trim( $module[1] );
					$row['provides'] = trim( $module[2] );
					$row['requires'] = trim( $module[3] );
					$row['number'] = trim( $module[4] );
					$row['position'] = $count;
					$row['name'] = $name;
					$row['courseName'] = $courseName;

					// What module provides a certain level
					if ( !$provides[$row['provides']] )
						$provides[$row['provides']] = $row['module'];

					if ( $row['provides'] == 0 && $row['requires'] == 0 )
						$row['availability'] = 'always';
					elseif ( $row['provides'] <= $row['requires'] )
					{
						$row['availability'] = 'after';
						$row['after'] = $provides[$row['requires']];
					}

					if (  $row['number'] === '' )
					{
						unset( $row['number'] );
					}

					$GLOBALS['db']->save_row( 'moduleSets', $row );

					// Increment the count
					$count++;
				}
			}
		}
		else
		// The name does not exist
		{
			error( "The module set file '" . $file->path . "' does not exist." );
		}
	}

	function get_modules_JSON()
	{
		$json = array();

		foreach ( $this->modules as $moduleName => $index)
		{
			$index = $this->modules[$moduleName];
			$module = getModule($moduleName);
			$path = '';

			switch (  $this->moduleSet[$index]['type'] )
			{
			case 'test':
				$path = '/tests/load/'.$moduleName;
				break;

			case 'skill':
				$path = '/skills/load/'.$moduleName;
				break;

			case 'dbform':
				$path = '/userForms/load/'.$moduleName;
				break;

			default:
				fatal('Invalid module type: '.$this->moduleSet[$index]['type']);
				break;
			}

			$json[$index] = array(
				'name' => $module->getTitle(),
				'num_pages' => $module->numPages(),
				'type' => $this->moduleSet[$index]['type'],
				'module' => $index,
				'moduleName' => $moduleName,
				'path' => $path,
			);

			// Mark as dynamic if it's a test
			if ( $json[$index]['type'] == 'test' || $json[$index]['type'] == 'dbform' )
				$json[$index]['dynamic'] = TRUE;
		}
		debug_log("Finished compiling modules");

		$json['num_modules'] = count($json);
		debug_log("Finished counting modules");

		return json_encode($json);
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////
	//	
	//	Old functions
	//
	////////////////////////////////////////////////////////////////////////////////////////////////////

	function getNext( $module )
	// return the name of the next module
	{
		// Get the index of the module
		$index = $this->modules[$module];

		// If the next module doesn't exist in the moduleset, or the current module dne, return 0
		if ( !is_int($index) || ( $index + 1 ) > count( $this->moduleSet ) )
			return 0;

		// Return the name of the module
		return $this->moduleSet[$index + 1]['module'];
	}
	
	function getPrevious( $module )
	// return the name of the previous module
	{
		// Get the index of the module
		$index = $this->modules[$module];

		// If the next module doesn't exist in the moduleset, or the current module dne, return 0
		if ( !is_int($index) || ( $index - 1 ) < 0 )
			return 0;

		// Return the name of the module
		return $this->moduleSet[$index - 1]['module'];
	}

	function getType( $module )
	{
		// Get the index of the module
		$index = $this->modules[$module];

		// If the module dne, warn
		//if ( !is_int( $index ) )
		//	warn( "Module '$module' in moduleset '" . $this->name . "' does not exist. '$index'. (moduleSet.php:getType)" );

		// Return $module's type
		return $this->moduleSet[$index]['type'];
	}

	function getProvision( $module )
	{
		// Get the index of the module
		$index = $this->modules[$module];

		// If the module dne, warn
		//if ( !is_int( $index ) )
		//	warn( "Module '$module' in moduleset '" . $this->name . "' does not exist. (moduleSet.php:getProvision)" );

		// Return what $module provides
		return $this->moduleSet[$index]['provides'];
	}

	function getRequirement( $module )
	{
		// Get the index of the module
		$index = $this->modules[$module];

		// If the module dne, warn
		//if ( !is_int( $index ) )
		//	warn( "Module '$module' in moduleset '" . $this->name . "' does not exist. (moduleSet.php:getRequirement)" );

		// Return what $module requires
		return $this->moduleSet[$index]['requirement'];
	}
	
	function getNumber( $module )
	{
		if ( ! isset($this->modules[$module]) )
			return NULL;

		// Get the index of the module
		$index = $this->modules[$module];

		// If the module dne, warn
		//if ( !is_int( $index ) )
		//	warn( "Module '$module' in moduleset '" . $this->name . "' does not exist. (moduleSet.php:getNumber)" );

		// Return what $module requires
		return $this->moduleSet[$index]['number'];
	}


}
?>
