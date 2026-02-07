<?php

/**
 * User Control Panel
 */
class Control_User extends Controller
{
	var $user;

	function Control_User()
	{
		parent::Controller();

		$this->load->library('session');
		$this->session->set_userdata('control_selected','control_user');
		$this->load->helper('formtable');
	}

	function index()
	{
		//$user_id = $this->session->userdata('control_selected_user');

		//if ( $user_id === FALSE )
		//	$user_id = $GLOBALS['user']->user_id;

		$this->edit('user_id',$GLOBALS['user']->user_id);
	}

	function edit($key_type,$key)
	{
		debug_log("control_user/edit/{$key_type}/{$key}");

		// Don't continue if getting user fails.
		if ( ! $this->_get_user($key_type,$key) )
			return;

		//if ( ! empty($key) )
		//	$this->session->set_userdata('control_selected_user',$this->user->user_id);
		$reset_button = $GLOBALS['user']->user_id != $this->user->user_id;
		
		$this->user->set_post();

		$this->load->view('control/user/edit',array(
			'custom' => $this->_get_custom_view(),
			'user' => $this->user,
			'reset_button' => $reset_button,
		));
	}

	function enrollments($user_id=NULL)
	{
		//$user_id = $this->session->userdata('control_selected_user');

		if ( $user_id === NULL )
			$user_id = $GLOBALS['user']->user_id;

		$this->list_enrollments('user_id',$user_id);
	}

	function list_enrollments($key_type,$key)
	{
		debug_log("control_user/list_enrollments/{$key_type}/{$key}");

		// Don't continue if getting user fails.
		if ( ! $this->_get_user($key_type,$key) )
			return;

		//if ( ! empty($key) )
		//	$this->session->set_userdata('control_selected_user',$this->user->user_id);

	/* Get unenrolled courses that are available to enroll */
		$available_courses = $this->_get_available_courses();

	/* Calculate completion percentages, certification status, and grouping for enrollments */
		$grouped_enrollments = array();
		foreach ( $this->user->enrollments as $key => $e ) {
			$grouped_enrollments[$e['group']][$key] = $e;
			$grouped_enrollments[$e['group']][$key]['can_certify'] = $this->user->can_certify($key);
			$grouped_enrollments[$e['group']][$key]['has_certified'] = $this->user->has_certified($key);
			$grouped_enrollments[$e['group']][$key]['percent_complete'] = $this->user->percent_complete($key);
			$grouped_enrollments[$e['group']][$key]['course_certifies'] = $this->user->enrollments[$key]['course_certifies'];
		}
	
	/* Calculate the union of the groupings */
		$groups = array();
		foreach ( $grouped_enrollments as $group => $enrollments ) {
			$first = array_shift(array_keys($enrollments));
			$groups[$group] = $enrollments[$first]['group_description'];
		}
		foreach ( $available_courses as $group => $course ) 
			$groups[$group] = $course[0]['group_description'];
			
		$this->load->view('control/user/enrollments',array(
			'grouped_enrollments' => $grouped_enrollments,
			'user' => $this->user,
			'available_courses' => $available_courses,
			'groups' => $groups,
		));	
	}

	function show_enrollment_details($enrollment_id)
	{
	/* Get the user */
		if ( ! $this->_get_user_from_enrollment_id($enrollment_id) )
			return;

	/* Calculate the report card */
		$report_card = $this->user->report_card($enrollment_id);

		$this->load->view('control/user/report_card',array('report_card'=>$report_card));
	}

	function show_module_details($enrollment_id,$module_id)
	{
	/* Get the user */
		if ( ! $this->_get_user_from_enrollment_id($enrollment_id) )
			return;

	/* Get the test attempts */
		$attempts = $this->db
			->from('test_attempts')
			->where('enrollment_id',$enrollment_id)
			->where('module_id',$module_id)
			->get()
			->result_array();

		$this->load->view('/control/user/test_attempts',array('attempts' =>$attempts));
	}

	function change_password()
	{
		$this->user =& $GLOBALS['user'];

		if ( ! $this->user->empty_password() )
			fatal('This can only be used for users with reset passwords.');

		$this->load->view('control/user/change_password',array(
			'user' => $this->user,
		));
	}

