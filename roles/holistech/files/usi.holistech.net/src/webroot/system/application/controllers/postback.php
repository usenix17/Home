<?php

/**
 * Postback Controller
 */
class Postback extends Controller
{
	function Postback()
	{
		parent::Controller();
		$this->load->helper('codes');
	}

	function index()
	{
		$this->errors->echo = TRUE;
		flush();
	/* Grab the postback */
		$unique_id = $this->input->post('unique_id');
		$date_time = $this->input->post('date_time');

	/* Find the purchase record */
		$result = $this->db
			->from('purchases')
			->where('purchase_id',$unique_id)
			->get()
			->result_array();
		if ( count($result) != 1 )
			die('Could not find unique_id='.$unique_id);
		$purchase = $result[0];

	/* Initialize purchasing_subsystem user and purchaser */
		// Open the purchasing subsystem user
		$GLOBALS['user'] = getUser('username','purchasing_subsystem');
		$purchaser = getUser('user_id',$purchase['user_id']);

	/* Create the codes */
		$codes = Codes::create($purchase['num_codes'], 'For: '.$purchaser->realName, $purchase['purchase_id'],$purchase['course']);
		var_dump($codes);

	/* Enroll the purchaser if requested */
		if ( $purchase['myself'] ) 
			Codes::apply($codes[0]['code'],$purchaser);

	/* Mark the purchase as complete */
		$this->db
			->set('time_in','NOW()',FALSE)
			->set('status','Completed')
			->set('date_time',$date_time)
			->set('completed_by',$GLOBALS['user']->user_id)
			->where('purchase_id',$purchase['purchase_id'])
			->update('purchases');
	}
}
