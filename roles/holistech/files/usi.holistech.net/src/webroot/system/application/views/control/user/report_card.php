<TABLE WIDTH="100%" CLASS="ticTacToe">
	<TR>
		<TH>Module</TH>
		<TH>Complete</TH>
		<TH>High Score</TH>
	</TR>
	<? foreach ( $report_card as $line ): ?>
	<? $uniq = uniqid(); ?>
	<TR>
		<TD><?=$line['name']?></TD>
		<TD><?=$line['passed'] ? 'Yes' : 'No'?></TD>
		<TD><?=$line['score'] === NULL ? '' : $line['score']."% (<A HREF=\"javascript:control_enrollments.show_module_details('{$line['enrollment_id']}','{$line['module_id']}','#{$uniq}')\">Details</A>)"?></TD>
	</TR>
	<TR ID="control_enrollments_module_details_<?=$line['enrollment_id']?>_<?=$line['module_id']?>_TR" STYLE='display: none;' CLASS='tech_support_details'><TD ID="<?=$uniq?>" COLSPAN=3 WIDTH="100%" STYLE='border: 1px solid black; border-top: 0px; text-align: left;'></TD></TR>
	<? endforeach; ?>
</TABLE>
