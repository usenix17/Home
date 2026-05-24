<? $this->load->helper('phone'); ?>



<TABLE ALIGN=CENTER CLASS=pagination>
	<TR>
		<TD><? if ( $page == 0 ): echo '|&lt;'; else: ?><A HREF="javascript:tech_support.search(0)">|&lt;</A><?endif;?></TD>
		<TD><? if ( $page == 0 ): echo '&lt;'; else: ?><A HREF="javascript:tech_support.search(<?=$page-1?>)">&lt;</A><?endif;?></TD>
		<? for ( $i = 1; $i <= $num_pages; $i++ ): ?>
			<? if ( $page == $i-1 ): echo "<TD><B>{$i}</B></TD>"; else: ?>
				<TD><A HREF="javascript:tech_support.search(<?=$i-1?>)"><?=$i?></A></TD>
			<? endif; ?>
		<? endfor; ?>
		<TD><? if ( $page == $num_pages-1 ): echo '&gt;'; else: ?><A HREF="javascript:tech_support.search(<?=$page+1?>)">&gt;</A><?endif;?></TD>
		<TD><? if ( $page == $num_pages-1 ): echo '&gt;|'; else: ?><A HREF="javascript:tech_support.search(<?=$num_pages-1?>)">&gt;|</A><?endif;?></TD>
	</TR>
</TABLE>
<TABLE CLASS="ticTacToe tech_support_results" WIDTH="100%">
	<TR CLASS=header>
		<TH>Real Name</TH>
		<TH>E-Mail</TH>
		<!--TH>Phone</TH-->
		<? if ( $tech_support ): ?>
			<TH>Pu.</TH>
			<TH>En.</TH>
		<? endif; ?>
		<TH>Certs</TH>
		<TH COLSPAN=2>Last Login / Course</TH>
		<TH></TH>
	</TR>
	<? foreach ( $results as $r ):
		$class = ( $r['num_purchases'] + $r['num_enrollments'] > 0 ? 'hit' : 'miss' );
	?>
	<TR CLASS="<?=$class?>" USER_ID="<?=$r['user_id']?>">
		<TD><?=$r['realName']?></TD>
		<TD><?=$r['email']?></TD>
		<!--TD CLASS=nowrap><?=Phone::format($r['phone'])?></TD-->
		<? if ( $tech_support ): ?>
			<TD ALIGN=CENTER><?=$r['num_purchases']?></TD>
			<TD ALIGN=CENTER><?=$r['num_enrollments']?></TD>
		<? endif; ?>
		<TD ALIGN=CENTER><?=$r['num_certificates']?></TD>
		<TD CLASS=nowrap><?=$r['last_login'] === NULL ? '' : date('n/j/y H:i',strtotime($r['last_login']))?></TD>
		<TD CLASS=nowrap><?=$r['last_course']?></TD>
		<TD>
			<SPAN ID="tech_support_details_<?=$r['user_id']?>_show_button" CLASS="tech_support_details_button">
				<A HREF="javascript:tech_support.show_details('<?=$r['user_id']?>')">Details</A>
			</SPAN>
			<SPAN ID="tech_support_details_<?=$r['user_id']?>_hide_button" STYLE='display: none;' CLASS="tech_support_hide_button">
				<A HREF="javascript:tech_support.hide_details('<?=$r['user_id']?>')">Hide</A>
			</SPAN>
		</TD>
	</TR>
	<TR ID="tech_support_details_<?=$r['user_id']?>_TR" STYLE='display: none;' CLASS='tech_support_details'><TD ID="tech_support_details_<?=$r['user_id']?>_TD" COLSPAN=8 WIDTH="100%" STYLE='border: 1px solid black; border-top: 0px; text-align: left;'></TD></TR>
	<? endforeach; ?>
</TABLE>

<? if ( count($results) == 1 ): ?>
<SCRIPT>
	tech_support.show_details('<?=$results[0]['user_id']?>');
</SCRIPT>
<? endif; ?>

<SCRIPT>
	$J('TABLE.tech_support_results TR:not(.header):not(.tech_support_details)')
		.mouseover(function () { $J(this).addClass('selected'); })
		.mouseout(function () { $J(this).removeClass('selected'); })
		.click(function () {
			tech_support.show_details($J(this).attr('user_id'));
		});
</SCRIPT>

