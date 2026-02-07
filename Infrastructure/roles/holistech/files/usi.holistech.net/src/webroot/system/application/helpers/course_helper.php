<?
//////////////////////////////////////////////////////////////////////
//
//      course.php
//      Jason Karcz
//      Class for courses 
//
//////////////////////////////////////////////////////////////////////
//
//      6 April 2004 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading course.php');
class Course
{
	// Exterior information variables
	var $name;			// Name of course
	var $email;			// Email address of primary contact
	var $tech_support_email;	// Tech Support Email address
	var $syllabus;			// Ordered array of modules 
	var $minscore;			// Minimum percentage to pass tests
	var $layout;			// Layout file to use
	var $certificate;		// Certificate file to use
	var $quota;			// Largest number of users
	var $group;			// Logical grouping of courses
	var $population;		// Current number of users
	var $greyModules;		// Defines whether or not to show future modules (greyed out)
	var $useCodes;			// Defines whether to show login code dialog on login page
	var $openReg;			// Defines whether to allow open registration
	var $expire;			// Days after certification when an account expires (0=never)
	var $bdayExpire;		// Days after birthday when an account expires (0=never)
	var $lmid;			// EBusiness LMID
	var $ebizURL;			// EBusiness URL
	var $useEbiz;			// Defines whether or not to use NAU EBusiness
    var $useCAS;        // Use a CAS for authentication defined by $cas_host, $cas_port, and $cas_context.  Automatically implies $openReg
    var $cas_host;      
    var $cas_port;
    var $cas_context;
    var $cas_email_template;
	var $displayName;		// Display Name of the course
	var $price;			// Price per code
	var $contactInfo;		// Contact information for EBusiness
	var $roamingIP;			// Allows roaming IPs for all users
	var $disableCorrectAnswers;	// Disables altering test questions after they've been marked correct
	var $languages;			// Languages to display
	var $login_type;		// Sets how a user identifies themselves for login
	var $link_to;			// Provides the key for custom user data
	var $certify_token;		// Token the user must have to get a certificate
    var $roster_file;       // an absolute path local to the webserver
    var $roster_username_field; // expects the name of the header for the column in the CSV that corresponds to the username in the FQOT database
    var $roster_email_address_field; // expects the name of the header for the column in the CSV that corresponds to the email address in the FQOT database
    var $roster_real_name_fields; // expects a comma-separate list of names of the header for the columns in the CSV that correspond to the user's real name
    var $roster_report_filter_fields; // expects a comma-separate list of names of the header for the columns in the CSV that should have filter dropdown on the Roster Report
    var $roster_report_restrict_fields; // expects a comma-separate list of names of the header for the columns in the CSV that require authentication tokens in order for their contents to be displayed.  Specifically, the required token would be ('roster_auth', '<FIELD_NAME>=<FIELD_VALUE>', COURSENAME) to see rows with <FIELD_NAME>=<FIELD_VALUE>

	var $ci;

