<H2><?=
	lang(array(
		'EN' => 'To continue with your '.($code?'registration':'purchase').', you must log in or create an account.',
		'ES' => 'Para continuar con su '.($code?'registraci&oacute;n':'compra').', debe entrar al sistema o crear una cuenta.',
	))
?></H2>

<P>
<?
	if ( ! $myself )
		echo lang(array(
			'EN' => 'This is the account you will be using to make your purchase.  
				It does not have to be the one taking the course.',
			'ES' => "Esta cuenta s&oacute;lo es para hacer la compra.
				No tiene que ser la cuenta que va a tomar el curso."				
			));
	elseif ( $num_codes > 1 )
		echo lang(array(
			'EN' => 'After your purchase, this account will be registered in this course, and you will be given '
				.(string)($num_codes-1)
				.' additional registration code'
				.($num_codes==2?'':'s')
				.'.',
			'ES' => 'Despu&eacute;s de su compra, esta cuenta se registra en el curso, y le damos '
				.$num_codes-1
				.($num_codes==2?
					' c&oacute;digo de registraci&oacute;n adicional.':
					' c&oacute;digos de registraci&oacute;n adicionales.')
				.'.',
			));
?>

</P>

<TABLE WIDTH="100%" CLASS=view>
	<TR>
		<TD WIDTH="33%" ALIGN=CENTER>
			<INPUT TYPE=RADIO NAME="purchase_RADIO_create" ID="purchase_RADIO_create_yes" VALUE='yes' CHECKED>
			<LABEL CLASS="purchase_radio" FOR="purchase_RADIO_create_yes"><?=lang(array(
				'EN'=>'I would like to create a new account.',
				'ES'=>'Yo quisiera crear una cuenta nueva.'
			))?></LABEL>
		</TD>
		<TD WIDTH="33%" ALIGN=CENTER>
			<INPUT TYPE=RADIO NAME="purchase_RADIO_create" ID="purchase_RADIO_create_yes_no" VALUE='no'>
			<LABEL CLASS="purchase_radio" FOR="purchase_RADIO_create_yes_no"><?=lang(array(
				'EN'=>'I already have an account.',
				'ES'=>'Ya tengo una cuenta.'
			))?></LABEL>
		</TD>
	</TR>
</TABLE>

<FORM ID="purchase_create_user_FORM" ONSUBMIT="return false;">
<INPUT TYPE=HIDDEN NAME=code VALUE="<?=$code?>">
<INPUT TYPE=HIDDEN NAME=myself VALUE="<?=$myself?>">
<INPUT TYPE=HIDDEN NAME=num_codes VALUE="<?=$num_codes?>">

<TABLE WIDTH="100%" BORDER=0 CLASS=view>
	<TR>
		<TD WIDTH="50%">
			<H2>Login Information</H2>
			<TABLE CLASS="formtable">
				<?
					if ( $GLOBALS['course']->login_type == 'username' )
						echo Formtable::row('* '.say('Username').':','username');
					else {
						echo Formtable::row('* '.say('E-Mail Address').':','email');
					}

					echo Formtable::row('* '.say('Password').':','password','password');
					echo Formtable::row('* '.say('Password Verify').':','password_verify','password');
				?>
			</TABLE>
			<H2>Personal Information</H2>
			<TABLE CLASS="formtable">
				<?
					echo Formtable::row('* '.say('Legal Full Name').':','realName');
					echo Formtable::row(say('Address').' 1:','address1');
					echo Formtable::row(say('Address').' 2:','address2');
					echo Formtable::row(say('City').':','city');
					echo Formtable::row(say('State').':','state','state');
					echo Formtable::row(say('ZIP').':','zip');

					if ( $GLOBALS['course']->login_type == 'username' )
						echo Formtable::row(say('E-Mail Address').':','email');

					echo Formtable::row(say('Phone').':','phone','phone');
				?>
			</TABLE>
		</TD>
		<TD WIDTH="50%">
			<H2>Demographics</H2>
			<TABLE CLASS="formtable">
				<?
			echo Formtable::row(say('Employer').':','employer');
			echo Formtable::row(say('Year Born').':','year_born');
			echo Formtable::row(say('Gender').':','gender','dropdown',array(
				'' => '',
				'Male' => 'Male',
				'Female' => 'Female',
				));
			echo FormTable::row(say('Ethnicity').':','ethnicity','dropdown',array(
				'' => '',
				'American Indian or Alaska Native' => 'American Indian or Alaska Native',
				'Asian' => 'Asian',
				'Black or African-American' => 'Black or African-American',
				'Hispanic or Latino' => 'Hispanic or Latino',
				'Native Hawaiian or Other Pacific Islander' => 'Native Hawaiian or Other Pacific Islander',
				'White' => 'White',
				'Other' => 'Other',
			));
			echo FormTable::row(say('Education').':','education','dropdown',array(
				'' => '',
				'Some High School' => 'Some High School',
				'High School' => 'High School',
				'Some College' => 'Some College',
				'College' => 'College',
				'Masters' => 'Masters',
				'Doctorate' => 'Doctorate',
				'Other' => 'Other',
			));
			echo $custom;
				?>
			</TABLE>
		</TD>
	</TR>
</TABLE>
<P><?=lang(array(
	'EN' => '* = required field',
	'ES' => '* = campo necesario',
));?></P>

</FORM>

<CENTER>
<FORM ID="purchase_login_form" ONSUBMIT="return false;">
<INPUT TYPE=HIDDEN NAME=code VALUE="<?=$code?>">
<INPUT TYPE=HIDDEN NAME=myself VALUE="<?=$myself?>">
<INPUT TYPE=HIDDEN NAME=num_codes VALUE="<?=$num_codes?>">

<TABLE WIDTH="50%" BORDER=0 CLASS=view ID="purchase_login_table">
	<TR>
		<TD>
			<H2><?=lang(array(
				'EN' => 'Please log in:',
				'ES' => 'Favor de entrar el sistema',
			))?></H2>
			<TABLE CLASS=formtable><?
				if ( $GLOBALS['course']->login_type == 'email' )
					echo Formtable::row(say('E-Mail Address').':','email');
				else
					echo Formtable::row(say('Username').':','username');
				echo Formtable::row(say('Password').':','password','password');
			?></TABLE>
		</TD>
	</TR>
</TABLE>
</FORM>
</CENTER>

<P ALIGN="RIGHT">
	<?=$this->sayings->input('Continue','TYPE=BUTTON ONCLICK="Purchase.enroll()"')?>
	<?=$this->sayings->input('Cancel','TYPE=BUTTON ONCLICK="pager.unpaged_show(\'/users/show_login\')"')?>
</P>

<SCRIPT>
	$J('INPUT[name=purchase_RADIO_create]').change(function () {
		if ( $J(this).val() == 'yes' ) {
			$J('#purchase_login_form').hide();
			$J('#purchase_create_user_FORM').show();
		} else {
			$J('#purchase_login_form').show();
			$J('#purchase_create_user_FORM').hide();
		}
	});
	$J("#purchase_login_form INPUT").keypress(function (ev) {
		if ( ev.keyCode == '13' )
		{
			Purchase.enroll();
		}
	});
</SCRIPT>


