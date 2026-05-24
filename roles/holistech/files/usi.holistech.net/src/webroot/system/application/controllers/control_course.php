<?php

/**
 * Course Control Panel
 */
class Control_Course extends Controller
{
	var $course;
	var $fields = array(
		array('field'=>'layout','label'=>'layout','rules'=>''),
		array('field'=>'quota','label'=>'Quota','rules'=>'is_natural'),
		array('field'=>'email','label'=>'Primary Contact Email','rules'=>'required|valid_email'),
		array('field'=>'tech_support_email','label'=>'Tech Support Email','rules'=>'valid_email'),
		array('field'=>'useCodes','label'=>'Use Registration Codes','rules'=>''),
		array('field'=>'openReg','label'=>'Allow Open Registration','rules'=>''),
		array('field'=>'expire','label'=>'Days after completion course expires','rules'=>'is_natural'),
		array('field'=>'bdayExpire','label'=>'Days after registration course expires','rules'=>'is_natural'),
		array('field'=>'useEbiz','label'=>'Use NAU EBusiness','rules'=>''),
		array('field'=>'useCAS','label'=>'Use CAS','rules'=>''),
		array('field'=>'cas_host','label'=>'CAS Host','rules'=>''),
		array('field'=>'cas_port','label'=>'CAS Port','rules'=>''),
		array('field'=>'cas_context','label'=>'CAS Context','rules'=>''),
		array('field'=>'cas_email_template','label'=>'CAS Email Template','rules'=>''),
		array('field'=>'displayName','label'=>'Course Title','rules'=>'required'),
		array('field'=>'roamingIP','label'=>'Allow Roaming IP','rules'=>''),
		array('field'=>'disableCorrectAnswers','label'=>'Disable Correct Answers','rules'=>''),
		array('field'=>'minScore','label'=>'Minimum Score on Tests','rules'=>'is_natural'),
		array('field'=>'languages','label'=>'Languages Offered','rules'=>'valid_languages'),
		array('field'=>'group','label'=>'Grouping','rules'=>'maxlength[20]'),
		array('field'=>'login_type','label'=>'Login Type','rules'=>''),
		array('field'=>'roster_file','label'=>'Roster File','rules'=>'maxlength[255]'),
		array('field'=>'roster_username_field','label'=>'Roster Username Field','rules'=>'maxlength[100]'),
		array('field'=>'roster_email_address_field','label'=>'Roster Email Address Field','rules'=>'maxlength[100]'),
		array('field'=>'roster_real_name_fields','label'=>'Roster Real Name Fields','rules'=>'maxlength[100]'),
		array('field'=>'roster_report_filter_fields','label'=>'Roster Report Filter Fields','rules'=>'maxlength[255]'),
		array('field'=>'roster_report_restrict_fields','label'=>'Roster Report Restrict Fields','rules'=>'maxlength[255]'),
	);
	var $ebiz_fields = array(
		array('field'=>'lmid','label'=>'LMID','rules'=>'required'),
		array('field'=>'ebizURL','label'=>'URL','rules'=>'required'),
		array('field'=>'price','label'=>'Price','rules'=>'required|is_natural'),
		array('field'=>'contactInfo','label'=>'Contact Information','rules'=>'required'),
	);

	function Control_Course()
	{
		parent::Controller();

		$this->load->library('session');
		$this->session->set_userdata('control_selected','control_course');
		$this->load->helper('formtable');
		$this->load->helper('form');
	}

	function index()
	{
		$coursename = $this->session->userdata('control_selected_course');

		if ( $coursename === FALSE )
			$coursename = COURSENAME;

		$this->edit($coursename);
	}

	function edit($coursename=NULL)
	{
		debug_log("Control_Course/edit/{$coursename}");
		$this->_get_course($coursename);
		$this->session->set_userdata('control_selected_course',$this->course->name);
		$this->_set_post();

		$this->load->view('control/course/edit',array(
			'course_list' => $this->_get_course_list(),
			'coursename' => $coursename,
			'course' => $this->course,
			'layouts' => $this->_get_layouts(),
		));
	}

	function edit_syllabus($coursename=NULL)
	{
		if ( $coursename === NULL )
			$coursename = $this->session->userdata('control_selected_course');
		if ( $coursename === FALSE )
			$coursename = COURSENAME;

		debug_log("Control_course/edit/{$coursename}");
		$this->_get_course($coursename);
		$this->session->set_userdata('control_selected_course',$this->course->name);

		$this->load->view('control/course/edit_syllabus',array(
			'course' => $this->course,
		));
	}


	function create()
	{
		$this->output->set_output('This function not yet implemented.');
	}

	function save()
	{
		debug_log("Control_Course/save");
		$coursename = $this->input->post('coursename');

		if ( $coursename === FALSE )
			return;

		$this->_get_course($coursename);
		$this->_save();
		$this->edit($coursename);
	}

	/**
	 * Loads the requested or current user for editing
	 */
	private function _get_course($coursename)
	{
		debug_log("Control_Course/_get_course/{$coursename}");

		if ( $GLOBALS['user']->has(new Token('edit','content',$coursename)) )
		{
			$course = getCourse($coursename);
			$this->course =& $course;
		}
		else
			fatal('You do not have permission to edit the course: '.$coursename);
	}

	private function _set_post()
	{
		if ( ! isset($this->course) )
			fatal('You must run _get_course before _set_post');

		foreach ( $this->fields as $f )
			$_POST[$f['field']] = $this->course->$f['field'];

		foreach ( $this->ebiz_fields as $f )
			$_POST[$f['field']] = $this->course->$f['field'];
	}

	private function _save()
	{
		debug_log("Control_Course/_save");

		$fields = $this->fields;

		if ( $this->input->post('useEbiz') !== FALSE )
			$fields = array_merge($fields,$this->ebiz_fields);

		// Run validation
		$this->load->library('form_validation');
		$this->load->plugin('valid_languages');
		$this->form_validation->set_rules($fields);
		if ( $this->form_validation->run() == FALSE )
		{
			error('There was a problem saving the changes.');
			return FALSE;
		}

		// Get the original setting for layout 
		$layout = $this->course->layout;

		// Save the regular fields
		foreach ( $fields as $f )
		{
			$val = $this->input->xss_clean($this->input->post($f['field']));

			$this->course->$f['field'] = $val;
		}

		$this->course->saveData();

		// Determine if a refresh is needed
		if ( $layout != $this->course->layout )
			$this->_immediate_refresh();
	}

	private function _get_course_list()
	{
		$result = $this->db
			->select('name,displayName')
			->from('courses')
			->order_by('name')
			->get()
			->result_array();

		$courses = array();
		foreach ( $result as $row )
		{
			if ( $GLOBALS['user']->has(new Token('edit','content',$row['name'])) )
				$courses[$row['name']] = "{$row['name']} - {$row['displayName']}";
		}

		return $courses;
	}

	private function _get_layouts()
	{
		$layouts = '';

		foreach ( glob(BASEPATH.'application/layouts/*') as $layout )
		{
			$name = str_replace('/','',str_replace(BASEPATH.'application/layouts/','',$layout));

			$layouts[$name] = $name;
		}

		return $layouts;
	}

	private function _immediate_refresh()
	{
		$this->session->set_flashdata('unpaged','control/');
		print "<SCRIPT>window.location.reload()</SCRIPT>";exit;
	}

}

