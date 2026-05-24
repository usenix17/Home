<?
//////////////////////////////////////////////////////////////////////
//
//      user.php
//      Jason Karcz
//      Class for users 
//
//////////////////////////////////////////////////////////////////////
//
//      16 October 2003 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading user.php');
class User
{
	// Personal information variables
	var $user_id = NULL;
	var $realName;
	var $username;
	private $password;
	var $email;
	var $address1;
	var $address2;
	var $city;
	var $state;
	var $zip;
	var $phone;
	
	// Program information variables
	var $session;
	var $data;
	var $creation_time;
	var $lastRequest;
	var $disable = FALSE;
	var $roamingIP = FALSE;
	var $enrollments = array();
	var $courses = array();
	var $clearance_level = 0;

	// Login variables
	var $loginTime;
	var $loginIP;
	var $authenticated;
	var $session_id;

	// Dynamic variables
	var $code;				// What code was used to log in user.
	var $exists = false;	 		// Assume it's a new user until we find the record.
	var $token = NULL;			// This user's permission token

	// Demographic variables
	var $employer;
	var $year_born;
	var $gender;
	var $ethnicity;
	var $education;
	var $custom;

	// Editing variables
	var $fields = array(
		array('field'=>'username','label'=>'username','rules'=>'max_length[100]'),
		array('field'=>'realName','label'=>'Legal Full Name','rules'=>'required|max_length[100]'),
		array('field'=>'password','label'=>'password','rules'=>'min_length[6]|max_length[100]'),
		array('field'=>'address1','label'=>'address1','rules'=>'max_length[100]'),
		array('field'=>'address2','label'=>'address2','rules'=>'max_length[100]'),
		array('field'=>'city','label'=>'city','rules'=>'max_length[100]'),
		array('field'=>'state','label'=>'state','rules'=>'max_length[100]'),
		array('field'=>'zip','label'=>'zip','rules'=>'max_length[10]'),
		array('field'=>'phone','label'=>'phone','rules'=>'max_length[100]'),
		array('field'=>'email','label'=>'email','rules'=>'valid_email|max_length[100]'),
		array('field'=>'employer','label'=>'employer','rules'=>'max_length[100]'),
		array('field'=>'year_born','label'=>'year_born','rules'=>'max_length[4]'),
		array('field'=>'gender','label'=>'gender','rules'=>''),
		array('field'=>'ethnicity','label'=>'ethnicity','rules'=>''),
		array('field'=>'education','label'=>'education','rules'=>''),

	);
	var $custom_fields;

	var $ci;

	function User( $array = NULL )
	{
		debug_log('New User()');
		
		$this->ci =& get_instance();

		// Set the creation time (in case no user data get's loaded (new user))
		$this->creation_time = time();

		// Find out if there is user data to load
		if ( is_array($array) && ! empty($array['user_id']) )
			$this->from_array($array);

		// Populate $this->custom_fields
		$key = ( $GLOBALS['course']->link_to ? $GLOBALS['course']->link_to : COURSENAME );
		if ( file_exists(BASEPATH.'application/resources/'.$key.'/control_user/fields.php') )
			include(BASEPATH.'application/resources/'.$key.'/control_user/fields.php');
	}

	function __destruct()
	{
		debug_log("Destructing {$this->user_id}");
	}

	function as_array()
	{
		$this->ci->load->helper('phone');
		return array	( "username" => $this->username
				, "password" => $this->password
				, "realName" => $this->realName
				, "email" => $this->email
				, "phone" => Phone::unformat($this->phone)
				, "address1" => $this->address1
				, "address2" => $this->address2
				, "city" => $this->city
				, "state" => $this->state
				, "zip" => $this->zip
				, "disable" => $this->disable
				, "roamingIP" => $this->roamingIP
				, "creation_time" => $this->creation_time

			//	, "session" => store( $this->session )
				, "data" => store( $this->data )
				, "custom" => store( $this->custom )

				, "employer" => $this->employer
				, "year_born" => $this->year_born
				, "gender" => $this->gender
				, "ethnicity" => $this->ethnicity
				, "education" => $this->education
				, "lastRequest" => $this->lastRequest
				, "clearance_level" => $this->clearance_level
				);		
	}
	
