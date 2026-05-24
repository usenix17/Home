<? $baseurl = preg_replace("#".COURSENAME."/$#",'',base_url()); ?>
<DIV ID="control_user-enrollments">

<H1 ID="control_user-name"><?=( $user->exists() ? $user->realName : "New User" )?></H1>

<? foreach( $groups as $group => $description ): ?>
<H2 ONCLICK="control_enrollments.toggle_group('<?=$group?>');">
<SPAN STYLE="float: right; font-size: 10pt" ID="control_user-enrollment_group_<?=$group?>_hide">[Hide]</SPAN>
<SPAN STYLE="float: right; font-size: 10pt; display: none" ID="control_user-enrollment_group_<?=$group?>_show">[Show]</SPAN>
<SPAN><?=empty($description) ? $group : $description?></SPAN>
</H2>
<TABLE WIDTH="100%" CLASS="ticTacToe control_enrollments_list" ID="control_user-enrollment_group_<?=$group?>">
	<TR>
		<TH>Course</TH>
		<TH>Enrolled On</TH>
		<TH>Completed On</TH>
		<TH>Certificate</TH>
		<TH>Status</TH>
		<TH></TH>
	</TR>
	
	<? if ( array_key_exists($group,$grouped_enrollments) ): ?>
	<? foreach( $grouped_enrollments[$group] as $e ): ?>
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
		<TD CLASS=nowrap><?=$e['status'];?>
			<? if ( $e['status'] == 'Enrolled' 
				&& $GLOBALS['user']->has(new Token('auth','create_users',$e['name'])) ): ?>
			<BR>(<A HREF="javascript:control_enrollments.unenroll(<?=$e['enrollment_id']?>)">Unenroll</A>)
			<? endif; ?>
			<? if ( $e['status'] == 'Unenrolled' 
				&& $GLOBALS['user']->has(new Token('auth','create_users',$e['name'])) ): ?>
			<BR>(<A HREF="javascript:control_enrollments.reenroll(<?=$e['enrollment_id']?>)">Re-enroll</A>)
			<? endif; ?>
		</TD>
		<TD ALIGN=CENTER>
			<SPAN ID="control_enrollments_details_<?=$e['enrollment_id']?>_show_button">
				<A HREF="javascript:control_enrollments.show_details('<?=$e['enrollment_id']?>')">Details</A>
			</SPAN>
			<SPAN ID="control_enrollments_details_<?=$e['enrollment_id']?>_hide_button" STYLE='display: none;'>
				<A HREF="javascript:control_enrollments.hide_details('<?=$e['enrollment_id']?>')">Hide</A>
			</SPAN>
		</TD>
	</TR>
	<TR ID="control_enrollments_details_<?=$e['enrollment_id']?>_TR" STYLE='display: none;' CLASS='tech_support_details'><TD ID="control_enrollments_details_<?=$e['enrollment_id']?>_TD" COLSPAN=6 WIDTH="100%" STYLE='border: 1px solid black; border-top: 0px; text-align: left;'></TD></TR>
	<? endforeach; endif; ?>
	<? if ( array_key_exists($group,$available_courses) ): ?>
	<? foreach ( $available_courses[$group] as $course ): ?>
	<TR>	
		<TD><A HREF="<?=$baseurl.$course['name']?>"><?=( empty($course['displayName']) ? $course['name'] : $course['displayName'])?></A></TD>
		<TD COLSPAN=4 ALIGN=CENTER><I>Not Enrolled</I></TD>
		<? $uniq = uniqid(); ?>
		<TD ALIGN=CENTER><A HREF="javascript:control_enrollments.enroll(<?=$user->user_id?>,'<?=$course['name']?>','<?=$uniq?>')" ID="<?=$uniq?>">Enroll</A></TD>
	</TR>
	<? endforeach; endif; ?>
</TABLE>
<? endforeach; ?>

</DIV>
