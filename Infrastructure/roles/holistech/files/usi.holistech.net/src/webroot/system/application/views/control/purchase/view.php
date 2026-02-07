<P><?
	if ( $purchase['num_codes'] == 1 )
		echo lang(array(
			'EN' => 'Here is the registration code that you purchased.',
			'ES' => 'Aqu&iacute; est&aacute; el c&oacute;digo de registraci&oacute; que ha comprado.',
		));
	else
		echo lang(array(
			'EN' => 'Here are the registration codes that you purchased.',
			'ES' => 'Aqu&iacute; est&aacute;n los c&oacute;digos de registraci&oacute; que ha comprado.',
		));
?></P>

<TABLE CLASS=ticTacToe>
	<TR>
		<TH><?=say('Registration Code')?></TH>
		<TH><?=lang(array(
			'EN' => 'Used By',
			'ES' => 'Usado por',
		))?></TH>
	<TR>

	<? foreach ( $codes as $c ): ?>
	<TR>
		<TD><?=$c['code']?></TD>
		<TD><?=$c['used_by_realName']?></TD>
	</TR>
	<? endforeach; ?>
</TABLE>

<? if ( ! $purchase['myself'] || $purchase['num_codes'] > 1 ): ?>
<P><?=lang(array(
	'EN' => 'These codes can be used by going to "'.base_url().'" and clicking on "Enter a Code".',
	'ES' => 'Estos c&oacute;digos se pueden usar por ir a  "'.base_url().'" y hacer click en "Inscribir un C&oacute;digo".',
));?></P>
<? endif; ?>

<? if ( $GLOBALS['user']->is_enrolled(COURSENAME) ): ?>
<P ALIGN=CENTER><A HREF="javascript:load('/users/init_pager','#HIDDEN')" STYLE="font-size: 24pt;"><?=lang(array(
	'EN' => 'Continue to the course',
	'ES' => 'Continuar al curso',
))?></A></P>
<? endif; ?>
