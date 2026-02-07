<?
//////////////////////////////////////////////////////////////////////
//
//	db.php
//	Jason Karcz
//	Database interface
//
//////////////////////////////////////////////////////////////////////
//
//	25 February 2004 - Created
//
//////////////////////////////////////////////////////////////////////

// Initialize the database
	$GLOBALS['db'] = new DB( "localhost", "fqot", 'training', "training" );

class DB
{
	var $errors;

	function DB( $host, $login, $passwd, $db )
	{
		mysql_connect( $host, $login, $passwd ) or die("Could not connect to the database at host {$host}: " . mysql_error() . "\n");
		mysql_select_db( $db );
	}

	function __destruct()
	{
		debug_log( "Destructing DB<BR>");
	}

	function save_row( $table, $row, $mtime = '' )
	{
		debug_log("Saving row to $table");
		debug_log($row,TRUE);

		$columns = '';
		$values = '';

		debug_log('Begin save_row');
		if ( $mtime )
		{
			$row['mtime'] = $this->get_data_from_sql( "SELECT FROM_UNIXTIME({$mtime});" );
		}
		
		// Create columns and values
		foreach ( $row as $name => $value )
		{
			$columns .= "$name,";
			$value = str_replace( '"', "'", $value );
			//$value = str_replace( ';', ",", $value );
			$values .= ( $value === 'NULL' || $value === NULL ? "NULL," : "\"$value\"," );
		}

		// Remove trailing commas
		$columns = substr( $columns, 0, -1 );
		$values = substr( $values, 0, -1 );

		debug_log('save_row->query');
		$result = $this->mysql_unbuffered_query( "REPLACE INTO `{$table}` ({$columns}) VALUES ({$values});" );
		debug_log('End save_row');
	}
	
	function delete_row( $table, $key, $value )
	{
		$value = str_replace( '"', "'", $value );
		$value = str_replace( ';', ",", $value );
		$this->mysql_query( "DELETE FROM `{$table}` WHERE {$key}=\"{$value}\"" );
	}

	function get_row( $table, $key, $value )
	{
		$value = str_replace( '"', "'", $value );
		$value = str_replace( ';', ",", $value );
		return $this->get_row_from_sql( "SELECT * from `{$table}` WHERE {$key}=\"{$value}\"" );
	}
	
	function get_row_from_sql( $sql )
	{
		$result = $this->mysql_query( $sql );
		$array = mysql_fetch_array( $result, MYSQL_ASSOC );
		mysql_free_result( $result );

		debug_log($array,true);
		return is_array( $array ) ? $array : 0;
	}
	
	function get_data( $table, $key, $value, $column )
	{
		$value = str_replace( '"', "'", $value );
		$value = str_replace( ';', ",", $value );
		return $this->get_data_from_sql( "SELECT {$column} from `{$table}` WHERE {$key}=\"{$value}\"" );
	}

	function get_data_from_sql( $sql )
	{
		$result = $this->mysql_query( $sql );
		$array = mysql_fetch_array( $result, MYSQL_ASSOC );
		mysql_free_result( $result );

		return is_array( $array ) ? array_pop( $array ) : 0;
	}

	function get_mtime( $table, $key, $value )
	{
		$value = str_replace( '"', "'", $value );
		$value = str_replace( ';', ",", $value );
		return $this->get_data_from_sql( "SELECT UNIX_TIMESTAMP(mtime) from `{$table}` WHERE {$key}=\"{$value}\"" );
		
	}

	function get_table( $table )
	{
		return $this->query( "SELECT * FROM `{$table}`" );
	}

	function query( $sql )
	{
		$array = array();

		$result = $this->mysql_query( $sql );
		//if ( mysql_error() ) die(mysql_error());
		while ( $row = mysql_fetch_array( $result, MYSQL_ASSOC ) )
		{
			$array[] = $row; 
		}
		mysql_free_result( $result );

		return $array;
	}

	function mysql_unbuffered_query( $sql )
	{
		debug_log('Begin mysql_unbuffered_query - '.$sql);
		$result = mysql_unbuffered_query( $sql );
		debug_log('End mysql_unbuffered_query - '.$sql);
		if ( mysql_error() ) $this->errors[] = array( 'sql' => $sql, 'error' => mysql_error() );
		return $result;
	}

	function mysql_query( $sql )
	{
		debug_log('Begin mysql_query - '.$sql);
		$result = mysql_query( $sql );
		debug_log('End mysql_query - '.$sql);
		if ( mysql_error() ) $this->errors[] = array( 'sql' => $sql, 'error' => mysql_error() );
		return $result;
	}
}
?>