	function clear_userCache()
	{
		unset($GLOBALS['userCache']['user_id'][$this->user_id]);
		unset($GLOBALS['userCache']['username'][strtolower($this->username)]);
		unset($GLOBALS['userCache']['email'][strtolower($this->email)]);
	}

	// Returns an encrypted password
	function encPasswd($pass,$salt=NULL)
	{
		$i = 5000;
		if ( $salt === NULL )
			$salt = substr(md5(uniqid(rand(),TRUE)),0,32);
		else
			$salt = substr($salt,0,32);

		while ($i--) {
			$pass = $salt.hash('sha512',$salt.$pass);
		}

		return $pass;
	}

	function exists()
	{
		return $this->exists;
	}
	
	// @param	Array $user - Array containing all user's fields
	function from_array($array)
	{
		// Populate the instance variables
		$this->user_id = $array['user_id'];
		$this->username = $array['username'];
		$this->realName = $array['realName'];
		$this->password = $array['password'];
		$this->email = $array['email'];
		$this->phone = $array['phone'];
		$this->address1 = $array['address1'];
		$this->address2 = $array['address2'];
		$this->city = $array['city'];
		$this->state = $array['state'];
		$this->zip = $array['zip'];

		$this->disable = $array['disable'];
		$this->roamingIP = $array['roamingIP'];
		$this->creation_time = $array['creation_time'];

		//$this->session = unstore( $array['session'] );
		$this->data = unstore( $array['data'] );
		$this->custom = unstore( $array['custom'] );
		$this->clearance_level = $array['clearance_level'];

		$this->employer = $array['employer'];
		$this->year_born = $array['year_born'];
		$this->gender = $array['gender'];
		$this->ethnicity = $array['ethnicity'];			
		$this->education = $array['education'];			


		$this->lastRequest = $array['lastRequest'];	
		
		// Generate the base permission token
		$this->make_token();
	
		// Load the user's enrollments
		$this->load_enrollments();
		
		// TODO: Fix section to determine _all_ of the codes this user has used
		//
		// Get the code
		//$code = $GLOBALS['db']->get_data( 'codes', 'user_id', $this->user_id, 'code' );
		//if ( $code )
		//{
		//	$this->ci->load->helper('code');
		//	$codegen = new Code();
		//	$this->code = $codegen->generate( $code );
		//}

		// Set as old
		$this->exists = true;
	}

	function make_token()
	{
		$this->token = new Token('user',$this->user_id);
	}

	function getSerial()
	{
		return substr( sprintf( "%u", crc32( $this->realName ) ), 0, 4 );
	}
	
	function hasPermission( $moduleName, $courseName=NULL )
	{
		if ( $moduleName == 'index' )
			return TRUE;

		$course = $GLOBALS['course'];
		if ( $courseName != NULL && $courseName != COURSENAME )
			$course =& getCourse($courseName);

		if ( $this->has(new Token('edit','content',($courseName===NULL?COURSENAME:$courseName)) ) )
			return TRUE;

		if ( $this->enrollment_token($courseName)->has(new Token('auth','view_all_content',($courseName===NULL?COURSENAME:$courseName)) ) )
			return TRUE;

		$token = $course->getRequirementToken($moduleName);

		if ( $token === NULL )
			return TRUE;

		if ( $this->enrollment_token($courseName)->has($token) )
			return TRUE;

		// Superusers transcend enrollments for page permissions
		if ( $this->has($token) )
			return TRUE;

		return FALSE;
	}

	function mail( $subject, $message )
	{
		if ( $this->email )
		mail( $this->email, $subject, $message, 'From: ' . $this->course->name . ' <' . $this->course->email . '>' );
	}

	function resetPassword()
	{
		if ( $GLOBALS['user']->has(new Token('edit','user_information',COURSENAME)) ) {
			$this->password = '';
			warn('Password for user "'.$this->realName.'" has been reset to "Temp1234".');
		}
		else
			fatal("You do not have permission to change this user's password.");
	}

