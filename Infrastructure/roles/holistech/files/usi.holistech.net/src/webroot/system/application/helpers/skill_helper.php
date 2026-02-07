<?
//////////////////////////////////////////////////////////////////////
//
//	skill.php
//	Jason Karcz
//	Skill handling class
//
//////////////////////////////////////////////////////////////////////
//
//	16 October 2003 - Created
//	9 September 2008 - Rewrote for database storage only
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading skill.php');
class Skill extends Module
{
	// Instance Variables
	var $name;				// Filename of the skill
	var $courseName;			// Name of the current course
	var $title = array();
	var $languages = array();		// Languages the skill is available in
	var $ci;

	private $number;			// Skill number - access via number()
	private $numPages;			// Number of pages - access via numPages()

	var $pages;				// Collection of all of the pages in the skill
	
	// Constructor
	function Skill( $module_id )
	{	
		// Set name and courseName
		parent::Module($module_id);

		//$this->ci =& get_instance();
		//$this->ci->load->library('bench');

		debug_log('New Skill('.$this->name.','.$this->courseName.')');

		//$this->read_DB();
		//$this->write_XML();
		$this->read_XML();
	}

	function clear_contents()
	{
		$this->title = NULL;
		$this->languages = NULL;
		$this->pages = NULL;
	}

	function getTitle($wrapper='DIV')
	{
		$title = array();

		foreach ( $this->languages as $lang )
		{
			$title[$lang] = ( ( $this->number() != 0 ) ? say("Skill",$lang) . " " . $this->number() . " - " : "" ) 
				. $this->title[$lang];
		}

		return lang(str_replace(' - ',' &ndash; ',$title),$wrapper);
	}

	function numPages()
	{
		return count($this->pages);
	}

	function getPage($page)
	{
		if ( $page > $this->numPages() )
			error( "Page {$page} does not exist in " . $this->getTitle() );

		$edit = '';
		if ( $GLOBALS['user']->has(new Token('edit','content',$this->courseName)) ) {
            $file = $this->XML_path();
            if ( is_writable($file) )
                $edit = "<P STYLE='float: right'>[<A HREF=\"javascript:edit.skill_edit('{$this->module_id}',{$page})\">Edit</A>]</P>";
            else
                $edit = "<P STYLE='float: right'>[Source File Not Writable]</P>";

        }

		// Format the title and Select only languages necessary for the current course
		$title = array();
		$page_out = array();
		foreach ( $GLOBALS['course']->getLanguages() as $lang ) {
			if ( !isset($this->pages[$page]['content1'][$lang]) ) {
				error('This page is not available in '.$GLOBALS['iso639'][$lang].'.');
				continue;
			}

			$title[$lang] = $this->pages[$page]['title'][$lang] ? "<H2 CLASS='pageTitle'>{$this->pages[$page]['title'][$lang]}</H2>" : '';
			$page_out[$lang] = $this->pages[$page]['content1'][$lang];
		}

		return $edit.'<H1>'.$this->getTitle().'</H1>'.str_replace(' - ',' &ndash; ',lang($title,'DIV'))	. lang($page_out,'DIV');
	}

	function read_DB()
	{
		// Read in the serialized database entry
		$contents = $GLOBALS['db']->get_row_from_sql( "SELECT * FROM _skills WHERE name='{$this->name}' AND clientName='{$this->courseName}';" );
		
		// Distribute to instance variables
		$this->title = unstore( $contents['title'] );

		$ci =& get_instance();
		$result = $ci->db
			->from('_pages')
			->where('clientName',$this->courseName)
			->where('skillName',$this->name)
			->get()
			->result_array();

		foreach ( $result as $row )
		{
			// Weed out bogus pages
			if ( in_array($row['lang'],array('/T','/E','NO')) )
				continue;

			$row['number'] -= 1;
			$this->pages[$row['number']]['title'][$row['lang']] = unstore($row['title']);
			$this->pages[$row['number']]['content1'][$row['lang']] = unstore($row['contents']);

			if ( ! in_array($row['lang'],$this->languages) )
				$this->languages[] = $row['lang'];
		}
	}

	function XML_path()
	{
		return BASEPATH.'application/resources/'.$this->courseName.'/skills/'.$this->name.'.xml';
	}

