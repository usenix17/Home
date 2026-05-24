<?php

/**
 * Tech Support Control Panel
 */
class Tech_Support extends Controller
{
	var $user;

	function Tech_Support()
	{
		parent::Controller();

		$this->load->library('session');
		$this->load->helper('codes');
		$this->session->set_userdata('control_selected','tech_support');

		if ( ! $GLOBALS['user']->has(new Token('auth','tech_support',COURSENAME)) &&
			! $GLOBALS['user']->has(new Token('edit','user_information',COURSENAME)) &&
			! $GLOBALS['user']->has(new Token('edit','user_information')))
			fatal('That operation is not allowed');
	}

	function index()
	{
		$this->load->view('control/tech_support/search');
	}

	function search($page=0)
	{
		$rows_per_page = 10;

		$search = $this->input->post('query');
		$limit = '';
		if ( $search == '' )
			$limit = 'LIMIT 100';
		$courses = $this->_get_courses();

		$tech_support = $GLOBALS['user']->has(new Token('auth','tech_support',COURSENAME));

		$sql = <<<SQL
			SELECT DISTINCT u.user_id, u.realName, u.email, u.phone
			FROM users u
			LEFT JOIN purchases p ON p.user_id=u.user_id 
			LEFT JOIN enrollments e ON e.user_id=u.user_id
			WHERE 
				( u.realName LIKE ?
				OR u.username LIKE ?
				OR u.email LIKE ?
				OR u.phone LIKE ?
				OR p.purchase_id LIKE ?
				OR e.course LIKE ?
				)
				AND u.clearance_level <= ?
				AND ( e.course IN ({$courses}) OR p.course IN ({$courses}) )
			GROUP BY u.user_id
			{$limit}
SQL;

		$phone_search = '-';
		if ( ! preg_match('/[^0-9()\- ]/',$search) )
			$phone_search = preg_replace('/\D/','',$search);

		$query = $this->db->query($sql,array(
			'%'.$search.'%',
			'%'.$search.'%',
			'%'.$search.'%',
			'%'.$phone_search.'%',
			'%'.$search.'%',
			'%'.$search.'%',
			$GLOBALS['user']->clearance_level,
		));

		$num_results = $query->num_rows();
		$num_pages = ceil($num_results/$rows_per_page);
		$data = $query->result_array();

		if ( $num_results == 0 ) {
			$this->output->set_output('<I>No Results Found.</I>');
			return;
		}

	// Calculate last login for each user in $result
		$user_ids = array();
		foreach ( $data as $r ) 
			$user_ids[] = $r['user_id'];
		$user_ids = '(' . implode(',',$user_ids) . ')';

		$sql = <<<SQL
			SELECT u.user_id,
			max(l.time) as last_login,
			l.course as last_course
			FROM users u
			LEFT JOIN (SELECT * FROM login_log ORDER BY time DESC) l on u.user_id=l.user_id
			WHERE u.user_id in {$user_ids}
			GROUP BY u.user_id
			ORDER BY last_login DESC
SQL;
		
		$login_times = $this->db->query($sql)->result_array();

	// Calculate counts of purchases, enrollments, and certificates
		$sql = <<<SQL
			SELECT u.user_id,
			( SELECT count(p.purchase_id) FROM purchases p WHERE p.user_id=u.user_id ) AS num_purchases,
			( SELECT count(e.enrollment_id) FROM enrollments e WHERE e.user_id=u.user_id ) AS num_enrollments,
			( SELECT sum(if(e2.status="Completed",1,0)) FROM enrollments e2 WHERE e2.user_id=u.user_id ) as num_certificates
			FROM users u
			WHERE u.user_id in {$user_ids}
SQL;
		
		$counts = $this->db->query($sql)->result_array();


	// Combine $data and $login_times for $result
		$result = array();
		foreach ( $login_times as $r )
			$result[$r['user_id']] = $r;
		foreach ( $counts as $r )
			$result[$r['user_id']] = array_merge($result[$r['user_id']],$r);
		foreach ( $data as $r )
			$result[$r['user_id']] = array_merge($result[$r['user_id']],$r);
		
	// Paginate the results
		$result = array_slice($result,$page*$rows_per_page,$rows_per_page);

		$this->load->view('control/tech_support/search_results',array(
			'results' => $result,
			'tech_support' => $tech_support,
			'num_results' => $num_results,
			'num_pages' => $num_pages,
			'page' => $page,
		));
	}