	function getSession()
	{
		$result = $this->ci->db
			->select('session')
			->from('users')
			->where('user_id',$this->user_id)
			->get()
			->result_array();
		
		$this->session = unstore($result[0]['session']);
		
		$module = 0;
		$page = 0;
		$id = $this->enrollment_id();

		if ( isset($this->session[$id]) && is_array($this->session[$id]) ) {
			if ( isset($this->session[$id]['module']) )
				$module = $this->session[$id]['module'];
			if ( isset($this->session[$id]['page']) )
				$page = $this->session[$id]['page'];
		}

		return array(
			'module' => $module,
			'page' => $page,
		);
	}

	function saveSession($module,$page)
	{
		$this->session[$this->enrollment_id()] = array(
			'module' => $module,
			'page' => $page,
		);

		$this->ci->db
			->set('session',store($this->session))
			->where('user_id',$this->user_id)
			->update('users');
	}
	
	function setEmail( $email )
	{
		if ( $email == $this->email )
			return TRUE;


		if ( User::ensure_email_does_not_exist($email) )
		{
			$this->email = $email;
			if ( $GLOBALS['course']->login_type == 'email' && $this->exists() )
				warn(lang(array(
					'EN' => "The e-mail address for \"{$this->realName}\" has changed.
						Please use the address \"{$email}\" to log in to this course.",
					'ES' => "El correo electr&oacute;nico de \"{$this->realName}\" ha 
						cambiado.  Por favor, use \"{$email}\" para entran este curso.",
				)));
		}
		else
			return FALSE;
		
		return TRUE;
	}

	function empty_password()
	{
		return $this->password == '';
	}

	function setPassword( $plaintextPasswd )
	{
		if ( empty($plaintextPasswd) )
		{
			error("Trying to set empty password.");
			return FALSE;
		}
	
		$this->password = $this->encPasswd($plaintextPasswd);

		if ( $this->exists() )
			warn('Password for user "'.$this->realName.'" has been changed.');
		
		return TRUE;
	}

	function setUsername( $username )
	{
		if ( $username == $this->username )
			return TRUE;

		if ( empty($username) && $this->exists() ) {
		/* Find out if any of the enrolled courses are username-keyed */
			$result = $this->ci->db
				->from('enrollments e')
				->join('courses c','e.course=c.name')
				->where('e.user_id',$this->user_id)
				->where('c.login_type','username')
				->get()
				->num_rows();

			if ( $result > 0 ) {
				error("Trying to set empty username in a username-keyed course.");
				return FALSE;
			}
		}

		if ( User::ensure_username_does_not_exist($username) )
			$this->username = $username;
		else
			return FALSE;
		
		return TRUE;
	}

	function validateIP()
	{
		return $this->loginIP == getenv( "REMOTE_ADDR" ) || $this->roamingIP || $this->course->roamingIP;
	}

	function validatePasswd( $passwd )
	{
		// Blank passwords for resets get in with 'Temp1234'
		if ( $this->password == '' && $passwd == 'Temp1234' )
			return TRUE;

		if ( $this->password == '' )
			return FALSE;

		// Determine if it's an old-style or new-style password
		if ( strlen($this->password) == 160 )
			return $this->encPasswd($passwd,$this->password) == $this->password;
		else
		{
			// Find out if it's the correct password
			if ( crypt( $passwd, $this->password ) == $this->password )
			{
				// Re-encode it as a new-style password
				$this->password = $this->encPasswd($passwd);

				return TRUE;
			} else {
				return FALSE;
			}
		}
	}

/* Token manipulation functions */

	function has($token)
	{
		return $this->token->has($token);
	}

	function issue($token,$value=NULL)
	{
		return $this->token->issue($token,$value);
	}

	function revoke($token)
	{
		return $this->token->revoke($token);
	}

	function value($token)
	{
		return $this->token->value($token);
	}

