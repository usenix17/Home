<? $this->load->helper('form'); ?>
<DIV WIDTH="100%" ID="control_user-edit">

<H1 ID="control_user-name"><?=( $user->exists() ? $user->realName : "New User" )?></H1>

<FORM ID="control_user-edit_form" ONSUBMIT="return false;">
<?=form_hidden('user_id',$user->user_id)?>

<TABLE WIDTH="80%" BORDER=0 CLASS=view STYLE="margin-left: auto; margin-right: auto;">
	<TR>
		<TD WIDTH="100%">
            <? if ( ! $GLOBALS['course']->useCAS || substr($user->username,0,5) != "CAS::" ): ?>
                <H2>Login Information</H2>
                <TABLE CLASS="formtable">
                    <?
                        if ( $GLOBALS['course']->login_type == 'username' )
                            echo Formtable::row('* '.say('Username').':','username');
                        else {
                            echo Formtable::row('* '.say('E-Mail Address').':','email');
                            echo form_hidden('username',$user->username);
                        }

                        if ( $reset_button )
                            echo "<TR><TH>Password:</TH><TD><A HREF='javascript:tech_support.reset_password(\"{$user->user_id}\");'>Reset Password</A></TD></TR>";
                        else {
                            echo Formtable::row('* '.say('Password').':','password','password');
                            echo Formtable::row('* '.say('Password Verify').':','password_verify','password');
                        }
                    ?>
                </TABLE>
            <? endif; ?>
			<H2>Personal Information</H2>
			<TABLE CLASS="formtable">
				<?
                    if ( $GLOBALS['course']->useCAS ) {
                        echo Formtable::row('* '.say('E-Mail Address').':','email');
                        echo form_hidden('username',$user->username);
                    }
					echo Formtable::row('* '.say('Legal Full Name').':','realName');
					echo Formtable::row(say('Address').' 1:','address1');
					echo Formtable::row(say('Address').' 2:','address2');
					echo Formtable::row(say('City').':','city');
					echo Formtable::row(say('State').':','state','state');
					echo Formtable::row(say('ZIP').':','zip');

					if ( $GLOBALS['course']->login_type == 'username' )
						echo Formtable::row(say('E-Mail Address').':','email');

					echo Formtable::row(say('Phone').':','phone','phone');
                    echo $custom;
				?>
			</TABLE>
		</TD>
	</TR>
</TABLE>
</FORM>

<P><?=lang(array(
	'EN' => '* = required field',
	'ES' => '* = campo necesario',
));?></P>

<P ALIGN=RIGHT>
	<INPUT TYPE=BUTTON VALUE="Save" ONCLICK="control_user_save()">
</P>

<?/*<H2>Tokens</H2>
<?$user->token->dump();?>*/?>

</DIV>