	function write_XML()
	{
		$file = $this->XML_path();
		if ( ! is_writable($file) )
			if ( file_exists($file) || ! is_writable(dirname($file)) )
				fatal("File {$file} is not writable.");

		$fp = fopen($file,'w');
		fwrite($fp,$this->get_XML());
		fclose($fp);
	}

	function read_XML()
	{
		$file = $this->XML_path();
		if ( ! file_exists($file) )
		{
			fatal("File {$file} does not exist.");
		}

		$result = $this->load_XML(file_get_contents($file));

		if ( ! $result )
			fatal('Error parsing file "'.$file.'" for skill "'.$this->name.'".');
	}

	function load_XML($xml)
	{
		$this->clear_contents();

        libxml_use_internal_errors(true);
		$x = simplexml_load_string($xml);

        $errors = FALSE;
        foreach ( libxml_get_errors() as $e ) {
            error("XML Error: File: " . $this->XML_path() . ' Line: ' . $e->line . ' Column: ' . $e->column . ' - ' . $e->message);
            $errors = TRUE;
        }
        if ( $errors ) { return FALSE; }
        libxml_clear_errors();
		//var_dump($x);

        if ( gettype($x) != 'object' ) {
            throw new Exception(gettype($x) . ' - ' . print_r($x,1));
        }

        if ( ! $x ) {
            error("No XML object returned when parsing XML for skill ".$this->name);
            return FALSE;
        }

		if ( ! $x->xpath('/skill/languages') ) {
			error("Languages tag not found.");
			return FALSE;
		}
		if ( ! $x->xpath('/skill/title') ) {
			error("Title tag not found.");
			return FALSE;
		}
		if ( ! $x->xpath('/skill/pages') ) {
			error("Pages tag not found.");
			return FALSE;
		}

		foreach ( $x->xpath('/skill/languages/lang') as $lang )	{
			$this->languages[] = (string) $lang;
		}

		foreach ( $x->xpath('/skill/title') as $title ) {
			foreach ( $this->languages as $lang ) {
				$this->title[$lang] = (string) $title->$lang;
			}
		}

		foreach ( $x->xpath('/skill/pages/page') as $xpage ) {
			$page = array();
			foreach ( $this->languages as $lang ) {
				$page['title'][$lang] = (string) $xpage->title->$lang;
				$page['content1'][$lang] = (string) $xpage->content1->$lang;
			}
			$this->pages[] = $page;
		}

		return TRUE;
	}

	function get_XML()
	{
		$x = new XMLWriter(); 
		$x->openMemory();
		$x->setIndent(true);
		
		$x->startDocument('1.0','UTF-8');

		$x->startElement('skill');

		$x->startElement('languages');
		foreach ( $this->languages as $lang )
		{
			$x->writeElement('lang',$lang);
		}
		$x->endElement();

		$x->startElement('title');
		foreach ( $this->languages as $lang )
		{
			if ( isset($this->title[$lang]) )
				$x->writeElement($lang,$this->title[$lang]);
		}
		$x->endElement();

		$x->startElement('pages');
		foreach ( $this->pages as $page )
		{
			$x->startElement('page');

			$x->startElement('title');
			foreach ( $this->languages as $lang )
			{
				if ( isset($page['title'][$lang]) )
					$x->writeElement($lang,$page['title'][$lang]);
			}
			$x->endElement();

			$x->startElement('content1');
			foreach ( $this->languages as $lang )
			{
				if ( !isset($page['content1'][$lang]) )
					continue;

				// Save content
				$encoding = mb_detect_encoding($page['content1'][$lang]);
				if ( $encoding != 'ASCII' )
				{
					//var_dump($this->courseName.' '.$this->name.' '.$lang,mb_detect_encoding($page['content1'][$lang]),$page['title'][$lang]);
					//print "<HR>{$page['content1'][$lang]}<HR>";
					if ( 	   $encoding === FALSE 
						|| strpos($page['content1'][$lang],'fácil') !== FALSE
					   )
						$page['content1'][$lang] = utf8_encode($page['content1'][$lang]);
				}

				$x->writeElement($lang,$page['content1'][$lang]);
			}
			$x->endElement();

			$x->endElement();
		}
		$x->endElement();

		$x->endElement();

		$x->endDocument();

		return $x->outputMemory();
	}
}

