<?php

/**
 * Purchase Controller
 */
class Purchase extends Controller
{
	var $form = NULL;
	var $code;
	var $myself;
	var $num_codes;
	var $duplicates;
	var $fields = array(
		array('field'=>'username','label'=>'username','rules'=>'max_length[100]'),
		array('field'=>'realName','label'=>'Legal Full Name','rules'=>'required|max_length[100]'),
		array('field'=>'password','label'=>'password','rules'=>'min_length[6]|max_length[100]'),
		array('field'=>'password_verify','label'=>'Password Verify','rules'=>'required|matches[password]'),
		array('field'=>'address1','label'=>'address1','rules'=>'max_length[100]'),
		array('field'=>'address2','label'=>'address2','rules'=>'max_length[100]'),
		array('field'=>'city','label'=>'city','rules'=>'max_length[100]'),
		array('field'=>'state','label'=>'state','rules'=>'max_length[100]'),
		array('field'=>'zip','label'=>'zip','rules'=>'max_length[10]'),
		array('field'=>'phone','label'=>'phone','rules'=>'max_length[100]'),
		array('field'=>'email','label'=>'email','rules'=>'required|valid_email|max_length[100]'),
		array('field'=>'employer','label'=>'employer','rules'=>'max_length[100]'),
		array('field'=>'year_born','label'=>'year_born','rules'=>'max_length[4]'),
		array('field'=>'gender','label'=>'gender','rules'=>''),
		array('field'=>'ethnicity','label'=>'ethnicity','rules'=>''),
		array('field'=>'education','label'=>'education','rules'=>''),
	);
	var $custom_fields;
	var $purchase = NULL;

	function Purchase()
	{
		parent::Controller();
		$this->load->helper('formTable');
		$this->load->helper('phone');
		$this->load->library('session');

		if ( $this->input->post('code') ) {
			$this->code = $this->input->post('code');
			$this->session->set_userdata('code',$this->code);
		} else
			$this->code = $this->session->userdata('code');

		if ( $this->input->post('myself') ) {
			$this->myself = $this->input->post('myself');
			$this->session->set_userdata('myself',$this->myself);
		} else
			$this->myself = $this->session->userdata('myself');

		if ( $this->input->post('num_codes') ) {
			$this->num_codes = $this->input->post('num_codes');
			$this->session->set_userdata('num_codes',$this->num_codes);
		} else
			$this->num_codes = $this->session->userdata('num_codes');
	}

	function enter_code()
	{
	/* Grab the code if one was submitted */
		$this->load->helper('codes');
		$code = $this->input->post('code');
		if ( $code !== FALSE && ! empty($code) ) {
			if ( Codes::validate($code,TRUE) !== FALSE )
				$this->session->set_userdata(array(
					'code' => $code,
					'myself' => TRUE,
				));
			else
				return;

			$this->start_purchase();
		}
		else
			error('Please enter a registration code and click "Register", or click "Cancel" to go back.');
	}

	function start_purchase()
	{
	/* Load the preagreement */
		$this->output->set_output("<SCRIPT>pager.unpaged_show('/purchase/preagreement')</SCRIPT>");
	}

	function preagreement()
	{
		$this->load->helper('file');

	/* Check for the preagreement */
		$pre_agreement_folder = new File('purchase/preagreement');
		if ( $pre_agreement_folder->exists() )
		{
			$this->load->view('purchase/preagreement');
			return;
		}

	/* Default: pass through to select_purchase() */
		$this->select_purchase();
	}

	function select_purchase()
	{
    /* CAS courses jump directly to enrollment */
        if ( $GLOBALS['course']->useCAS ) {
            $this->auth->login();
            $this->enroll_and_login();
			return;
        }
	/* Check if we've already collected a code or if the course has open registation*/
		if ( $this->code || $GLOBALS['course']->openReg ) {
			$this->show_create_user();
			return;
		}

	/* Load the purchase selection page */
		$this->load->view('purchase/select',array(
			'name' => str_replace('<BR>',' ',$GLOBALS['course']->displayName),
			'price' => $GLOBALS['course']->price,
		));
	}