	function can_edit($user)
	{
		// If there is no auth token, the user is up for grabs, or
		// If the user is trying to self-edit, allow it
		if ( $user->token === NULL || $user->token->equals($this->token) )
			return TRUE;

		// Compare clearance levels
		if ( $this->clearance_level < $user->clearance_level )
			return FALSE;

		// Go through each of the courses in which the user is enrolled
		foreach ( $user->courses as $courseName )
		{
			if ( $this->has(new Token('edit','user_information',$courseName)) )
				return TRUE;
		}

		// Go through each of the purchases that the user has attempted
		$result = $this->ci->db
			->select('course')
			->from('purchases')
			->where('user_id',$user->user_id)
			->get()
			->result();
		foreach ( $result as $r )
		{
			if ( $this->has(new Token('edit','user_information',$r->course)) )
				return TRUE;
		}

		// Find out if the current user has over-arching edit user permission
		if ( $this->has(new Token('edit','user_information')) )
			return TRUE;

		return FALSE;
	}

/* Test interaction functions */

	function count_test_attempts($module_id)
	{
		// Test attempts are stored with the current coursename instead of the originating coursename
		return $this->ci->db
			->select('attempt_id')
			->from('test_attempts')
			->where('enrollment_id',$this->enrollment_id())
			->where('module_id',$module_id)
			->get()
			->num_rows();
	}

	function get_last_test_attempt($module_id)
	{
		$result = $this->ci->db
			->from('test_attempts')
			->where('enrollment_id',$this->enrollment_id())
			->where('module_id',$module_id)
			->order_by('time')
			->get();

		if ( $result->num_rows() == 0 )
			return NULL;

		return $result->last_row('array');
	}

	function get_last_test_results_HTML($module_id)
	{
		$last = $this->get_last_test_attempt($module_id);
		return $last['html'];
	}

/* Editing functions */
	function set_post()
	{
		if ( ! empty($this->custom_fields) )
			$this->_set_custom_post();

		foreach ( $this->fields as $f )
			if ( $f['field'] != 'password' && $f['field'] != 'password_verify' )
				$_POST[$f['field']] = $this->$f['field'];
	}

	private function _set_custom_post()
	{
		$key = ( $GLOBALS['course']->link_to ? $GLOBALS['course']->link_to : COURSENAME );
		foreach ( $this->custom_fields as $f )
			$_POST[$f['field']] = ( isset($this->custom[$key][$f['field']]) ?
					$this->custom[$key][$f['field']] : '' );
	}

	function save_from_post($ignore_custom_fields=FALSE)
	{
		debug_log("user_helper/save_from_post");

		$fields = $this->fields;

		// Set either username or email to required depending on the
		// course login type.
		if ( $GLOBALS['course']->login_type == 'username' ) {
			foreach ( $fields as &$field )
				if ( $field['field'] == 'username' )
					$field['rules'] .= '|required';
		} else {
			foreach ( $fields as &$field )
				if ( $field['field'] == 'email' )
					$field['rules'] .= '|required';
		}

		// Set password field to required if it's a new user
		if ( !$this->exists() && !$GLOBALS['course']->useCAS )
			foreach ( $fields as &$field )
				if ( $field['field'] == 'password' )
					$field['rules'] .= '|required';
        
        // CAS does not need validation on real name
		if ( $GLOBALS['course']->useCAS )
			foreach ( $fields as &$field )
				if ( $field['field'] == 'realName' ||
				     $field['field'] == 'password' )
					$field['rules'] = '';
			
		// Add password verify to the validation list if a password is set or if it's a new user
		if ( (!$this->exists() && !$GLOBALS['course']->useCAS) || $this->ci->input->post('password') != '' )
			$fields[] = array('field'=>'password_verify','label'=>'Password Verify','rules'=>'required|matches[password]');

		// Add custom fields to the validation list
		if ( ! $ignore_custom_fields && ! empty($this->custom_fields) )
			$fields = array_merge($fields,$this->custom_fields);

		// Run validation (no validation on CAS users)
		$this->ci->load->library('form_validation');
		$this->ci->form_validation->set_rules($fields);
		if ( !$GLOBALS['course']->useCAS && $this->ci->form_validation->run() == FALSE )
        {
            debug_log('User save_from_post validation failed.');
			return FALSE;
        }

		$change = FALSE;

		// Save the custom fields
		if ( ! empty($this->custom_fields) )
			$change = $this->_custom_save();

		// Save the regular fields
		foreach ( $this->fields as $f )
		{
			$val = $this->ci->input->post($f['field']);

			if ( $val === FALSE )
				continue;

			if ( $f['field'] == 'year_born' )
			{
				if ( empty($val) )
					$val = NULL;
			}

			if ( $f['field'] == 'password' )
			{
				if ( !empty($val) )
					if ( $this->setPassword($val) )
						$change = TRUE;
					else
						return FALSE;
			}
			elseif ( $f['field'] == 'email' )
			{
				if ( $val != $this->email )
						$change = TRUE;

				if ( ! $this->setEmail($val) )
					return FALSE;
			}
			elseif ( $f['field'] == 'username' )
			{
				if ( $val != $this->username )
					$change = TRUE;

				if ( ! $this->setUsername($val) )
					return FALSE;
			}
			else {
				if ( $this->{$f['field']} != $val )
					$change = TRUE;
				$this->$f['field'] = $val;
			}
		}

		if ( $this->user_id === NULL ) {
			if ( ! User::ensure_username_does_not_exist($this->username) )
				return FALSE;
			if ( ! User::ensure_email_does_not_exist($this->email) )
				return FALSE;
			if ( empty($this->email) && empty($this->username) ) {
				error(lang(array(
					'EN' => 'You must give either an e-mail address or a username.',
					'ES' => 'Debe proveer o un correo electr&oacute;nico o un usuario.',
				)));
				return FALSE;
			}
			if ( $this->insert_row() ) {
                debug_log('User created.');
				//warn("User Created.");
            }
		} else {
			if ( $this->update_row() && $change )
				warn("Changes Saved.");
		}

		return $this->user_id;
	}

