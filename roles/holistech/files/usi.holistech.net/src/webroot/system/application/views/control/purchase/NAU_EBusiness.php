<P><?=lang(array(
	'EN' => 'You will now be directed to NAU\'s EBusiness site to complete your purchase.',
	'ES' => 'Ahora se va al sitio de EBusiness de NAU para completar su compra',
));?></P>

<? 
	$trans_desc = $purchase['num_codes'] . ' registrations in ' . $GLOBALS['course']->displayName; 
	$name = explode(' ', $GLOBALS['user']->realName);
	$first_name = array_shift($name);
	$last_name = implode(' ',$name);
?>

<FORM METHOD=POST ACTION="<?=$GLOBALS['course']->ebizURL?>">
<INPUT TYPE=HIDDEN NAME="LMID" VALUE="<?=$GLOBALS['course']->lmid?>">
<INPUT TYPE=HIDDEN NAME="unique_id" VALUE="<?=$purchase['purchase_id']?>">
<INPUT TYPE=HIDDEN NAME="sTotal" VALUE="<?=$purchase['amount']?>">
<INPUT TYPE=HIDDEN NAME="webTitle" VALUE="<?=$GLOBALS['course']->displayName?>">
<INPUT TYPE=HIDDEN NAME="contact_info" VALUE="<?=$GLOBALS['course']->contactInfo?>">
<INPUT TYPE=HIDDEN NAME="Trans_Desc" VALUE="<?=$trans_desc?>">
<INPUT TYPE=HIDDEN NAME="return_url" VALUE="<?=base_url()?>">
<INPUT TYPE=HIDDEN NAME="BILL_CUSTOMER_FIRSTNAME" VALUE="<?=$first_name?>">
<INPUT TYPE=HIDDEN NAME="BILL_CUSTOMER_LASTNAME" VALUE="<?=$last_name?>">
<INPUT TYPE=HIDDEN NAME="BILL_CUSTOMER_EMAIL" VALUE="<?=$GLOBALS['user']->email?>">
<INPUT TYPE=HIDDEN NAME="BILL_CUSTOMER_PHONE" VALUE="<?=$GLOBALS['user']->phone?>">
<INPUT TYPE=HIDDEN NAME="return_html" VALUE="&lt;CENTER&gt;&lt;INPUT TYPE=BUTTON ONCLICK='location.href=&quot;<?=base_url()?>/control_purchases/complete/<?=$purchase['purchase_id']?>&quot;' VALUE='Continue'&gt;&lt;/CENTER&gt;&lt;P&gt;Please click continue to complete your order!&lt;/P&gt;">
<P ALIGN=CENTER>
<INPUT TYPE=SUBMIT VALUE="Continue Securely">
<INPUT TYPE=BUTTON ONCLICK="window.location.reload()" VALUE="Cancel">
</P>
</FORM>