	function show_create_user()
	{
		$header = '';
		$footer = '';

	/* Find the header */
		$header_folder = new File('purchase/header');
		if ( $header_folder->exists() )
		{
			$header = lang_files('purchase/header');
		}

	/* Find the footer */
		$footer_folder = new File('purchase/footer');
		if ( $footer_folder->exists() )
		{
			$footer = lang_files('purchase/footer');
		}

	/* Show the form */
		$this->load->view('purchase/create_user', array(
			'header' => $header,
			'footer' => $footer,	
			'custom' => $this->_get_custom_user_fields(),
			'myself' => $this->myself,
			'num_codes' => $this->num_codes,
			'code' => $this->code,
		));
	}

	/**
	 * This is one of two places where a user can be created.  
	 * The other is administratively via control_user
	 */
	function create_user()
	{
		debug_log("purchase/create_user");

		$user = new User();
		$user_id = $user->save_from_post();

		if ( $user_id === FALSE )
			// The user did not get created, go back
			$this->show_create_user();
		else
			// The user is created, now proceed as if they're logging in
			$this->do_login();
	}	

	function do_login()
	{
		$this->auth->login($this->input->post($GLOBALS['course']->login_type),$this->input->post('password'));

		// If there was a code used, it's not a purchase so enroll and login
		// Otherwise, if the user elected to self-enroll and is already enrolled, 
		// 	just log in _even if they requested more codes_.
		// All other cases, continue with the order_conf
		if ( $this->code || $GLOBALS['course']->openReg )
			$this->enroll_and_login();
		elseif ( $this->myself && ! $GLOBALS['user']->ensure_not_already_enrolled(COURSENAME) ) {
			$this->output->set_output("<SCRIPT>load('/users/init_pager','#HIDDEN');</SCRIPT>");
			if ( $this->num_codes > 1 )
				warn(lang(array(
					'EN' => 'If you wish to purchase additional registration codes, please visit the "Purchases" tab under "Settings".',
					'ES' => 'Si quiere comprar c&oacute;digos de registraci&oacute;n adicionales, por favor visite "Compras" desde "Opciones".',
				)));
		}
		else 
			$this->output->set_output("<SCRIPT>pager.unpaged_show('/control_purchases/order_conf');</SCRIPT>");

	}