	private function _custom_save()
	{
		$change = FALSE;
		$key = ( $GLOBALS['course']->link_to ? $GLOBALS['course']->link_to : COURSENAME );
		foreach ( $this->custom_fields as $f )
		{
			$val = $this->ci->input->post($f['field']);

			if ( $val !== FALSE ) {
				if ( !isset($this->custom[$key]) || 
					!isset($this->custom[$key][$f['field']]) ||
					$val != $this->custom[$key][$f['field']] )
					$change = TRUE;
				$this->custom[$key][$f['field']] = $val;
			}
		}
		return $change;
	}

	function insert_row()
	{
		debug_log("Creating User");

		// Write to the MySQL Database
		$this->ci->db
			->insert('users',$this->as_array());		
		
		// Update the global user cache to hold the newest version
		$this->clear_userCache();

		$this->user_id = $this->ci->db->insert_id();
		$this->make_token();
		$this->exists = TRUE;

		return TRUE;
	}

	function update_row()
	{
		debug_log("Saving User {$this->user_id}");
		if ( $this->user_id === NULL ) {
			//error( "Cannot save empty user." );
			return FALSE;
		}

		if ( ! $GLOBALS['user']->can_edit($this) ) {
			error('You are not allowed to edit this user ('.$this->username.').');
			return FALSE;
		}
		
		// Write to the MySQL Database
		$this->ci->db
			->where('user_id',$this->user_id)
			->update('users',$this->as_array());		
		
		// Update the global user cache to hold the newest version
		$this->clear_userCache();

		return TRUE;
	}

	static function ensure_username_does_not_exist($username) 
	{
		$ci =& get_instance();
		debug_log('user_helper/ensure_username_does_not_exist');
		if ( ! empty($username) ) {
			$result = $ci->db
				->from('users')
				->where('username',$username)
				->get();
			if ( $result->num_rows() != 0 )	{
				error(lang(array(
					'EN' => 'The username "'.$username.'" is already used.',
					'ES' => 'El usuario "'.$username.'" ya est&aacute; usado.',
				)));
				return FALSE;
			}
		}

		return TRUE;
	}

	static function ensure_email_does_not_exist($email) 
	{
		$ci =& get_instance();
		debug_log('user_helper/ensure_email_does_not_exist');
		if ( ! empty($email) ) {
			$result = $ci->db
				->from('users')
				->where('email',$email)
				->get();
			if ( $result->num_rows() != 0 )	{
				error(lang(array(
					'EN' => 'The e-mail address "'.$email.'" is already used.',
					'ES' => 'El correo electr&oacute;nico "'.$email.'" ya est&aacute; usado.',
				)));
				return FALSE;
			}
		}

		return TRUE;
	}


/* Enrollment Functions */

