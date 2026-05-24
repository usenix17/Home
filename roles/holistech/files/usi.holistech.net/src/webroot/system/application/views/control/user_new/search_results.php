<? $this->load->helper('phone'); ?>

<TABLE CLASS="ticTacToe tech_support_results" WIDTH="100%">
	<TR>
		<TH>Real Name</TH>
		<TH>E-Mail</TH>
		<TH>Phone</TH>
		<TH>Purch.</TH>
		<TH>Enr.</TH>
		<TH>Last Login</TH>
		<TH></TH>
	</TR>
	<? foreach ( $results as $r ):
		$class = ( $r['num_purchases'] + $r['num_enrollments'] > 0 ? 'hit' : 'miss' );
	?>
	<TR CLASS="<?=$class?>">
		<TD><?=$r['realName']?></TD>
		<TD><?=$r['email']?></TD>
		<TD><?=Phone::format($r['phone'])?></TD>
		<TD ALIGN=CENTER><?=$r['num_purchases']?></TD>
		<TD ALIGN=CENTER><?=$r['num_enrollments']?></TD>
		<TD><?=$r['last_login'] === NULL ? '' : date('n/j/y H:i',strtotime($r['last_login']))?></TD>
		<TD>
			<SPAN ID="tech_support_details_<?=$r['user_id']?>_show_button">
				<A HREF="javascript:tech_support.show_details('<?=$r['user_id']?>')">Details</A>
			</SPAN>
			<SPAN ID="tech_support_details_<?=$r['user_id']?>_hide_button" STYLE='display: none;'>
				<A HREF="javascript:tech_support.hide_details('<?=$r['user_id']?>')">Hide</A>
			</SPAN>
		</TD>
	</TR>
	<TR ID="tech_support_details_<?=$r['user_id']?>_TR" STYLE='display: none;' CLASS='tech_support_details'><TD ID="tech_support_details_<?=$r['user_id']?>_TD" COLSPAN=7 WIDTH="100%" STYLE='border: 1px solid black; border-top: 0px; text-align: left;'></TD></TR>
	<? endforeach; ?>
</TABLE>

<? if ( count($results) == 1 ): ?>
<SCRIPT>
	tech_support.show_details('<?=$results[0]['user_id']?>');
</SCRIPT>
<? endif; ?>
