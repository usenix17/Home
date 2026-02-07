<DIV ID="tech_support_details_<?=$user_id?>">
<?
	$this->load->helper('formtable');
	$this->load->helper('form');
	$_POST['email'] = $user->email;
	echo form_hidden('user_id',$user_id);
?>
<H1><?=$user->realName?> (<A HREF="javascript:tech_support.reset_password('<?=$user_id?>');">Reset Password</A>)</H1>
<TABLE CLASS=formTable>
	<TR><TH><?=say('E-Mail Address')?>:&nbsp;</TH>
	<TD><?=formTable::field('email')?></TD>
	<TD><INPUT TYPE=button ONCLICK="tech_support.save_email('<?=$user_id?>');" VALUE="Save Email"></TD></TR>
</TABLE>

<H2>Purchases</H2>

<? if ( count($purchases) ): ?>
<TABLE WIDTH="100%" CLASS=ticTacToe>
	<TR>
		<TH>Order Number</TH>
		<TH>Course</TH>
		<TH>Time</TH>
		<TH>Amount</TH>
		<TH>Status</TH>
		<TH>Codes</TH>
	</TR>

	<? foreach( $purchases as $p ): ?>
	<TR>
		<TD><?=$p['purchase_id']?></TD>
		<TD><?=$p['course']?></TD>
		<TD><?=date('n/j/y H:i',strtotime( empty($p['time_in']) ? $p['time_started'] : $p['time_in'] ))?></TD>
		<TD>$<?printf('%01.2f',$p['amount'])?></TD>
		<TD>
			<?=$p['status']?>
			<? if ( $p['status'] == 'Pending' ): ?>
				(<A HREF="javascript:tech_support.fulfill('<?=$p['purchase_id']?>');">Fulfill</A>)
			<? endif; ?>
		</TD>
		<TD ID="tech_support_details_<?=$p['purchase_id']?>_codes_TD" STYLE="padding: 0;">
			<? if ( $p['status'] != 'Pending' ): ?>
			<TABLE WIDTH="100%" CLASS='ticTacToe codes_table' STYLE="margin-bottom: 0px;">
				<? foreach ( $codes[$p['purchase_id']] as $c ): ?>
				<TR>
					<TD><?=$c['code']?></TD>
					<TD WIDTH="100%">
						<?
							$text = array();
							if ( !empty($c['used_by_realName']) )
								$text[] = $c['used_by_realName'];
							if ( $c['enrollment_status']=='Completed' )
								$text[] = '(Completed)';
							if ( $c['status']=='Refunded' )
								$text[] = '(Refunded)';
							if ( $c['status']=='Voided' )
								$text[] = '(Voided)';
							echo implode('<BR>',$text);
						?>
					</TD>
					<TD><INPUT TYPE=CHECKBOX NAME="codes[]" VALUE="<?=$c['serial']?>"></TD>
				</TR>
			<? endforeach; ?>
			</TABLE>
			<? endif; ?>
		</TD>
	</TR>
	<? endforeach; ?>
</TABLE>
<P>
	<A HREF="javascript:tech_support.refund('<?=$user_id?>')">Refund Checked</A> | 
	<A HREF="javascript:tech_support.email('<?=$user_id?>')">Email Checked</A> | 
	<A HREF="javascript:tech_support.email_all('<?=$user_id?>')">Email All Unused Codes</A>  
</P>
<? else: ?>
<I>This user has not made any purchases</I>
<? endif; ?>

<H2>Enrollments</H2>
<? if ( count($enrollments) ): ?>
<TABLE WIDTH="100%" CLASS=ticTacToe>
	<TR>
		<TH>Course</TH>
		<TH>Enrollment</TH>
		<TH>Completion</TH>
		<TH>Certificate</TH>
		<TH>Status</TH>
	</TR>

	<? foreach( $enrollments as $e ): ?>
	<TR>
		<TD><?=$e['course']?> - <?=$e['displayName']?></TD>
		<TD><?=date('n/j/y H:i',strtotime($e['date']))?></TD>
		<TD><?=( empty($e['certification_time']) ? $e['percent_complete'].'%' : date('n/j/y H:i',strtotime($e['certification_time'])))?></TD>
		<TD><?
			if ( $e['course_certifies'] ) {
				if ( $e['has_certified'] ) {
					echo "<A HREF=\"".base_url()."certificate/view/{$e['enrollment_id']}\" TARGET='_BLANK'>View Certificate</A>";
					echo "<BR><A HREF=\"javascript:tech_support.email_certificate({$user_id},{$e['enrollment_id']})\">E-Mail Certificate</A>";
				} else {
					echo $e['can_certify'] ? 'User meets requirements' : 'User DOES NOT meet requirements';
					if ( $e['status'] === 'Enrolled' )
						echo "<BR><A HREF='javascript:tech_support.certify({$user_id},\"{$e['enrollment_id']}\")'>Certify User</A>";
				}
			} else {
				echo "<I>Course doesn't certify</I>";
			}
		?></TD>
		<TD>
			<?=$e['status'];?>
			<SPAN ID="control_enrollments_details_<?=$e['enrollment_id']?>_show_button">
				<A HREF="javascript:control_enrollments.show_details('<?=$e['enrollment_id']?>')">Details</A>
			</SPAN>
			<SPAN ID="control_enrollments_details_<?=$e['enrollment_id']?>_hide_button" STYLE='display: none;'>
				<A HREF="javascript:control_enrollments.hide_details('<?=$e['enrollment_id']?>')">Hide</A>
			</SPAN>
		</TD>
	</TR>
	<TR ID="control_enrollments_details_<?=$e['enrollment_id']?>_TR" STYLE='display: none;' CLASS='tech_support_details'><TD ID="control_enrollments_details_<?=$e['enrollment_id']?>_TD" COLSPAN=6 WIDTH="100%" STYLE='border: 1px solid black; border-top: 0px; text-align: left;'></TD></TR>
	<? endforeach; ?>
</TABLE>
<? else: ?>
<I>This user is not enrolled in any courses.</I>
<? endif; ?>
</DIV>
