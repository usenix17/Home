<H2><?=lang(array(
	'EN' => "Thank you for your purchase!",
	'ES' => "&iexcl;Gracias por su compra!",
))?></H2>

<DIV ID="control_purchase_thank_you_DIV">
<P ALIGN=CENTER><?=lang(array(
	'EN' => 'Please wait a moment while your order is processed...',
	'ES' => 'Por favor espere un momento mientras que su orden est&aacute; completado...',
));?></P>
<P ALIGN=CENTER><IMG SRC="<?=base_url()?>../images/ajax-loader.gif"></P>
</DIV>

<SCRIPT>
	Control_Purchases.purchase_id = "<?=$purchase_id?>";
	Control_Purchases.start_time = new Date();

	Control_Purchases.poll();
</SCRIPT>