	function Course( $name )
	{
		debug_log('New Course('.$name.')');
		$this->name = $name;
		$this->ci =& get_instance();

		// Fetch the DB row
		$course = $GLOBALS['db']->get_row( "courses", "name", $name );
		
		if ( $course['name'] != "" )
		// If the course record exists
		{
			$this->name = $course['name'];
			$this->email = $course['email'];
			$this->tech_support_email = $course['tech_support_email'];
			$this->minScore = $course['minScore'];
			$this->layout = $course['layout'];
			$this->certificate = $course['certificate'];
			$this->quota = $course['quota'];
			$this->group = $course['group'];
			$this->greyModules = $course['greyModules'];
			$this->useCodes = $course['useCodes'];
			$this->openReg = $course['openReg'];
			$this->expire = $course['expire'];
			$this->bdayExpire = $course['bdayExpire'];
			$this->lmid = $course['lmid'];
			$this->ebizURL = $course['ebizURL'];
			$this->useEbiz = $course['useEbiz'];
			$this->useCAS = $course['useCAS'];
			$this->cas_host = $course['cas_host'];
			$this->cas_port = $course['cas_port'];
			$this->cas_context = $course['cas_context'];
			$this->cas_email_template = $course['cas_email_template'];
			$this->displayName = $course['displayName'];
			$this->price = $course['price'];
			$this->contactInfo = $course['contactInfo'];
			$this->roamingIP = $course['roamingIP'];
			$this->disableCorrectAnswers  = $course['disableCorrectAnswers'];
			$this->languages  = $course['languages'];
			$this->login_type  = $course['login_type'];
			$this->link_to  = $course['link_to'];
			$this->roster_file  = $course['roster_file'];
			$this->roster_username_field  = $course['roster_username_field'];
			$this->roster_email_address_field  = $course['roster_email_address_field'];
			$this->roster_real_name_fields  = $course['roster_real_name_fields'];
			$this->roster_report_filter_fields  = $course['roster_report_filter_fields'];
			$this->roster_report_restrict_fields  = $course['roster_report_restrict_fields'];

			if ( empty($this->displayName) )
				$this->displayName = $course['name'];

            // Force openReg if useCAS is TRUE
            if ( $this->useCAS ) { $this->openReg = true; }

			$this->read_XML();
			//$this->load_syllabus();
			//$this->write_XML();
		}
		else
		{
			// If we don't have a global course, then this needs to
			// invoke a 404 or show the server splash page.
			if ( !isset($GLOBALS['course']) )
			{
				if ( COURSENAME === '' ) {
					print
					file_get_contents(BASEPATH.'application/views/server_splash.html');
					exit;
				}
				else
					show_404();
			}

			error( "Course '{$name}' does not exist." );
		}
	}

	function population()
	{
		$result = $this->ci->db
			->select('count(username) as population')
			->from('registrations')
			->where('course', $this->name)
			->get()
			->result_array();

		return $result[0]['population'];
	}

	function saveData( $saveNew = 0 )
	{
		debug_log('Saving course '.$this->name);
		$row = array	( "name" => $this->name
				, "email" => $this->email
				, "tech_support_email" => $this->tech_support_email
				, "minScore" => $this->minScore
				, "quota" => $this->quota
				, "group" => $this->group
				, "layout" => $this->layout
				, "certificate" => $this->certificate
				, "greyModules" => $this->greyModules
				, "useCodes" => $this->useCodes
				, "openReg" => $this->openReg
				, "expire" => $this->expire
				, "bdayExpire" => $this->bdayExpire
				, "lmid" => $this->lmid
				, "ebizURL" => $this->ebizURL
				, "useEbiz" => $this->useEbiz
				, "useCAS" => $this->useCAS
				, "cas_host" => $this->cas_host
				, "cas_port" => $this->cas_port
				, "cas_context" => $this->cas_context
				, "cas_email_template" => $this->cas_email_template
				, "displayName" => $this->displayName
				, "price" => $this->price
				, "contactInfo" => $this->contactInfo
				, "roamingIP" => $this->roamingIP
				, "disableCorrectAnswers" => $this->disableCorrectAnswers
				, "languages" => $this->languages
				, "login_type" => $this->login_type
				, "link_to" => $this->link_to
				, "roster_file" => $this->roster_file
				, "roster_username_field" => $this->roster_username_field
				, "roster_email_address_field" => $this->roster_email_address_field
				, "roster_real_name_fields" => $this->roster_real_name_fields
				, "roster_report_filter_fields" => $this->roster_report_filter_fields
				, "roster_report_restrict_fields" => $this->roster_report_restrict_fields
				);

		// Fetch the DB row
		$course = $GLOBALS['db']->get_row( "courses", "name", $this->name );

		// Ensure that there is already a valid course
		if ( $course['name'] != "" || $saveNew )
		{
			// Write to the MySQL Database
			//$GLOBALS['db']->save_row( "courses", $row );		
			if ( $saveNew )
				$this->ci->db->insert('courses',$row);
			else
				$this->ci->db->where('name',$this->name)->update('courses',$row);
		}

		warn("Course \"{$this->name}\" saved.");
	}

