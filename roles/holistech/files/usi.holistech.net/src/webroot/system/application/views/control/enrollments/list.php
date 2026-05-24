<? $baseurl = preg_replace("#".COURSENAME."/$#",'',base_url()); ?>

<TABLE WIDTH="100%" CLASS="ticTacToe control_enrollments_list">
	<TR>
		<TH>Course</TH>
		<TH>Enrolled On</TH>
		<TH>Completed On</TH>
		<TH>Certificate</TH>
		<TH>Status</TH>
	</TR>

	<? foreach( $enrollments as $e ): ?>
	<TR>
		<TD><A HREF="<?=$baseurl.$e['name']?>"><?=( empty($e['displayName']) ? $e['name'] : $e['displayName'])?></A></TD>
		<TD CLASS=nowrap><?=date('j M Y<\BR>\a\t g:iA',strtotime($e['date']))?></TD>
		<TD CLASS=nowrap><?=( empty($e['certification_time']) ? $e['percent_complete'].'%' : date('j M Y<\BR>\a\t g:iA',strtotime($e['certification_time'])))?></TD>
		<TD CLASS=nowrap><?
			if ( $e['course_certifies'] ) {
				if ( $e['has_certified'] ) 
					echo "<A HREF=\"".base_url()."certificate/view/{$e['enrollment_id']}\" TARGET='_BLANK'>View Certificate</A>";
			} else {
				echo "<I>Course doesn't certify</I>";
			}
		?></TD>
		<TD CLASS=nowrap><?=$e['status'];?></TD>
	</TR>
	<? endforeach; ?>
</TABLE>
