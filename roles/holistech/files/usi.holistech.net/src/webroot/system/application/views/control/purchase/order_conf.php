<H2><?=lang(array(
	'EN' => 'Here is the summary of your order:',
	'ES' => 'Aqu&iacute; est&aacute; el resumen de su orden:',
))?></H2>

<TABLE CLASS=ticTacToe WIDTH="100%">
	<TR>
		<TH WIDTH="40%"><?=lang(array('EN'=>'Description','ES'=>'Descripci&oacute;n'))?></TH>
		<TH WIDTH="20%"><?=lang(array('EN'=>'Unit Price','ES'=>'Precio de unidad'))?></TH>
		<TH WIDTH="20%"><?=lang(array('EN'=>'Quantity','ES'=>'Cantidad'))?></TH>
		<TH WIDTH="20%"><?=lang(array('EN'=>'Total','ES'=>'Total'))?></TH>
	</TR>
	<TR>
		<TD ALIGN=CENTER><?=$GLOBALS['course']->displayName?></TD>
		<TD ALIGN=CENTER>$<?printf('%01.2f',$GLOBALS['course']->price)?> USD</TD>
		<TD ALIGN=CENTER><?=$purchase['num_codes']?></TD>
		<TD ALIGN=CENTER>$<?printf('%01.2f',$purchase['amount'])?> USD</TD>
	</TR>
</TABLE>
