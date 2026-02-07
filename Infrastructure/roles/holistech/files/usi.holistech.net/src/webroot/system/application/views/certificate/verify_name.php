<H1 ALIGN=CENTER><?=lang(array(
	'EN' => 'Congratulations!',
	'ES' => '&iexcl;Felicitaciones!',
));?></H1>

<P ALIGN=CENTER><?=lang(array(
	'EN' => 'You have earned a certificate in this course!  
		Please verify the spelling of your full name and click "Get Certificate"',
	'ES' => '&iexcl;Usted gan&oacute; un certificado en este curso!  
		Por favor, confirme su nombre completo y haga click en "Conseguir Certificado"',
));?></P>

<P ID="certificate_verify_name" ALIGN=CENTER>
	<INPUT STYLE="width: 50%; font-size: 24pt;" NAME="realName" VALUE="<?=$user->realName?>">
</P>
<P ALIGN=CENTER>
	<A HREF="javascript:certificate.certify();" STYLE="font-size: 24pt"><?=lang(array(
		'EN' => 'Get Certificate',
		'ES' => 'Conseguir Certificado',
	))?></A>
</P>

<P ALIGN=CENTER>
<A HREF="http://get.adobe.com/reader/" TARGET="_BLANK"><IMG SRC="<?=base_url()?>../images/get_adobe_reader.gif" BORDER=0></A>
</P>
