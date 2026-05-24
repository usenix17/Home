<?php

/**
 * Purchases Control Panel
 *
 * This controller is corollary to Purchase 
 * except Purchase does not have a logged-in user
 */
class Control_Purchases extends Controller
{
	var $myself;
	var $num_codes;
	var $purchase;

	function Control_Purchases()
	{
		parent::Controller();
		$this->load->helper('formTable');
		$this->load->helper('phone');
		$this->load->helper('codes');
		$this->load->library('session');

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

	function order_conf()
	{
		if ( $this->purchase === NULL )
			$this->_save_purchase_row();

		$this->load->view('/control/purchase/order_conf',array('purchase'=>$this->purchase));

		if ( $GLOBALS['course']->useEbiz )
			$this->load->view('/control/purchase/NAU_EBusiness',array('purchase'=>$this->purchase));
	}

	function complete($purchase_id)
	{
		$this->_load_purchase_row($purchase_id);

		$this->session->set_flashdata('unpaged','/control_purchases/thank_you/'.$purchase_id);

		// Load the main system
		$this->output->set_output('<SCRIPT>location.href="'.base_url().'";</SCRIPT>');
	}

	function thank_you($purchase_id)
	{
		$this->load->view('/control/purchase/thank_you', array('purchase_id'=>$purchase_id));
	}

	function poll($purchase_id)
	{
		$this->_load_purchase_row($purchase_id);

		if ( $this->purchase['status'] == 'Completed' )
			$this->output->set_output("<SCRIPT>load('/control_purchases/view/{$purchase_id}','#control_purchase_thank_you_DIV')</SCRIPT>");
		else
			$this->output->set_output("<SCRIPT>Control_Purchases.poll()</SCRIPT>");
	}

	function timeout($purchase_id)
	{
		$this->load->view('/control/purchase/timeout', array('purchase_id'=>$purchase_id));
	}

	function view($purchase_id)
	{

		$this->_load_purchase_row($purchase_id);
		$codes = Codes::list_codes('c.purchase_id',$purchase_id);

		// If there's just one code, and it was used on the current user, just log in
		if ( count($codes) == 1 && $codes[0]['user_id'] == $GLOBALS['user']->user_id )
		{
			$this->output->set_output('<SCRIPT>pager.unpaged_show("/users/init_pager/");</SCRIPT>');
			return;
		}

		$this->load->view('/control/purchase/view',array(
			'purchase' => $this->purchase,
			'codes' => $codes,
		));
	}

	private function _save_purchase_row()
	{
		if ( ! isset($GLOBALS['user']) || ! $GLOBALS['user']->exists() )
			fatal('Cannot initiate a purchase without a user_id');

		if ( ! $GLOBALS['course']->price )
			fatal('Cannot initiate a purchase without a price.');

		$this->purchase = array(
			'purchase_id' => uniqid(),
			'user_id' => $GLOBALS['user']->user_id,
			'course' => COURSENAME,
			'num_codes' => $this->num_codes,
			'myself' => $this->myself,
			'amount' => $GLOBALS['course']->price * $this->num_codes,
		);

		$this->db->insert('purchases',$this->purchase);
	}

	private function _load_purchase_row($purchase_id)
	{
		$result = $this->db
			->from('purchases')
			->where('purchase_id',$purchase_id)
			->get()
			->result_array();

		if ( count($result) != 1 )
			fatal('There was a problem loading purchase '.$purchase_id);

		$this->purchase = $result[0];
	}
}

	
