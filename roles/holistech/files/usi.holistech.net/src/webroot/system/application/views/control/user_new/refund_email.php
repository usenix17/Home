<P>Albert,</P>

<P>This is an automated refund request for az-hospitality.nau.edu.</P>

<P>Please refund $<?=$amount?> to:</P>

<P>
	<?=$user->realName?><BR>
	<?=$user->address1?><BR>
	<?=!empty($user->address2)?$user->address2."<BR>":''?>
	<?=$user->city?>, <?=$user->state?> <?=$user->zip?>
</P>

<P>for the following registration code purchase<?=count($purchases)>1?'s':''?>:</P>

<TABLE BORDER=1 CELLPADDING=3>
	<TR><TH>Unique ID</TH><TH>Code</TH><TH>Time Purchased</TH><TH>Amount</TH></TR>
	<? foreach ( $codes as $c ): ?>
	<TR>
		<TD><?=$c['purchase_id'];?></TD>
		<TD><?=Codes::generate($c['code']);?></TD>
		<TD><?=$c['time_created'];?></TD>
		<TD>$<?printf('%01.2f',$c['purchase_price']);?></TD>
	</TR>
	<? endforeach; ?>
</TABLE>
	