	function load_enrollments()
	{
		$this->enrollments = array();
		$this->courses = array();

		$result = $this->ci->db
			->from('enrollments e')
			->join('courses c','e.course=c.name')
			->join('course_groups cg','c.group=cg.group','left')
			->where('e.user_id',$this->user_id)
			->get()
			->result_array();

		$courses = array();

		foreach ( $result as $row )
		{
			$row['course'] = strtolower($row['course']);

			$this->enrollments[$row['enrollment_id']] = $row;

			if ( $row['status'] = 'Enrolled' )
				$this->courses[] = $row['course'];
		}

		$this->courses = array_unique($this->courses);
	}

	function enroll($coursename,$code=NULL)
	{

		// Make sure the user exists
		if ( ! $this->exists() ) {
			error("Cannot enroll unsaved user.");
			return FALSE;
		}

		// Ensure the user is not actively enrolled in the course
		if ( ! $this->ensure_not_already_enrolled($coursename) )
			return FALSE;

		$this->ci->db->insert('enrollments',array(
			'user_id' => $this->user_id,
			'course' => $coursename,
			'code' => $code,	
		));
		$this->ci->db
			->set('date','now()',FALSE)
			->where('enrollment_id',$this->ci->db->insert_id())
			->update('enrollments');
		$this->issue(new Token('course',$coursename));

		$course =& getCourse($coursename);
		//warn("\"{$this->realName}\" is now enrolled in '{$course->displayName}'.");

		$this->load_enrollments();

		//$this->ci->load->plugin('email');
		//email('','"'.$this->realName.'" has just enrolled in "'.$coursename.'" using code "'.$code.'".');

		return TRUE;
	}

	function ensure_not_already_enrolled($coursename)
	{
		if ( $this->is_enrolled($coursename,TRUE) ) {
			$course =& getCourse($coursename);
			error(lang(array(
				'EN' => "\"{$this->realName}\" is already enrolled in 
						\"{$course->displayName}\".",
				'ES' => "\"{$this->realName}\" ya se matricul&oacute; en
						\"{$course->displayName}\".",
			)));
			return FALSE;
		}

		return TRUE;
	}

	function unenroll($enrollment_id)
	{
		$this->_set_enrollment_status($enrollment_id,'Unenrolled');
		$this->revoke(new Token('course',$coursename));
	}

	function void($enrollment_id)
	{
		$this->_set_enrollment_status($enrollment_id,'Voided');
		$this->revoke(new Token('course',$coursename));
	}

	private function _set_enrollment_status($enrollment_id,$status)
	{
		$this->ci->db
			->set('status',$status)
			->where('enrollment_id',$enrollment_id)
			->update('enrollments');

		$this->load_enrollments();
	}

	function is_enrolled($coursename, $strict=FALSE)
	{
		// If the user is registered in the course
		if ( $this->enrollment_id($coursename) !== NULL )
		{
			// Make sure the registration has not expired
			return TRUE;
		}

		// If the can edit the course (this validates superusers and admins)
		if ( $this->has(new Token('edit',$coursename)) && ! $strict )
			return TRUE;

		return FALSE;
	}
		
	function enrollment_id($courseName=NULL)
	{
		if ( $courseName === NULL )
			$courseName = COURSENAME;

		$courseName = strtolower($courseName);
        $course =& getCourse($courseName);
		$enrollment_id = NULL;

        foreach ( $this->enrollments as $id => $enrollment ) {
            if ( $enrollment['course'] == $courseName ) {
                $age = (time() - strtotime($enrollment['date'])) / 86400;
                $certificate_age = NULL;
                if ( $enrollment['status'] == 'Completed' && $enrollment['certification_time'] )
                    $certificate_age = (time() - strtotime($enrollment['certification_time'])) / 86400;

                // Is this enrollment expired?
                if ( $course->expire && $certificate_age > $course->expire ) {
                    continue;
                }

                // Is this enrollment past the bdayExpire threshold?
                if ( $course->bdayExpire && $age > $course->bdayExpire ) {
                    continue;
                }

                // This is a valid enrollment
                $enrollment_id = $enrollment['enrollment_id'];
            }
        }

		return $enrollment_id;
	}		