	function show_details($user_id)
	{
		$tech_support = $GLOBALS['user']->has(new Token('auth','tech_support','*'));

	/* Load the user */
		$user = getUser('user_id',$user_id);

	/* Load Purchases */
		$purchases = $this->db
			->from('purchases')
			->where('user_id',$user_id)
			->get()
			->result_array();

	/* Load codes for each purchase */
		$raw_codes = $this->db
			->select('c.*,u.realName as used_by_realName,e.status as enrollment_status')
			->from('purchases p')
			->join('codes c','p.purchase_id=c.purchase_id','right')
			->join('users u','c.user_id=u.user_id','left')
			->join('enrollments e','e.code=c.code','left')
			->where('p.user_id',$user_id)
			->get()
			->result_array();

		// Key by purchase_id and encode the serial number
		$codes = array();
		foreach ( $raw_codes as $c ) {
			$c['serial'] = $c['code'];
			$c['code'] = Codes::generate($c['code']);
			$codes[$c['purchase_id']][] = $c;
		}

	/* Calculate completion percentages and certification status for enrollments */
		foreach ( array_keys($user->enrollments) as $key ) {
			$user->enrollments[$key]['can_certify'] = $user->can_certify($key);
			$user->enrollments[$key]['has_certified'] = $user->has_certified($key);
			$user->enrollments[$key]['percent_complete'] = $user->percent_complete($key);
		}
	
	/* Create the view */
		$this->load->view('control/tech_support/details',array(
			'purchases' => $purchases,
			'enrollments' => $user->enrollments,
			'codes' => $codes,
			'user' => $user,
			'user_id' => $user_id,
			'tech_support' => $tech_support,
		));
	}

