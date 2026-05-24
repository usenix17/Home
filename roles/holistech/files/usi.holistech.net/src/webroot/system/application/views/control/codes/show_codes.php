<? if ( isset($new_codes) ): ?>

<H2>New Codes:</H2>
<? foreach ( $new_codes as $c ): ?>
	<?=$c['code']?><BR>
<? endforeach; ?>

<? endif; ?>

<DIV CLASS="control_frame_div">
<B>View:</B>
<INPUT TYPE=RADIO NAME="control_codes_show_codes_view_radio" VALUE='available' ID="control_codes_show_codes_view_radio_available" CHECKED>
<LABEL FOR="control_codes_show_codes_view_radio_available">Available</LABEL>
<INPUT TYPE=RADIO NAME="control_codes_show_codes_view_radio" VALUE='used' ID="control_codes_show_codes_view_radio_used">
<LABEL FOR="control_codes_show_codes_view_radio_used">Used</LABEL>
<INPUT TYPE=RADIO NAME="control_codes_show_codes_view_radio" VALUE='all' ID="control_codes_show_codes_view_radio_all">
<LABEL FOR="control_codes_show_codes_view_radio_all">All</LABEL>
</DIV>

<TABLE CLASS=ticTacToe WIDTH="100%" ID="control_codes_show_codes_table">
	<TR CLASS="available used"><TH>Code</TH><TH>Label</TH><TH>Time Created</TH><TH>Created By</TH><TH>Purchase ID</TH>
		<TH>Used By</TH><TH>Time Used</TH></TR>
	
	<? foreach ( $codes as $c ): ?>
	<TR CLASS="<?=($c['user_id']===NULL ? 'available' : 'used')?>">
		<TD><?=$c['code']?></TD>
		<TD><?=$c['label']?></TD>
		<TD><?=$c['time_created']?></TD>
		<TD><?=$c['created_by']?></TD>
		<TD><?=$c['purchase_id']?></TD>
		<TD><A HREF="javascript:control_show_user(<?=$c['user_id']?>)"
			><?=$c['used_by_realName']?></A></TD>
		<TD><?=$c['time_used']?></TD>
	</TR>
	<? endforeach; ?>
</TABLE>

<SCRIPT>
$J('INPUT[name=control_codes_show_codes_view_radio]').change(function () {
	val = $J('INPUT[name=control_codes_show_codes_view_radio]:checked').val();

	$J('#control_codes_show_codes_table TR').hide();

	if ( val=='available' || val=='all' )
		$J('#control_codes_show_codes_table TR.available').show();

	if ( val=='used' || val=='all' )
		$J('#control_codes_show_codes_table TR.used').show();
}).change();
</SCRIPT>