	function enrollment_token($courseName=NULL)
	{
		$enrollment_id = $this->enrollment_id($courseName);

		if ( $enrollment_id === NULL )
			return NULL;

		return new Token('enrollment',$enrollment_id);
	}

	function percent_complete($enrollment_id=NULL)
	{
		if ( $enrollment_id === NULL )
			$enrollment_id = $this->enrollment_id();

	/* Get the enrollment's course */
		$course =& $GLOBALS['course'];
		if ( $this->enrollments[$enrollment_id]['course'] != COURSENAME )
			$course =& getCourse($this->enrollments[$enrollment_id]['course']);
		$enrollment_token = $this->enrollment_token($course->name);

	/* Count completed vs. total modules */
		$completed = 0;
		$total = 0;
		if ( count($course->syllabus) )
		foreach ( $course->syllabus as $m ) {
			$token = $course->getProvisionToken($m['module']);
			$total++;
			if ( $enrollment_token->has($token) )  {
				$completed++;
            }
		}

		if ( $total == 0 )
			return 0;

		return sprintf('%.1f',$completed/$total * 100);
	}

	function report_card($enrollment_id=NULL)
	{
		if ( $enrollment_id === NULL )
			$enrollment_id = $this->enrollment_id();

	/* Get the enrollment's course and enrollment token */
		$course =& $GLOBALS['course'];
		if ( $this->enrollments[$enrollment_id]['course'] != COURSENAME )
			$course =& getCourse($this->enrollments[$enrollment_id]['course']);
		$enrollment_token = $this->enrollment_token($course->name);

	/* Get the highscores from the database */
		$result = $this->ci->db
			->select('module_id,max(score) as high_score',FALSE)
			->from('test_attempts')
			->where('enrollment_id',$enrollment_id)
			->group_by('module_id')
			->get()
			->result_array();
		$highscores = array();
		foreach ( $result as $r )
			$highscores[$r['module_id']] = $r['high_score'];

	/* Find what's complete and list scores */
		$report_card = array();
		if ( count($course->syllabus) )
		foreach ( $course->syllabus as $m ) {
			$module = getModule($m['module_id'],$course->name);
			$line = array(
				'name' => $module->getTitle(),
				'module_id' => $m['module_id'],
				'enrollment_id' => $enrollment_id,
			);
			$token = $course->getProvisionToken($m['module']);
			if ( $enrollment_token->has($token) ) {
				$line['passed'] = TRUE;
				$line['score'] = $enrollment_token->value($token);
			} else {
				$line['passed'] = FALSE;
				$line['score'] = NULL;
				if ( isset($highscores[$m['module_id']]) )
					$line['score'] = $highscores[$m['module_id']];
			}
			$report_card[] = $line;
		}

		return $report_card;
	}

/* Certification */

	function has_certified($enrollment_id=NULL)
	{
		if ( $enrollment_id === NULL )
			$enrollment_id = $this->enrollment_id();

		return $this->enrollments[$enrollment_id]['certification_time'] !== NULL;
	}

	function can_certify($enrollment_id=NULL)
	{
		if ( $enrollment_id === NULL )
			$enrollment_id = $this->enrollment_id();

	/* Get the enrollment token */
		$enrollment_token = new Token('enrollment',$enrollment_id);

	/* Get the enrollment's course */
		$course =& $GLOBALS['course'];
		if ( !empty($this->enrollments[$enrollment_id]['course']) && $this->enrollments[$enrollment_id]['course'] != COURSENAME )
			$course =& getCourse($this->enrollments[$enrollment_id]['course']);

		if ( $course->certify_token === NULL ) {
			$this->enrollments[$enrollment_id]['course_certifies'] = FALSE;
			return FALSE;
		}
		else
			$this->enrollments[$enrollment_id]['course_certifies'] = TRUE;
		
		return $enrollment_token->has($course->certify_token);
	}
		