	function fulfill($purchase_id)
	{
	/* Retrieve the purchase row */
		$result = $this->db
			->from('purchases p')
			->join('users u','u.user_id=p.user_id','left')
			->where('purchase_id',$purchase_id)
			->get()
			->result_array();

		if ( count($result) !== 1 )
			fatal('Invalid purchase_id: '.$purchase_id);

		$purchase = $result[0];

	/* Make sure the tech is allowed to fulfill purchases for this course */
		if ( ! $GLOBALS['user']->has(new Token('auth', 'fulfill_purchases', $purchase['course'])) )
			fatal('You don\'t have permission to fulfill purchases
			for this course.');

	/* Make sure it hasn't already been fulfilled */
		if ( $purchase['status'] != 'Pending' )
			fatal('You can only fulfill pending purchases.');

	/* Generate the codes */
		Codes::create($purchase['num_codes'], 'For: '.$purchase['realName'],
			$purchase_id, $purchase['course']);

	/* Mark the purchase as fulfilled */
		$this->db
			->set('status','Fulfilled')
			->set('completed_by',$GLOBALS['user']->user_id)
			->where('purchase_id',$purchase_id)
			->update('purchases');

	/* Reload the view */
		$this->output->set_output('<SCRIPT>load("/tech_support/show_details/'.$purchase['user_id'].'","#tech_support_details_'.$purchase['user_id'].'_TD");</SCRIPT>');
	}

	function reset_password($user_id)
	{
		$user = $this->_getUser('user_id',$user_id);

		$user->resetPassword();
	}

	function save_email($user_id)
	{
		$user = $this->_getUser('user_id',$user_id);
		$user->setEmail($this->input->post('email'));
	}

	function refund_codes()
	{
	/* Grab the refund request */
		list($purchases,$codes) = $this->_look_up_codes();

	/* Determine if any of the codes have been used to completion */
		$reasons = array();
		$fail = FALSE;
		foreach ( $codes as $c ) {
			if ( $c['enrollment_status'] == 'Completed' || $c['code_status'] == 'Refunded' 
			|| $c['code_status'] == 'Voided' ) {
				$reasons[] = $this->_describe_code($c);
				$fail = TRUE;
			}
		}
		if ( $fail ) {
			$this->load->view('control/tech_support/refund_fail',array('reasons'=>$reasons));
			return;
		}

	/* Determine if any of the codes have been used at all */	
		$reasons = array();
		$fail = FALSE;
		foreach ( $codes as $c ) {
			if ( $c['code_status'] == 'Used' ) {
				$reasons[] = $this->_describe_code($c);
				$fail = TRUE;
			}
		}
		if ( $fail ) {
			$this->load->view('control/tech_support/refund_warn',array(
				'reasons' => $reasons,
				'codes' => $this->input->post('codes'),
				'user_id' => $this->input->post('user_id'),
			));
			return;
		}

	/* Go ahead and process the refund */
		$this->refund_address_verify();
	}

	function refund_address_verify()
	{
	/* Grab the user who's getting the refund */
		$user_id = $this->input->post('user_id');
		$user = $this->_getUser('user_id',$user_id);

	/* Verify the Address */	
		$this->load->view('control/tech_support/address_verify',array(
				'codes' => $this->input->post('codes'),
				'user_id' => $user_id,
				'user' => $user,
				'destination' => '/tech_support/refund_final',
		));
	}

	function refund_final()
	{
	/* Grab the user who's getting the refund and update */
		$user_id = $this->input->post('user_id');
		$user = $this->_getUser('user_id',$user_id);
		if ( ! $user->save_from_post() ) {
			$this->refund_address_verify();
			return;
		}

	/* Grab the refund request */
		list($purchases,$codes) = $this->_look_up_codes();

	/* Calculate the refund amount */
		$amount = 0;
		$amount_per_purchase = array();
		foreach ( $codes as $c ) {
			$amount += $c['purchase_price'];
			@$amount_per_purchase[$c['purchase_id']] += $c['purchase_price'];
		}

	/* Mark the codes as refunded and cancel user accounts */
		foreach ( $codes as $c )
			Codes::refund($c['code']);

	/* Get the email body */
		$message = $this->load->view('control/tech_support/refund_email',array(
			'purchases' => $purchases,
			'codes' => $codes,
			'user' => $user,
			'amount' => $amount,
			'message' => nl2br($this->input->post('message')),
		),TRUE);

		$this->_email('Albert.Sandoval@nau.edu',"az-hospitality.nau.edu refund request",$message);
	}

	function email_codes()
	{
	/* Grab the user who's getting the email */
		$user_id = $this->input->post('user_id');
		$user = getUser('user_id',$user_id);

	/* Verify the Address */	
		$this->load->view('control/tech_support/address_verify',array(
				'codes' => $this->input->post('codes'),
				'user_id' => $user_id,
				'user' => $user,
				'destination' => '/tech_support/email_final',
		));
	}

	function email_final()
	{
	/* Grab the user who's getting the email and update */
		$user_id = $this->input->post('user_id');
		$user = getUser('user_id',$user_id);
		if ( ! $user->save_from_post() ) {
			$this->email_codes();
			return;
		}

		if ( !$user->email ) {
			error('No e-mail address is set for "'.$user->realName.'".  Please set an e-mail address.');
			$this->email_codes();
			return;
		}

	/* Grab the email request */
		list($purchases,$codes) = $this->_look_up_codes();


	/* Get the email body */
		$message = $this->load->view('control/tech_support/codes_email',array(
			'codes' => $codes,
			'user' => $user,
			'message' => nl2br($this->input->post('message')),
		),TRUE);

		$this->_email($user->email,"Your az-hospitality.nau.edu registration code",$message);
	}

	function email_all_codes()
	{
	/* Grab the user who's getting the email */
		$user_id = $this->input->post('user_id');
		$user = $this->_getUser('user_id',$user_id);

	/* Load all of their available codes into $_POST */
		$codes = Codes::list_codes('p.user_id',$user->user_id,TRUE);
		$_POST['codes'] = array();
		foreach ( $codes as $c )
			$_POST['codes'][] = $c['serial'];

		$this->email_codes();
	}

	function certify($enrollment_id)
	{
	/* Grab the user who's getting the refund */
		$user_id = $this->input->post('user_id');
		$user = $this->_getUser('user_id',$user_id);

	/* Ensure the tech has permission to certify for this course */
		$coursename = $user->enrollments[$enrollment_id]['course'];
		if ( ! $GLOBALS['user']->has(new Token('auth', 'certify_users', $coursename)) ) {
			error('You are not allowed to certify users for that
			course.');
			$this->show_details($user_id);
			return;
		}
		
	/* Verify the Address */	
		$this->load->view('control/tech_support/address_verify',array(
				'codes' => array(),
				'user_id' => $user_id,
				'user' => $user,
				'destination' => '/tech_support/certify_final/'.$enrollment_id,
				'hide_message' => TRUE,
		));
	}

	function certify_final($enrollment_id)
	{
	/* Grab the user who's getting the certificate and update */
		$user_id = $this->input->post('user_id');
		$user = $this->_getUser('user_id',$user_id);
		if ( ! $user->save_from_post() ) {
			$this->certify($enrollment_id);
			return;
		}

	/* Ensure the tech has permission to certify for this course */
		$coursename = $user->enrollments[$enrollment_id]['course'];
		if ( ! $GLOBALS['user']->has(new Token('auth', 'certify_users', $coursename)) ) {
			error('You are not allowed to certify users for that
			course.');
			$this->show_details($user_id);
			return;
		}
		
	/* Verify permission to certify the user */
		if ( $user->can_certify($enrollment_id) )
			$user->certify($enrollment_id);
		else
			if ( $GLOBALS['user']->has(new Token('auth','certify_unqualified_users',COURSENAME)) )
				$user->certify($enrollment_id);
			else
				error('You are not allowed to certify unqualified users.');

	/* Show the results screen */
		$this->show_details($user_id);
	}

	function email_certificate($enrollment_id)
	{
	/* Grab the user who's getting the email */
		$user_id = $this->input->post('user_id');
		$user = getUser('user_id',$user_id);

	/* Email the certificiate */
		$user->email_certificate($enrollment_id);
	}

	// This function copies the database line of a course
	function copy_course($from,$to)
	{
		print "Copying course {$from} to {$to}\n";
		if ( ! $GLOBALS['user']->has(new
			Token('auth','copy_course',$from)) )
			return;

		$from_course = getCourse($from);

		$from_course->name = $to;
		$from_course->saveData(true);

		print "Course {$from} copied to {$to}.";
	}

	function dump_course()
	{
		$dump = '<STYLE>H1.pageTitle { font-size: 14pt; }</STYLE>';
		foreach ( $GLOBALS['course']->syllabus as $row ) {
			$module = getModule($row['module_id']);
			if ( $module->type == 'dbForm' )
				continue;

			$title = $module->getTitle();

			for ( $i=0; $i<$module->numPages(); $i++ ) {
				$j = $i + 1;
				$dump .= "<HR><H2>{$title} - Page {$j}</H2><HR>".$module->getPage($i);
			}
		}

		$this->output->set_output($dump);
	}

	private function _email($to,$subject,$message)
	{
		require_once(BASEPATH.'application/libraries/PHPMailer_v5.1/class.phpmailer.php');
		$mail = new PHPMailer();
		$mail->IsSMTP(); // telling the class to use SMTP
		$from = ( empty($GLOBALS['course']->tech_support_email) ? $GLOBALS['course']->email : $GLOBALS['course']->tech_support_email );
		try {
			$mail->Host       = "mailgate.nau.edu"; // SMTP server
			//$mail->SMTPDebug  = 2;                     // enables SMTP debug information (for testing)
			$mail->AddAddress($to);
			$mail->AddBCC('Jason.Karcz@nau.edu');
			$mail->AddBCC('food@nau.edu');
			$mail->SetFrom($from, "az-hospitality.nau.edu Technical Support");
			$mail->Subject = $subject;
			$mail->MsgHTML($message);
			$mail->Send();
			warn("Email Sent");
		} catch (Exception $e) {
			error($e->getMessage());
		}
	}

	/**
	 * Retrieves more detailed information about the codes found in array $_POST['codes']
	 *
	 * @return	array
	 */
	private function _look_up_codes()
	{
		$codes = $this->input->post('codes');
		if ( $codes === FALSE )
			fatal('No codes were selected.');

		$result = $this->db
			->select('p.purchase_id,p.time_started,p.time_in,p.num_codes,p.myself,p.amount,p.status,
				p.completed_by,p.course,c.code,c.course as code_course,c.user_id,c.label,
				c.creator_user_id,c.time_created,c.time_used,e.status as enrollment_status,
				u.realName as enrollment_realName,u.email as enrollment_email, e.certification_time,
			       	u2.realName as refunded_by,c.refunded_on,c.status as code_status')
			->from('purchases p')
			->join('codes c','c.purchase_id=p.purchase_id')
			->join('enrollments e','e.code=c.code','left')
			->join('users u','e.user_id=u.user_id','left')
			->join('users u2','c.refunded_by=u.user_id','left')
			->where_in('c.code',$codes)
			->order_by('p.purchase_id')
			->get()
			->result_array();

		$purchases = array();
		$codes = array();

		foreach ( $result as $r ) {
			$purchases[$r['purchase_id']]['purchase_id'] = $r['purchase_id'];
			$purchases[$r['purchase_id']]['time_started'] = $r['time_started'];
			$purchases[$r['purchase_id']]['time_in'] = $r['time_in'];
			$purchases[$r['purchase_id']]['num_codes'] = $r['num_codes'];
			$purchases[$r['purchase_id']]['myself'] = $r['myself'];
			$purchases[$r['purchase_id']]['amount'] = $r['amount'];
			$purchases[$r['purchase_id']]['status'] = $r['status'];
			$purchases[$r['purchase_id']]['completed_by'] = $r['completed_by'];
			$purchases[$r['purchase_id']]['course'] = $r['course'];
			$purchases[$r['purchase_id']]['codes'][] = $r['code'];

			$codes[$r['code']]['code'] = $r['code'];
			$codes[$r['code']]['course'] = $r['course'];
			$codes[$r['code']]['purchase_id'] = $r['purchase_id'];
			$codes[$r['code']]['code_course'] = $r['code_course'];
			$codes[$r['code']]['label'] = $r['label'];
			$codes[$r['code']]['user_id'] = $r['user_id'];
			$codes[$r['code']]['creator_user_id'] = $r['creator_user_id'];
			$codes[$r['code']]['time_created'] = $r['time_created'];
			$codes[$r['code']]['time_used'] = $r['time_used'];
			$codes[$r['code']]['enrollment_status'] = $r['enrollment_status'];
			$codes[$r['code']]['enrollment_realName'] = $r['enrollment_realName'];
			$codes[$r['code']]['enrollment_email'] = $r['enrollment_email'];
			$codes[$r['code']]['certification_time'] = $r['certification_time'];
			$codes[$r['code']]['refunded_by'] = $r['refunded_by'];
			$codes[$r['code']]['refunded_on'] = $r['refunded_on'];
			$codes[$r['code']]['code_status'] = $r['code_status'];
			$codes[$r['code']]['purchase_price'] = $r['amount'] / $r['num_codes'];
		}

		return array($purchases,$codes);
	}

	private function _describe_code($c)
	{
		if ( $c['enrollment_status'] == 'Completed' )
			return Codes::generate($c['code'])." was used by {$c['enrollment_realName']} on ".
					date('j M Y \a\t H:i:s',strtotime($c['time_used'])).
					" and was completed on ".
					date('j M Y \a\t H:i:s.',strtotime($c['certification_time']));
		
		if ( $c['code_status'] == 'Refunded' )
			return Codes::generate($c['code'])." was refunded by {$c['refunded_by']} on ".
					date('j M Y \a\t H:i:s.',strtotime($c['refunded_on']));
		
		if ( $c['code_status'] == 'Voided' )
			return Codes::generate($c['code'])." is has been voided.";
		
		if ( $c['code_status'] == 'Available' )
			return Codes::generate($c['code'])." is available.";

		if ( $c['code_status'] == 'Used' )
			return Codes::generate($c['code'])." was used by {$c['enrollment_realName']} on ".
					date('j M Y \a\t H:i:s.',strtotime($c['time_used']));
		
		return "_describe_code does not have enough conditions to describe this code.";
	}

	private function _getUser($key_type,$key)
	{
		debug_log("tech_support/_get_user/{$key_type}/{$key}");
		$user =& getUser($key_type,$key);

		if ( $GLOBALS['user']->can_edit($user) )
			return $user;
		else
			fatal('You are not allowed to edit this user.');
	}

	private function _get_courses()
	{
		$result = $this->db
			->from('courses c')
			->get()
			->result_array();

		$courses = array();
		foreach ( $result as $c ) {
			if ( $GLOBALS['user']->has(new Token('edit','user_information',$c['name'])) ) {
				$courses[] = '"'.$c['name'].'"';
			}
		}

		return implode(',',$courses);
	}
}
