<?$this->load->helper('form');?>
<P><?=count($reasons)<count($codes)?'Some':'All'?> of the codes pending refund have been used.  Refunding will cancel the accounts that were created:</P>

<UL>
	<? foreach ( $reasons as $r ): ?>
	<LI><?=$r?></LI>
	<? endforeach; ?>
</UL>

<P>Do you want to continue?</P>
<P ID="tech_support_refund_verify">
	<?
		echo form_hidden('user_id',$user_id);
		foreach ( $codes as $c )
			echo form_hidden('codes[]',$c);
	?>
	<INPUT TYPE=BUTTON VALUE="Yes" ONCLICK="tech_support.refund_verify()">
	<INPUT TYPE=BUTTON VALUE="No" ONCLICK="tech_support.cancel()">
</P>
