<H2><?=
	lang(array(
		'EN' => 'I would like to purchase this course for:',
		'ES' => 'Yo quiero comprar este curso para:',
	))
?></H2>

<TABLE WIDTH="100%">
	<TR>
		<TD WIDTH="33%" ALIGN=CENTER>
			<INPUT TYPE=CHECKBOX NAME="purchase_course_for_me" ID="purchase_course_for_me" VALUE='1'>
			<LABEL CLASS="purchase_radio" FOR="purchase_course_for_me"><?=lang(array('EN'=>'myself','ES'=>'m&iacute'))?></LABEL>
		</TD>
		<TD WIDTH="33%" ALIGN=CENTER>
			<INPUT TYPE=CHECKBOX NAME="purchase_course_for_other" ID="purchase_course_for_other" VALUE='1'>
			<INPUT TYPE=TEXT NAME="num_codes" ID="purchase_course_num_codes" MAXLENGTH=3> 
			<LABEL CLASS="purchase_radio" FOR="purchase_course_for_other"><?=lang(array('EN'=>'other people','ES'=>'otras personas'))?></LABEL>
		</TD>
	</TR>
</TABLE>

<P ID="purchase_text">
	<SPAN ID="num_codes">0</SPAN> 
	<SPAN CLASS='registration'><?=lang(array('EN'=>'registration','ES'=>'registracion')) ?></SPAN>
	<SPAN CLASS='registrations'><?=lang(array('EN'=>'registrations','ES'=>'registraciones')) ?></SPAN>
	&times;
	$<?=$price?> <?=lang(array('EN'=>'per registration','ES'=>'cada registraci&oacute;n'))?>
	=
	$<SPAN ID="total_price">0</SPAN>
</P>

<P ALIGN="CENTER">
	<?=$this->sayings->input('Continue','DISABLED=TRUE ID="purchase_select_continue" TYPE=BUTTON ONCLICK="Purchase.start_order()"')?>
	<?=$this->sayings->input('Cancel','TYPE=BUTTON ONCLICK="pager.unpaged_show(\'/users/show_login\')"')?>
	<BR>
	<?=lang(array(
		'EN' => 'We accept American Express&reg;, Visa, MasterCard&reg;, JCB, and Electronic Checks.',
		'ES' => 'Aceptamos American Express&reg;, Visa, MasterCard&reg;, JCB, and Electronic Checks.',
	))?>
</P>


<SCRIPT>
	purchase_price = <?=$price?>;
	$J('#purchase_course_num_codes').keyup(function () {
		evilie_num_codes = $J(this).val().replace(/[^0-9]/g,'');
		if ( evilie_num_codes > 0 )
			$J('#purchase_course_for_other').attr('checked',true);
		else
			$J('#purchase_course_for_other').attr('checked',false);

		$J(this).val(evilie_num_codes);
		purchase_select_update_total();
	});
	$J('#purchase_course_for_other').click(function () {
		if ( ! $J(this).attr('checked') )
			$J('#purchase_course_num_codes').val('');
		purchase_select_update_total();
	});
	$J('#purchase_course_for_me').click(function () {
		purchase_select_update_total();
	});
</SCRIPT>
