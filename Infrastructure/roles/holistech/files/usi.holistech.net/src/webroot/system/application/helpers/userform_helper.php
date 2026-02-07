<?

// This module is a disaster...it's pending updating...

class userForm extends Module
{
	var $fields = array();
	var $table;
	var $script_path;
	var $title;
	var $confirmation;
	var $token;

	private $query;
	private $result;
	var $ci;

	function userForm($module_id)
	{
		parent::Module($module_id);

		$this->ci =& get_instance();
		
		debug_log('New userForm('.$this->name.')');

		$this->script_path = 
			BASEPATH.'application/resources/'.$this->courseName.'/userForms/'.$this->name.'/form.php';
		//$this->view_path = 
		//	'../resources/'.COURSENAME.'/userForms/'.LANG.'/'.$this->name.'_view.php';

		if ( file_exists($this->script_path) )
			include( $this->script_path );
		else 
			error('Cannot find userForm file: '.$this->script_path);

		$this->token = new Token('module_dbform',$this->module_id,COURSENAME);
	}

	function display($lang)
	{
		if ( !$this->table || $this->fields === array() )
		{
			fatal('Invalid UserForm: '.$this->name);
		}

		$this->_query();

		$view_file = 
			BASEPATH.'application/resources/'.COURSENAME.'/userForms/'.$this->name.'/'.$lang.'.php';

		if ( ! file_exists($view_file) ) {
			error($GLOBALS['iso639'][$lang].' view for userForm "'.$this->name.'" does not exist.');
			return;
		}

		$this->ci->load->helper('form');
		$this->ci->load->plugin('survey_fields');
		$this->_set_post();


		ob_start();
		include($view_file);
		$view = ob_get_contents();
		ob_end_clean();

		return $this->ci->load->view('userForms/wrapper',array(
			'module_id' => $this->module_id,
			'userForm' => $view,
			'title' => $this->title[$lang],
		),TRUE);
	}

	function submit()
	{
		$this->_query();

		$row = $this->_get_data();

		$this->_save($row);

		if ( ! $GLOBALS['user']->enrollment_token()->has($this->token) )
			$GLOBALS['user']->enrollment_token()->issue($this->token);

		$certificate = '';
		if ( $this->token->equals($GLOBALS['course']->certify_token) )
			$certificate = '<P ALIGN=CENTER><A HREF="javascript:pager.unpaged_show(\'certificate/\'); pager.next();" STYLE="font-size: 24pt">'.say('Get Certificate').'</A><SCRIPT>pager.update_utility()</SCRIPT></P>';

		return lang($this->confirmation).$certificate;
	}
	
/* Functions to comply with Module for display */
	function getPage($page)
	{
		if ( $page != 0 )
			return;

		$out = array();
		foreach ( $this->getLanguages() as $lang )
			$out[$lang] = $this->display($lang);
	
		return lang($out);
	}

	function getTitle($wrapper='DIV')
	{
		if ( is_array($this->title) )
			return lang($this->title,$wrapper);
		if ( !empty($this->title) )
			return $this->title;
		return "<I>Untitled UserForm</I>";
	}

	function getLanguages()
	{
		if ( is_array($this->title) )
			return array_keys($this->title);
		return array('EN');
	}

	function numPages()
	{
		return 1;
	}

/* Queries the DB for the current user */
	private function _query()
	{
		if ( ! is_object($GLOBALS['user']) )
			fatal('You must be logged in to open a userForm');

		$this->query = $this->ci->db
			->from($this->table)
			->where('enrollment_id',$GLOBALS['user']->enrollment_id())
			->get();

		$this->result = $this->query->result_array();
	}

/* Writes $row to the database */
	private function _save($row)
	{
	/* Set the DB keys */
		$row['enrollment_id'] = $GLOBALS['user']->enrollment_id();

	/* Determine INSERT vs. UPDATE */
		if ( $this->query->num_rows() == 0 )
		{
			$this->ci->db->insert($this->table,$row);
		}
		else
		{
			$this->ci->db
				->where('enrollment_id',$GLOBALS['user']->enrollment_id())
				->update($this->table,$row);
		}
	}

	function _get_data()
	{
		$data = array();

		foreach ( $this->fields as $field )
		{
			$data[$field] = $this->ci->input->post($field);
		}

		return $data;
	}

/* Sets the $_POST variable to the current result set (for integration with CI Form Helper) */
	function _set_post()
	{
		foreach ( $this->fields as $field )
		{
			$_POST[$field] = ( isset($this->result[0]) ? $this->result[0][$field] : null );
		}
	}			
}

