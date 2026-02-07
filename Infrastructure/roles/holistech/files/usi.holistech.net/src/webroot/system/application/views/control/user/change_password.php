<? $this->load->helper('form'); ?>
<DIV WIDTH="100%" ID="control_user-edit">

<H1 ID="control_user-name"><?=( $user->exists() ? $user->realName : "New User" )?></H1>

<FORM ID="control_user-edit_form" ONSUBMIT="return false;">
<?=form_hidden('user_id',$user->user_id)?>

<H2>Please Reset your Password:</H2>
<TABLE CLASS="formtable" ALIGN=CENTER STYLE="width: 50%;">
	<?
		echo Formtable::row('Password:','password','password');
		echo Formtable::row('Password Verify:','password_verify','password');
	?>
</TABLE>

</FORM>

<P ALIGN=RIGHT>
	<INPUT TYPE=BUTTON VALUE="Reset" ONCLICK="control_user_reset_password()">
</P>

</DIV>
