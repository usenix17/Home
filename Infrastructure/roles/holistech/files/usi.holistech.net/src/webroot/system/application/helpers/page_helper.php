<?
//////////////////////////////////////////////////////////////////////
//
//	page.php
//	Jason Karcz
//	page Handling Class
//
//////////////////////////////////////////////////////////////////////
//
//	19 November 2005 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading page.php');
class Page extends Module
{
	// Instance Variables
	var $allowPrevAndNext = 0;
	var $number = 0;
	var $languages;
	
	var $title;
	var $header;
	var $contents;

	// Constructor
	function Page()
	{
		debug_log('New Page()');
		$this->headerColor = "#FEF4B6";
		$this->menuColor = "#FCC500";
			
		require_once( "../lib/dat.php" );
		require_once( '../lib/file.php' );
		$dat = new dat( new File( $GLOBALS['page'] . '.dat.html' ) );

		$dat->read(2);
		
		foreach( $dat->dat as $number => $item )
		{
			if ( $item['name'] == 'TITLE' )
				$this->title[$item['args']['LANG']] = $item['contents'];
			
			if ( $item['name'] == 'HEADER' )
				$this->header[$item['args']['LANG']] = $item['contents'];
			
			if ( $item['name'] == 'CONTENTS' )
				$this->contents[$item['args']['LANG']] = $item['contents'];
		}
	}

	function getTitle()
	{
		return $this->title[$GLOBALS['lang']];
	}

	function getHeader()
	{
		return $this->header[$GLOBALS['lang']];
	}
	
	function getPageTitle()
	{
		//return say( "Login" );
	}

	function display()
	{
		return $this->contents[$GLOBALS['lang']];
	}

	function getLanguages()
	{
		return array_keys( $this->contents );
	}

	function progressBar()
	{
		return "";
	}	
}
?>
