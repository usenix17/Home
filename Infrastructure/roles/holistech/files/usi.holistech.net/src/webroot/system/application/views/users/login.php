<?
	$throbber_src = base_url() . '../images/ajax-loader.gif';

?>
<H1><?=say('Login');?></H1>
<?=$text?>

<TABLE WIDTH="100%" CLASS=view ID="login_table">
	<TR>
		<? if ( ! $GLOBALS['course']->useCAS ): ?>
            <TD WIDTH="50%">
                <H2><?=say('New Users')?>:</H2>
                <? if ( $GLOBALS['course']->useCodes ): ?>
                    <? if ( $GLOBALS['course']->useEbiz ): ?>
                        <P ID="purchase" ALIGN=CENTER>
                            <A HREF="javascript:login.purchase()" 
                            STYLE="font-size: 24pt"><?=say('Purchase')?></A>

                            <BR><BR>

                            <?=say('or')?>
                        </P>
                    <? endif; ?>

                    <DIV ID="enter_code" STYLE="display: none">
                    <FORM METHOD=POST ONSUBMIT="return false;" ID=register_form>
                        <INPUT TYPE=hidden NAME=action VALUE=register>

                        <TABLE CLASS=formtable>
                            <?=Formtable::row(say('Registration Code'),'code')?>
                        </TABLE>

                        <P ALIGN=CENTER>
                            <SPAN ID="users_login-register_button">
                                <?=$this->sayings->input('Register',' 
                                ID="users_login_register_button" TYPE=BUTTON 
                                ONCLICK="login.enter_code()"')?>
                            </SPAN>
                            <SPAN ID="users_login-register_throbber" STYLE="display: none;">
                                <IMG SRC="<?=$throbber_src?>">
                            </SPAN>
                            <?=$this->sayings->input('Cancel','TYPE=BUTTON 
                            ONCLICK="login.hide_enter_code()"')?>
                        </P>

                    </FORM>
                    </DIV>
                    <DIV ID="pre_code">
                        <P ALIGN=CENTER>
                            <A HREF="javascript:login.show_enter_code()" 
                            STYLE="font-size: 24pt"><?=say('Enter a Code')?></A>
                            <BR>
                            <?=say('that you have been issued')?>
                        </P>
                    </DIV>
                <? elseif ( $GLOBALS['course']->openReg ): ?>
                    <P ALIGN=CENTER>
                        <A HREF="javascript:login.purchase()" 
                        STYLE="font-size: 24pt"><?=say('Register')?></A>
                    </P>
                <? else: ?>
                    <P ALIGN=CENTER><?=lang(array(
                        'EN' => 'Please contact the course administrator for registration information.',
                        'ES' => 'Favor de contactar el administrador del curso por informaci&ocaute;n
                            de registracion.',
                    ));?></P>
                <? endif; ?>
            </TD>
            <TD WIDTH="50%">
			<H2><?=say('Existing Users')?>:</H2>
        <? else: // useCAS = TRUE ?>
            <TD WIDTH="100%">
            <P ALIGN=CENTER>
                <A HREF="users/cas_login" 
                STYLE="font-size: 24pt"><?=say('Log In at')?> <?=$GLOBALS['course']->cas_host?></A>
            </P>

            <P ALIGN=CENTER ID="non_cas_login_link">
                <A HREF="javascript:login.show_login_form()">Non-CAS Login</A>
            </P>
        <? endif; ?>

			<FORM METHOD=POST ONSUBMIT="login.do_login(); return false;" ID="login_form" STYLE="<?= $GLOBALS['course']->useCAS ? 'display: none;' : '' ?>">
				<TABLE CLASS=formtable><?
					if ( $GLOBALS['course']->login_type == 'email' )
						echo Formtable::row(say('E-Mail Address').':','email');
					else
						echo Formtable::row(say('Username').':','username');
					echo Formtable::row(say('Password').':','password','password');
				?></TABLE>
				<P ALIGN=RIGHT>
					<SPAN ID="users_login-button"><?=$this->sayings->input('Log In',
						'TYPE=SUBMIT')?></SPAN>
					<SPAN ID="users_login-throbber" STYLE="display: none;">
						<IMG SRC="<?=$throbber_src?>">
					</SPAN>
				</P>
				<? if( 0&&! empty($GLOBALS['course']->certificate) ): ?>
					<P ALIGN=CENTER>
						<A HREF="javascript:login.emailCert()" STYLE="font-size: 18pt"
						><?=say('Lost your Certificate?')?></A>
					</P>
				<? endif; ?>
			</FORM>
		</TD>
	</TR>
</TABLE>

<SCRIPT>
	$J("#login_form INPUT").keypress(function (ev) {
		if ( ev.keyCode == '13' )
		{
			login.do_login();
		}
	});
	$J("#register_form INPUT").keypress(function (ev) {
		if ( ev.keyCode == '13' )
		{
			login.enter_code();
		}
	});
</SCRIPT>
				
