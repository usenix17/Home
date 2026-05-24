<?php

/**
 * Course Completion Certificates
 */
class Certificate extends Controller
{
	var $user;

	function Certificate()
	{
		parent::Controller();
	}

	function index()
	{
		if ( $GLOBALS['user']->can_certify() )
			if ( $GLOBALS['user']->has_certified() )
				$this->show_certificate();
			else
				$this->output->set_output('<SCRIPT>pager.unpaged_show("/certificate/verify_name");</SCRIPT>');
		else 
			$this->output->set_output('You are not eligible to receive your certificate in this course.');
	}

	function verify_name()
	{
	/* Check certification authorization */
		if ( ! $GLOBALS['user']->can_certify() )
			fatal('You have not met the requirements to certify in this course.');

	/* Show them the certification front page */
		$this->load->view('control/close');
		$this->load->view('certificate/verify_name',array('user'=>$GLOBALS['user']));
	}

	function certify()
	{
	/* Check certification status */
		if ( $GLOBALS['user']->has_certified() ) {
			$this->show_certificate();
			return;
		}

	/* Check certification authorization */
		if ( ! $GLOBALS['user']->can_certify() )
			fatal('You have not met the requirements to certify in this course.');

	/* Update the realName and generate the certificate */
		$_POST['email'] = $GLOBALS['user']->email;
		$GLOBALS['user']->save_from_post();
		$GLOBALS['user']->certify();

	/* Show the certificate */
		$this->show_certificate();
	}

	function show_certificate($enrollment_id=NULL)
	{
		if ( $enrollment_id === NULL )
			$enrollment_id = $GLOBALS['user']->enrollment_id();

		//$this->output->set_output('<SCRIPT>pager.update_utility(); window.open("'.base_url().'certificate/view/'.$enrollment_id.'");</SCRIPT>');
		$this->output->set_output('<SCRIPT>pager.update_utility(); pager.unpaged_show("/certificate/iframe/'.$enrollment_id.'");</SCRIPT>');
	}

	function test()
	{
		if ( !$GLOBALS['user']->has(new
			Token('auth','test_certificates',COURSENAME)) )
			return;

		$course =& $GLOBALS['course'];
		$name = "Test Certificate";
		$form = "0000";

	/* Set up PDF */
		$this->load->library('fpdf16/fpdf');

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
		
	/* Output the PDF */
		$this->output->set_header('Content-type: application/pdf');
		$this->output->set_output($pdf->output('','S'));
	}


	function iframe($enrollment_id)
	{
		$this->load->view('certificate/iframe',array('enrollment_id'=>$enrollment_id));
	}

	function view($enrollment_id=NULL)
	{
		if ( $enrollment_id === NULL )
			$enrollment_id = $GLOBALS['user']->enrollment_id();

	/* Find the enrolled user */
		// Check $GLOBALS['user'] first`
		$user =& $GLOBALS['user'];
		if ( ! in_array($enrollment_id,array_keys($GLOBALS['user']->enrollments)) ) {
			// Get the user_id from the DB
			$result = $this->db
				->select('user_id')
				->from('enrollments')
				->where('enrollment_id',$enrollment_id)
				->get()
				->result_array();

			$user =& getUser('user_id',$result[0]['user_id']);
		}

		$coursename = $user->enrollments[$enrollment_id]['course'];

	/* Ensure they've in fact certified */
		if ( ! $user->has_certified($enrollment_id) ) {
			print "That enrollment has not certified.";
			return;
		}

	/* Make sure $GLOBALS['user'] is allowed to view this certificate */
		if ( !( $GLOBALS['user']->user_id == $user->user_id ) && ! $GLOBALS['user']->has(new Token('auth','view_certificates',$coursename)) ) {
			print 'You are not allowed to view this certificate.';
			return;
		}

		if ( ! file_exists($user->certificate_path($enrollment_id)) )
		{
			print "The requested certificate cannot be found.";
		}
		else
		{
			$this->output->set_header('Content-type: application/pdf');
			$this->output->set_output(file_get_contents($user->certificate_path($enrollment_id)));
		}
	}
}
