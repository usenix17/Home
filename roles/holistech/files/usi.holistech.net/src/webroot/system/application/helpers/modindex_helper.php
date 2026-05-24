<?
//////////////////////////////////////////////////////////////////////
//
//	modIndex.php
//	Jason Karcz
//	Index handling class
//
//////////////////////////////////////////////////////////////////////
//
//	19 July 2005 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading modIndex.php');
class modIndex extends Module
{
	// Instance Variables
	var $skill;					// Current skill being indexed
	var $allowPrevAndNext = 0;	// Don't allow previous and next functionality
	var $number = 0;	
	
	// Constructor - $skill is only defined as 'index' when a skill is not calling it during parsing. 
	function modIndex()
	{
		debug_log('New modIndex()');
		$this->name = 'index';
		$this->couresName = COURSENAME;
	}

	function getTitle()
	{
		return '[SAY Index]';
	}

	function getHeader()
	{
		return '[SAY Index]';
	}

	function getPageTitle()
	{
		return '[SAY Index]';
	}

	function getMenuEntry()
	{
		return '[SAY Index]';
	}

	function numPages()
	{
		return 1;
	}

	function getPage($dummy)
	{
		return "This module is not yet supported.";

		// Get the parsed index if available
		$index = $GLOBALS['db']->get_data_from_sql( 
			"SELECT text FROM `index` WHERE course = \"{COURSENAME}\" 
									   AND skill = \"index\" AND lang = \"{$GLOBALS['lang']}\";" );

		if ( $index )
		{
			return unstore( $index );
		}
		else
		{
			if ( $GLOBALS['user']->hasSU() ) warn( "Parsing index." );

			// Get the parent index entries
			$rows = $GLOBALS['db']->query( 
				"SELECT skill, page, parent, name, text, parentText FROM `index` WHERE course = \"{COURSENAME}\"
														  AND lang = \"{$GLOBALS['lang']}\"
														  ORDER BY name;" );
						
			$out = '<TABLE WIDTH="100%"><TR><TD WIDTH="50%">';
			$index = array();

			foreach ( $rows as $row )
			{
				$text = $row['text'];
				$ref = ", <A HREF='?action=display&module={$row['skill']}&page={$row['page']}'>{$row['skill']}:{$row['page']}</A>";
				
				if ( $row['parent'] == '' )
				{
					$index[substr( $row['name'], 0, 1 )][$row['name']]['text'] = $text;
					$index[substr( $row['name'], 0, 1 )][$row['name']]['refs'] .= $ref;
				}
				else
				{	
					if ( !$index[substr( $row['parent'], 0, 1 )][$row['parent']]['text'] )
						$index[substr( $row['parent'], 0, 1 )][$row['parent']]['text'] = $row['parentText'];
						
					$index[substr( $row['parent'], 0, 1 )][$row['parent']]['sub'][$row['name']]['text'] = $text;
					$index[substr( $row['parent'], 0, 1 )][$row['parent']]['sub'][$row['name']]['refs'] .= $ref;
				}
			}

			for ( $letter = 'A'; $letter != 'AA'; $letter++ )
			{
				if ( $letter == 'M' )
					$out .= "</TD><TD>";

				$out .= "<BR><B>$letter</B><HR ALIGN=LEFT CLASS=index><BR>";

				$entries = $index[$letter];

				if ( is_array( $entries ) )
				foreach( $entries as $name => $entry )
				{
					$out .= "<P STYLE='padding-left:2em'>{$entry['text']}{$entry['refs']}";
					
					foreach( $entry['sub'] as $name => $sub )
					{
						$out .= "<BR><SPAN STYLE='padding-left:2em'>{$sub['text']}{$sub['refs']}</SPAN>";
					}
					
					$out .= "</P>";
					
				}
			}
			
			$out .= "</TD></TR></TABLE><P>&nbsp;</P>";
			 
			$row = array( 'skill' => 'index', 'lang' => $GLOBALS['lang'], 'course' => COURSENAME, 'text' => store( $out ) );
			
			$GLOBALS['db']->save_row( 'index', $row );

			return $out;
		}
	}

	function index( $string, $page, $lang )
	{
		// '!' in the field separator
		list( $parent, $text ) = explode( '!', $string );
		
		// If there's no text defined, then the text is found in $parent
		if ( $text == '' )
		{
			$text = $parent;
			$parent = '';
		}
		
		$row = array(	'skill'			=> $this->skill
					,	'page'			=> $page
					,	'parent'		=> $this->reduce( $parent )
					,	'name'			=> $this->reduce( $text )
					,	'parentText'	=> $parent
					,	'text'			=> $text
					,	'course'	=> COURSENAME
					,	'lang'			=> $lang
					);
					
		$GLOBALS['db']->save_row( 'index', $row );		
	}
	
	function reduce( $text )
	{
		return strtoupper( strip_tags( $text ) );
	}
}
?>
