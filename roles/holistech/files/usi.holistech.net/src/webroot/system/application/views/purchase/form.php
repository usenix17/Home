<?
	$continue = $this->sayings->input('Submit','TYPE=BUTTON NAME=submit ONCLICK="Purchase.submit_form()"');
	
	// Set the default number of codes
	if ( $this->input->post('num_codes') === FALSE )
		$_POST['num_codes'] = 1;
?>
<?=$header?>
				
<FORM METHOD=POST ID="purchase_form">

	<CENTER>
		<TABLE WIDTH="50%" CLASS=formtable>

			<?=FormTable::row('First Name','first_name');?>
			<?=FormTable::row('Last Name','last_name');?>
			<?=FormTable::row('Phone Number','phone','phone');?>
			<?=FormTable::row('E-Mail Address','email');?>
			<?=FormTable::row('Quantity','num_codes');?>

		</TABLE>

		<P ALIGN=RIGHT><?=$continue?></P>
		
		<?=$footer?>
	</CENTER>

</FORM>
