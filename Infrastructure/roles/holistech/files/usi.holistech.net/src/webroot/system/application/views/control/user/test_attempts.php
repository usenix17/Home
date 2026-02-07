
<TABLE WIDTH="100%" CLASS="ticTacToe">
	<TR>
		<TH>Date</TH>
		<TH>Score</TH>
		<TH>Results</TH>
	</TR>
	<? foreach ( $attempts as $a ): ?>
	<? $uniq = uniqid(); ?>
	<TR>
		<TD><?=date('l, j F Y g:iA',strtotime($a['time']))?></TD>
		<TD><?=$a['score'].'%'?></TD>
		<TD><A HREF="javascript:control_enrollments.show_test_results('<?=$uniq?>')">Show Results</A></TD>
	</TR>
	<TR  ID="<?=$uniq?>" STYLE='display: none;' CLASS='tech_support_details'>
		<TD COLSPAN=3 WIDTH="100%" STYLE='border: 1px solid black; border-top: 0px; text-align: left;'>
			<?=$a['html'];?>
		</TD>
	</TR>
	<? endforeach; ?>
</TABLE>
