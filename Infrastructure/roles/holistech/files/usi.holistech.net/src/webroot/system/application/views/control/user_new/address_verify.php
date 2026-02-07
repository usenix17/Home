<? 
	$this->load->helper('formtable'); 
	$user->set_post();
?>
<P>Please verify the address and update the contact information.  This address is where refund checks are mailed.  This name is what will be printed on the certificate.<P>
<DIV ID="tech_support_address_verify">
	<TABLE CLASS=formtable>
	<?
		echo Formtable::row('Legal Full Name:','realName');
		echo Formtable::row('Address 1:','address1');
		echo Formtable::row('Address 2:','address2');
		echo Formtable::row('City:','city');
		echo Formtable::row('State:','state','state');
		echo Formtable::row('ZIP:','zip');
		echo Formtable::row('E-Mail Address:','email');
		echo Formtable::row('Phone:','phone','phone');
	?>
	</TABLE>

	<P>
		<?
			echo form_hidden('user_id',$user_id);
			foreach ( $codes as $c )
				echo form_hidden('codes[]',$c);
		?>
		<INPUT TYPE=BUTTON VALUE="Continue" ONCLICK="tech_support.address_verify('<?=$destination?>')">
		<INPUT TYPE=BUTTON VALUE="Cancel" ONCLICK="tech_support.cancel()">
	</P>
</DIV>
