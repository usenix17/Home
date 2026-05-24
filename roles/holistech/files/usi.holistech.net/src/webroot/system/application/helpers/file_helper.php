<?
//////////////////////////////////////////////////////////////////////
//
//	file.php
//	Jason Karcz
//	File management functions 
//
//////////////////////////////////////////////////////////////////////
//
//	29 July 2004 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading file.php');
class File
{
	// Instance Variables
	var $path;
	var $local_prefix;

	function file( $path )
	{
		debug_log('New file('.$path.')');
		// Save the path
		$this->path = $path;
		$this->local_prefix = BASEPATH . 'application/resources/' . COURSENAME . '/';
	}

	function read()
	{
		if ( !$this->exists() )
		{
			error("Resource ".$this->path." does not exist.");
			return;
		}

		if ( filesize( $this->local_prefix.$this->path ) == 0 )
			return '';

		$fp = fopen( $this->local_prefix.$this->path, 'rb' );
		$out = fread( $fp, filesize( $this->local_prefix.$this->path ) );
		fclose( $fp );

		return $out;
			
	}

	function write( $data )
	{
		$fp = fopen( $this->local_prefix.$this->path, 'w' );
		fwrite( $fp, $data );
		fclose( $fp );	
	}

	function exists()
	{
		return file_exists( $this->local_prefix.$this->path );
	}
	
	function mtime()
	{
		clearstatcache();
		return filemtime( $this->local_prefix.$this->path );
	}

	function url()
	{
		return base_url().'layout/'.$this->path;
	}

	function path()
	{
		return $this->local_prefix.$this->path;
	}
}
?>
