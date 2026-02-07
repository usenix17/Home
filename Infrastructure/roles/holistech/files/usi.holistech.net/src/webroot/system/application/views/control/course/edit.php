<DIV WIDTH="100%" ID="control_course-edit">

<DIV ID="control_course-selector">
	<SELECT NAME='coursename'>
		<? foreach ( $course_list as $x => $name ): ?>
		<OPTION <?=($x==$coursename?'SELECTED':'')?> VALUE="<?=$x?>"><?=$name?></OPTION>
		<? endforeach; ?>
	</SELECT>
	<INPUT TYPE=BUTTON ONCLICK="control_course_edit()" VALUE="Edit">
</DIV>
<BR>

<FORM ID="control_course-edit_form">
<?=form_hidden('coursename',$coursename)?>

<STYLE TYPE="text/css">
TABLE.course-formtable { width: "100%"; }
TABLE.course-formtable TH { width: "50%"; text-align: right; padding-right: 10px; }
TABLE.course-formtable TD { width: "50%" }
TABLE.course-formtable TD INPUT[type=text] { width: "100%" }
</STYLE>


<TABLE WIDTH="100%"><TR>
	<TD WIDTH="50%">
		<H2>Course Details</H2>
		<TABLE BORDER=0 CLASS="formtable course-formtable">
		<TR><TH>Course Name:</TH><TD><?=$coursename?></TD></TR>
		<?
			echo Formtable::row('Course Title','displayName');
			echo Formtable::row('Primary Contact Email','email');
			echo Formtable::row('Tech Support Email','tech_support_email');
			echo Formtable::row('Layout','layout','dropdown',$layouts);
			echo Formtable::row('Languages Offered','languages');

			if ( $GLOBALS['user']->has(new Token('edit','quota',$coursename)) )
				echo Formtable::row('Quota','quota');
			echo Formtable::row('Grouping','group');
			echo Formtable::row('Allow Roaming IP','roamingIP','checkbox');
		?>
		</TABLE>

		<H2>Payment Options</H2>
		<TABLE BORDER=0 CLASS=course-formtable>
		<?
			echo Formtable::row('Price','price');
			echo Formtable::row('Use NAU EBusiness','useEbiz','checkbox');
		?>
		</TABLE>

		<DIV ID="control_course-ebiz_settings">
		<H2>NAU EBusiness Options</H2>
		<TABLE BORDER=0 CLASS=course-formtable>
		<?
			echo Formtable::row('LMID','lmid');
			echo Formtable::row('URL','ebizURL');
			echo Formtable::row('Contact Information','contactInfo');
		?>
		</DIV>
		</TABLE>

	</TD><TD WIDTH="50%">

		<H2>Course Registration</H2>
		<TABLE BORDER=0 CLASS=course-formtable>
		<?
			echo Formtable::row('Login Type','login_type','dropdown',array('email' => "E-Mail Address",'username' => 'Username'));
			echo Formtable::row('Use Registration Codes','useCodes','checkbox');
			echo Formtable::row('Allow Open Registration','openReg','checkbox');
			echo Formtable::row('Days after registration<BR>course expires','bdayExpire');
			echo Formtable::row('Days after completion<BR>course expires','expire');
		?>
		</TABLE>

		<H2>CAS</H2>
		<TABLE BORDER=0 CLASS=course-formtable>
		<?
			echo Formtable::row('Use CAS','useCAS','checkbox');
			echo Formtable::row('CAS Host','cas_host');
			echo Formtable::row('CAS Port','cas_port');
			echo Formtable::row('CAS Context','cas_context');
			echo Formtable::row('CAS Email Template (%u for username)','cas_email_template');
		?>
		</TABLE>

		<H2>Course Roster</H2>
		<TABLE BORDER=0 CLASS=course-formtable>
		<?
			echo Formtable::row('Roster File', 'roster_file');
			echo Formtable::row('Roster Username Field', 'roster_username_field');
			echo Formtable::row('Roster Email Address Field', 'roster_email_address_field');
			echo Formtable::row('Roster Real Name Fields', 'roster_real_name_fields');
			echo Formtable::row('Roster Report Filter Fields', 'roster_report_filter_fields');
			echo Formtable::row('Roster Report Restrict Fields', 'roster_report_restrict_fields');
		?>
		</TABLE>

		<H2>Testing Preferences</H2>
		<TABLE BORDER=0 CLASS=course-formtable>
		<?
			echo Formtable::row('Disable Questions After Answered Correctly','disableCorrectAnswers','checkbox');
			echo Formtable::row('Minimum Score on Tests','minScore');
		?>
		</TABLE>

		<P ALIGN=RIGHT>
			<INPUT TYPE=BUTTON VALUE="Save" ONCLICK="control_course_save()">
		</P>

	</TD>
</TR></TABLE>
</FORM>

</DIV>