	function enroll_and_login()
	{
		if ( $this->code ) {
			$this->load->helper('codes');
			Codes::apply($this->code,$GLOBALS['user']);
		} elseif ( $GLOBALS['course']->openReg ) {
			$GLOBALS['user']->enroll(COURSENAME);
		}

		$this->output->set_output("<SCRIPT>load('/users/init_pager','#HIDDEN');</SCRIPT>");
	}

//	function duplicates()
//	{
//	/* Get the form data */
//		if ( ! $this->_get_form() )
//			return;
//
//	/* Check for duplicates */
//		if ( count($this->duplicates) )
//		{
//			// Retrieve the duplicates message
//			$header = '';
//			$header_folder = new File('purchase/duplicate');
//			if ( $header_folder->exists() )
//			{
//				$header = lang_files('purchase/duplicate');
//			}
//
//			$this->load->view('purchase/duplicates',array('header'=>$header));
//			return;
//		}
//
//	/* Default: pass through to verify() */
//		$this->verify();
//	}
//
//	function email_duplicates()
//	{
//	/* Get the form data */
//		if ( ! $this->_get_form() )
//			return;
//
//	/* Email the codes */
//		foreach ( $this->duplicates as $row )
//		{
//			$this->_emailCode($row);
//		}
//
//	/* Show the confirmation */
//		$this->load->view('purchase/emailed');
//		return;
//	}
//
//	function verify()
//	{
//	/* Get the form data */
//		if ( ! $this->_get_form() )
//			return;
//
//	/* Show the verification page */
//		$this->load->view('purchase/verify',array(
//			'form' => $this->form,
//		));
//	}
//
//
//
//	private function _get_form()
//	{
//	/* See if there's something on POST */
//		if ( $this->input->post('submit') !== FALSE )
//		{
//			// Try to get the data
//			if ( $this->_get_form_from_post() )
//				return TRUE;
//		}
//
//	/* See if there's something carried over in SESSION */
//		elseif ( $this->_get_session() )
//			return TRUE;
//
//	/* We were not successful at gathering the form data, so we have to go collect it again. */
//		$this->form();
//	}
//
//	private function _get_form_from_post()
//	{
//	/* Escape if we already have the form */
//		if ( $this->form !== NULL )
//			return TRUE;
//
//		$form = array();
//		$this->load->library('form_validation');
//
//	/* Set up validation rules */
//		$validate = array(
//			array('field'=>'first_name','label'=>'First Name','rules'=>'required'),
//			array('field'=>'last_name','label'=>'Last Name','rules'=>'required'),
//			array('field'=>'phone','label'=>'Phone Number','rules'=>'required'),
//			array('field'=>'email','label'=>'E-Mail Address','rules'=>'required|valid_email'),
//			array('field'=>'num_codes','label'=>'Quantity','rules'=>'required|is_natural_no_zero'),
//		);
//		$this->form_validation->set_rules($validate);
//
//	/* Run form validation */
//		if ( $this->form_validation->run() == FALSE )
//			return FALSE;
//
//	/* Extract form data */
//		foreach ( $validate as $field )
//		{
//			$data = $this->input->xss_clean($this->input->post($field['field']));
//			
//			if ( substr($field['field'], 0, 5) == 'phone' )
//				$data = Phone::unformat($data);
//		
//			$form[$field['field']] = $data;
//		}
//
//	/* Calculate additional fields */
//		$form['amount'] = $form['num_codes'] * $GLOBALS['course']->price;
//		$form['time'] = time();
//		$form['unique_id'] = preg_replace( '/[^A-Za-z]/', '', $form['last_name'] ) . '_' . $form['time'];
//		$form['Trans_Desc'] = $form['num_codes'] . ( $form['num_codes'] > 1 ? ' registration codes for the ' 
//								: ' registration code for the ' ) . $GLOBALS['course']->displayName;
//		$form['return_url'] = base_url();
//		$form['course'] = COURSENAME;
//
//	/* Save row in DB */
//		$GLOBALS['db']->save_row( 'purchase', $form );
//
//	/* Populate ebusiness fields */
//		$form['url'] = $GLOBALS['course']->ebizURL;
//		$form['LMID'] = $GLOBALS['course']->lmid;
//		$form['webTitle'] = $GLOBALS['course']->displayName;
//		$form['contact_info'] = $GLOBALS['course']->contactInfo;
//
//	/* Try to find a duplicate entry */
//		$sql =	"SELECT * FROM purchase WHERE
//			( ( first_name='{$form['first_name']}' AND last_name='{$form['last_name']}' )
//			OR email='{$form['email']}'
//			OR phone='{$form['phone']}' )
//			AND time>=UNIX_TIMESTAMP(ADDDATE(NOW(),-30))
//			AND codes IS NOT NULL
//			AND course='{$form['course']}'
//			ORDER BY time DESC;";
//
//		$this->duplicates = $GLOBALS['db']->query($sql);
//
//	/* Save form in object and session */
//		$this->form = $form;
//		$this->_save_session();
//
//		return TRUE;
//	}
//
//	private function _save_session()
//	{
//		$this->session->set_userdata(array(
//			'purchase' => array(
//				'form' => $this->form,
//				'duplicates' => $this->duplicates,
//			)
//		));
//	}
//
//	private function _get_session()
//	{
//		$purchase = $this->session->userdata('purchase');
//
//		if ( $purchase === FALSE )
//			return FALSE;
//
//		$this->form = $purchase['form'];
//		$this->duplicates = $purchase['duplicates'];
//
//		return TRUE;
//	}
//
//	private function _emailCode( $row )
//	{
//		$first = trim($row['first_name']);
//		$last = trim($row['last_name']);
//		$email = $row['email'];
//		$codes = trim($row['codes']);
//
//		$code_links = array();
//		$username_links = array();
//
//		foreach ( explode("\n",$codes) as $code )
//		{
//			$x = intval( substr( $code, 0, 5 ) );
//			
//			$username = $GLOBALS['db']->get_data( 'codes', 'code', $x, 'username' );
//			
//			if ( !$username )
//				$code_links[] = "<A HREF='http://az-hospitality.org/{$GLOBALS['course']->name}/?action=register&code={$code}'>{$code}</A>";
//			else			
//				$username_links[] =  "<A HREF='http://az-hospitality.org/{$GLOBALS['course']->name}/?action=login&username={$username}'>{$username}</A>";
//		}
//
//		$this->load->library('phpMailer_v2.3/class.phpmailer.php');
//
//		$mail = new PHPMailer();
//
//		$mail->IsSMTP();
//		$mail->Host = 'mailgate.nau.edu';
//
//		$mail->From = $GLOBALS['course']->email;
//		$mail->FromName = $GLOBALS['course']->displayName;
//		$mail->AddAddress($email,$first.' '.$last);
//		$mail->AddBCC('Jason.Karcz@nau.edu');
//		$mail->AddBCC('food@nau.edu');
//		//$mail->AddAddress('jek9@nau.edu');
//
//		$mail->Subject = $GLOBALS['course']->displayName.' - Purchase';
//		$mail->Body = "Dear {$first},<BR><BR>";
//
//		if ( count($code_links) )
//		{
//			if ( count($code_links) == 1 && !count($username_links) )
//				$mail->Body .= 'Your registration code is: '.$code_links[0].'.  You can click on this link or click on "Enter a Code" at http://az-hospitality.nau.edu/'.$GLOBALS['course']->name.' to register.<BR><BR>';
//			elseif ( count($code_links) > 1 )
//				$mail->Body .= 'Below are the unused registration codes that you purchased:<BR><BR>'.join('<BR>',$code_links).'<BR><BR>Each of the above codes is a link that will take you to the registation page.  Alternatively, each code can be entered in at http://az-hospitality.nau.edu/'.$GLOBALS['course']->name.' by clicking on "Enter a Code".<BR><BR>';
//			else
//				$mail->Body .= 'Below is the unused registration code that you purchased:<BR><BR>'.join('<BR>',$code_links).'<BR><BR>The above code is a link that will take you to the registation page.  Alternatively, the code can be entered in at http://az-hospitality.nau.edu/'.$GLOBALS['course']->name.' by clicking on "Enter a Code".<BR><BR>';
//		}
//
//		if ( count($username_links) )
//		{
//			if ( count($username_links) == 1 )
//				$mail->Body .= 'Below is the username that has been registered:<BR><BR>';
//			else
//				$mail->Body .= 'Below are the usernames that have been registered:<BR><BR>';
//
//			$mail->Body .= join('<BR>',$username_links).'<BR><BR>Click on the username link to log in, or enter it at http://az-hospitality.nau.edu/'.$GLOBALS['course']->name.'.<BR><BR>';
//		}
//		
//		$mail->Body .= 'If you have any questions, please feel free to call (928) 523-3737 or email <A HREF="mailto:food@nau.edu">food@nau.edu</A>.';
//
//		$mail->IsHTML(true);
//
//		return $mail->Send();
//	}

	private function _get_custom_user_fields()
	{
		$key = ( $GLOBALS['course']->link_to ? $GLOBALS['course']->link_to : COURSENAME );
		return lang_includes(BASEPATH.'application/resources/'.$key.'/control_user/view','TBODY');
	}

}
