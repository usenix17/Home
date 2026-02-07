<?
	$again = $this->sayings->input('Purchase Again','TYPE=BUTTON ONCLICK="Purchase.purchase_again()"');
	$email = $this->sayings->input('Email My Purchase','TYPE=BUTTON ONCLICK="Purchase.email()"');
?>
<?=$header?>
 
<P ALIGN=CENTER><?=$again?> [SAY or] <?=$email?></P>