	function load_syllabus()
	{
		// Grab the syllabus from the DB
		$result = $this->ci->db
			->select('s.*, m.module, m.course as module_course, m.type')
			->from('syllabi s')
			->join('modules m','s.module_id=m.id')
			->where('s.course',$this->name)
			->order_by('s.position')
			->get()
			->result_array();

		// For modules with an availability of "sequential", $prev will hold the name of 
		// the previous module
		$prev = NULL;

		$position = 0;

		foreach ( $result as $row )
		{
			if ( !$row['active'] )
				continue;

			switch ( $row['availability'] )
			{
			case 'always':
				$row['requires'] = NULL;
				break;

			case 'sequential':
				$row['requires'] = $prev;
				break;

			case 'after':
				$row['requires'] = $row['after'];
				break;
			}

			// Normalize the position
			$row['position'] = $position;
			$position++;

			$prev = $row['module_id'];

			$this->syllabus[$row['module_id']] = $row;

			// Set the token used to determine certification
			if ( $row['certifies'] )
				$this->certify_token = new Token('module_'.$row['type'],$row['module_id'],$this->name);
		}
	}

	function get_syllabus_JSON()
	{
		$json = array();
		$position = 0;

		foreach ( $this->syllabus as $row )
		{
			$module = getModule($row['module_id']);

			$json[$position] = array(
				'name' => $module->getTitle(),
				'num_pages' => $module->numPages(),
				'type' => $this->syllabus[$row['module_id']]['type'],
				'module_id' => $row['module_id'],
				'moduleName' => $row['module'],
				'position' => $position,
			);

			// Mark as dynamic if it's a test
			if ( $json[$position]['type'] == 'test' || $json[$position]['type'] == 'dbform' )
				$json[$position]['dynamic'] = TRUE;

			$position++;
		}
		debug_log("Finished compiling modules");

		$json['num_modules'] = count($json);
		debug_log("Finished counting modules");

		return json_encode($json);
	}	

	function getProvisionToken( $moduleName )
	{
		foreach ( $this->syllabus as $row ) {
			if ( $row['module'] == $moduleName ) {
				return new Token('module_'.$row['type'],$row['module_id'],$this->name);
			}
		}

		error("Unknown module: $moduleName");
	}
	
	function getRequirementToken( $moduleName )
	{
		foreach ( $this->syllabus as $row ) {
			if ( $row['module'] == $moduleName ) {
				if ( $row['requires'] === NULL )
					return NULL;
				return new Token('module_'.$this->syllabus[$row['requires']]['type'],$row['requires'],$this->name);
			}
		}

		error("Unknown module: $moduleName");
	}
	
	function getRequirementTitle( $module_id )
	{
		$requires = $this->syllabus[$module_id]['requires'];

		if ( $requires === NULL )
			return NULL;

		$module = getModule($requires);
		return $module->getTitle('SPAN');
	}
	
	function getModuleID( $moduleName )
	{
		foreach ( $this->syllabus as $row )
		{
			if ( $row['module'] == $moduleName )
			{
				return $row['module_id'];
			}
		}

		error("Unknown module: $moduleName");
	}
	
	function getLanguages()
	{
		return explode(',', $this->languages);
	}

	function getUserList()
	{
		$result = $this->ci->db
			->from('registrations r')
			->join('users u','r.username=u.username','left')
			->where('r.course',$this->name)
			->get()
			->result_array();

		$users = array();
		foreach ( $result as $row )
			$users[$row['username']] = $row['realName'];

		return $users;
	}