	function certify($enrollment_id=NULL)
	{
		if ( $enrollment_id === NULL )
			$enrollment_id = $this->enrollment_id();

	/* Find out if they've already certified */
		if ( $this->has_certified() ) {
			error("{$this->realName} already has a certificate for that enrollment.");
			return;
		}

	/* Get the enrollment's course */
		$course =& $GLOBALS['course'];
		if ( $this->enrollments[$enrollment_id]['course'] != COURSENAME )
			$course =& getCourse($this->enrollments[$enrollment_id]['course']);

	/* Gather user's information */
		$name = $this->realName;
		$form = $this->getSerial();

	/* Set up PDF */
		$this->ci->load->library('fpdf16/fpdf');

	       	$pdf = new FPDF( 'P', 'in', 'Letter' );
		$pdf->SetAutoPageBreak(FALSE);
		$pdf->SetDisplayMode('fullpage');
		$pdf->SetCreator('http://www.az-hospitality.org/');
		$pdf->SetTitle('Certificate of Completion');

	/* Load certificate script */
		// This file will use $pdf, $imagePath, $name, $form to create the certificate
		$imagePath = BASEPATH.'application/resources/'.$course->name.'/certificate/';
		$path = BASEPATH.'application/resources/'.$course->name.'/certificate/'.$course->certificate;
		if ( empty($course->certificate) || !file_exists($path) )
			fatal('Certificate source for "'.$course->name.'" does not exist.');

		require_once($path);
		
	/* Archive PDF in certificate file */
		$certificate = $pdf->output('','S');
		if ( ! is_writable(dirname($this->certificate_path($enrollment_id))) )
			fatal("Certificate storage path for course '{$course->name}' is not writable. ");
		file_put_contents($this->certificate_path($enrollment_id),$certificate);

	/* Update enrollment */
		$this->ci->db
			->set('certification_time','now()',FALSE)
			->set('status','Completed')
			->where('enrollment_id',$enrollment_id)
			->update('enrollments');
		$this->load_enrollments();

	/* Send the user an email */
		$this->email_certificate($enrollment_id);
	}

	function certificate_path($enrollment_id=NULL)
	{
		if ( $enrollment_id === NULL )
			$enrollment_id = $this->enrollment_id();

		return BASEPATH.'application/resources/'
			.$this->enrollments[$enrollment_id]['course'].'/certificate/file_storage/'.$enrollment_id.'.pdf';
	}

	function email_certificate($enrollment_id=NULL)
	{

		if ( !$this->email ) {
			error('No e-mail address is set for "'.$this->realName.'".  The certificate was not emailed.');
			return;
		}

		if ( $enrollment_id === NULL )
			$enrollment_id = $this->enrollment_id();

	/* Ensure the user has certified */
		if ( ! $this->has_certified($enrollment_id) ) {
			error('You cannot email a certificate that has yet to be generated.');
			return;
		}

	/* Read the certificate */
		$certificate = file_get_contents($this->certificate_path());

	/* Get the enrollment's course */
		$course =& $GLOBALS['course'];
		if ( $this->enrollments[$enrollment_id]['course'] != COURSENAME )
			$course =& getCourse($this->enrollments[$enrollment_id]['course']);

	/* Send the email */
		require_once(BASEPATH.'application/libraries/PHPMailer_v5.1/class.phpmailer.php');
		$mail = new PHPMailer(TRUE);
		//$mail->IsSMTP(); // telling the class to use SMTP
		$mail->IsSendmail(); // telling the class to use PHP Mail
		$from = ( empty($course->tech_support_email) ? $course->email : $course->tech_support_email );

		try {
			//$mail->Host       = "mailgate.nau.edu"; // SMTP server
			//$mail->SMTPDebug  = 2;                     // enables SMTP debug information (for testing)
			$mail->AddAddress($this->email,$this->realName);
			//$mail->AddBCC('Jason.Karcz@nau.edu', 'Jason Karcz');
			//$mail->AddBCC($from);
			$mail->SetFrom($from);
			$mail->Subject = $course->text_name().' - Certificate';
			$mail->Body = 'Congratulations on completing the course!  Attached is your certificate.';
			$mail->AddStringAttachment($certificate,'Certificate.pdf'
				,'base64','application/pdf');
			if ( $mail->Send() )
				warn("Certificate e-mailed to {$this->email}.");
		} catch (Exception $e) {
			error('Error emailing certificate: '.$e->getMessage());
		}
	}	
}