	function do_reset_password()
	{
		$this->user =& $GLOBALS['user'];

		if ( ! $this->user->empty_password() )
			fatal('This can only be used for users with reset passwords.');

		// Set other required fields from $GLOBALS['user']
		$_POST['realName'] = $GLOBALS['user']->realName;
		$_POST['email'] = $GLOBALS['user']->email;
		$_POST['username'] = $GLOBALS['user']->username;

		// Save the changes
		$return = $this->user->save_from_post(TRUE);

		// If the save failed, show the form again
		// Otherwise, init_pager
		if ( $return === FALSE )
			$this->change_password();
		else
			$this->output->set_output('<SCRIPT>load("/users/init_pager","#HIDDEN");</SCRIPT>');
	}	

	function create()
	{
		$this->edit('','');
		return;
	}

	function save()
	{
		debug_log("control_user/save");
		$user_id = $this->input->post('user_id');

		$this->_get_user('user_id',$user_id);
		
		$new = FALSE;
		if ( empty($user_id) )
			$new = TRUE;

		$return = $this->user->save_from_post();
		if ( $return !== FALSE )
			$user_id = $return;

		if ( $new && $user_id !== FALSE )
			$this->user->enroll(COURSENAME);

		$this->edit('user_id',$user_id);
	}

	function enroll($user_id,$coursename)
	{
		$this->_get_user('user_id',$user_id);

		if ( $this->user->user_id != $user_id )
			fatal('Failed to get user with id: '.$user_id);

		if ( $GLOBALS['user']->has(new Token('auth','create_users',$coursename)) )
			$this->user->enroll($coursename);

		$this->output->set_output("<SCRIPT>load('/control_user/list_enrollments/user_id/{$user_id}',\$J('#control_user-enrollments').parent());</SCRIPT>");
	}

	function unenroll($enrollment_id)
	{
		$this->_change_enrollment($enrollment_id,'Unenrolled');
	}

	function reenroll($enrollment_id)
	{
		$this->_change_enrollment($enrollment_id,'Enrolled');
	}

	private function _change_enrollment($enrollment_id,$status)
	{
		$result = $this->db
			->select('user_id')
			->from('enrollments')
			->where('enrollment_id',$enrollment_id)
			->get()
			->result_array();

		if ( ! count($result) )
			fatal('Could not find enrollment '.$enrollment_id);

		$this->_get_user('user_id',$result[0]['user_id']);

	}
		
	private function _get_custom_view()
	{
		if ( ! isset($this->user) )
			fatal('You must run _get_user before _get_custom_view');

		$key = ( $GLOBALS['course']->link_to ? $GLOBALS['course']->link_to : COURSENAME );
		return lang_includes(BASEPATH.'application/resources/'.$key.'/control_user/view','TBODY');
	}

	/**
	 * Loads the requested or current user for editing
	 */
	private function _get_user($key_type,$key)
	{
		debug_log("control_user/_get_user/{$key_type}/{$key}");
		$user =& getUser($key_type,$key);

		//if ( ! $user->exists() ) {
		//	error("That user does not exist.");
		//	return FALSE;
		//}

		if ( $GLOBALS['user']->can_edit($user) || ! $user->exists() )
			$this->user =& $user;
		else
		{
			error('You are not allowed to edit this user.');
			return FALSE;
		//	$this->user =& $GLOBALS['user'];
		}

		return TRUE;
	}

	
	private function _get_user_from_enrollment_id($enrollment_id)
	{
		$result = $this->db
			->select('user_id')
			->from('enrollments')
			->where('enrollment_id',$enrollment_id)
			->get()
			->result_array();

		$this->_get_user('user_id',$result[0]['user_id']);

		return TRUE;
	}

	private function _get_available_courses()
	{
		$result = $this->db
			->from('courses c')
			->join('course_groups cg','c.group=cg.group','left')
			->get()
			->result_array();

		$courses = array();
		foreach ( $result as $c ) {
			if ( $GLOBALS['user']->has(new Token('auth','create_users',$c['name'])) && 
				! $this->user->is_enrolled($c['name'],TRUE) ) {
				$courses[$c['group']][] = $c;
			}
		}

		return $courses;
	}
}