	function search($search)
	{
		// Only allow users with edit tokens to search users
		if ( 	!(    $GLOBALS['user']->has(new Token('edit','user_information'))
			   || $GLOBALS['user']->has(new Token('edit','user_information',COURSENAME))
		   	 )
	   	   )
		{   
			return;
		}

		// Set up search
		$this->ci->db
			->from('users u');

		$where = "(`u`.`username` LIKE '%{$search}%' OR `u`.`realName` LIKE '%{$search}%') AND
				`u`.`clearance_level` <= ".$GLOBALS['user']->clearance_level;

		// Restrict search to course if over-arching token not found
		if ( 	! $GLOBALS['user']->has(new Token('edit','user_information'))
			&& $GLOBALS['user']->has(new Token('edit','user_information',COURSENAME))
		   )
		{
			$this->ci->db
				->join('enrollments e','e.user_id=u.user_id');
			$where .= " AND e.course='".COURSENAME."'";
		}

		$result = $this->ci->db
			->where($where,NULL,FALSE)
			->limit(30)
			->get()
			->result_array();

		$users = array();
		foreach ( $result as $row )
			$users[$row['user_id']] = $row['realName'] . ' (' . $row['username'] . ')';

		return $users;
	}

	function write_XML()
	{
		$fp = fopen(BASEPATH.'application/resources/'.$this->name.'/syllabus.xml','w');
		fwrite($fp,$this->get_XML());
		fclose($fp);
	}

	function get_XML()
	{
		$x = new XMLWriter(); 
		$x->openMemory();
		$x->setIndent(true);
		
		$x->startDocument('1.0','UTF-8');

		$x->startElement('syllabus');

		foreach ( $this->syllabus as $m )
		{
			$x->startElement('module');

			$x->writeElement('name',$m['module']);
			$x->writeElement('module_course',$m['module_course']);
			$x->writeElement('type',$m['type']);
			$x->writeElement('availability',$m['availability']);
			$x->writeElement('after',$m['after']);
			$x->writeElement('number',$m['number']);
			$x->writeElement('certifies',$m['certifies']);

			$x->endElement();
		}
		$x->endElement();

		$x->endDocument();

		return $x->outputMemory();
	}

	function read_XML()
	{
		$file = BASEPATH.'application/resources/'.$this->name.'/syllabus.xml';
		if ( ! file_exists($file) ) {
			fatal("File {$file} does not exist.");
		}

		$result = $this->load_XML(file_get_contents($file));

		if ( ! $result )
			fatal('Error parsing file "'.$file.'" for course "'.$this->name.'".');
	}

	function load_XML($xml)
	{
		$x = simplexml_load_string($xml);
		
		$position = 0;
		$prev = NULL;

		foreach ( $x->xpath('/syllabus/module') as $m )	{

			$row['module'] = (string) $m->name;
			$row['module_course'] = ( $m->module_course ? (string) $m->module_course : $this->name );
			$row['type'] = (string) $m->type;
			$row['availability'] = (string) $m->availability;
			$row['after'] = (string) $m->after;
			$row['number'] = (string) $m->number;
			$row['certifies'] = (string) $m->certifies;
			$row['module_id'] = $row['module_course'].':'.(string) $m->name;
			
			switch ( $m->availability )
			{
			case 'always':
				$row['requires'] = NULL;
				break;

			case 'sequential':
				$row['requires'] = $prev;
				break;

			case 'after':
				$after = $row['after'];
				if ( strpos($after,':') === FALSE )
					$after = $row['module_course'].':'.$after;

				$row['requires'] = $after;
				break;
			}

			// Normalize the position
			$row['position'] = $position;
			$position++;

			$prev = $row['module_id'];

			$this->syllabus[$row['module_id']] = $row;

			// Set the token used to determine certification
			if ( $row['certifies'] )
				$this->certify_token = new Token('module_'.$row['type'],$row['module_id'],$this->name);
		}

		return TRUE;
	}

    function text_name()
    {
        return strip_tags(str_replace('<BR>',' ',$this->displayName));
    }
}
?>
