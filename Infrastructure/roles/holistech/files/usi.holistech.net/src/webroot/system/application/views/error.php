<ERROR>
<?
	$id = 'ERROR'.rand(10000,99999);
?>
<DIV ID="<?=$id?>">
	<?=errors(TRUE);?>
</DIV>
<SCRIPT>
	$J('#<?=$id?>').dialog({ 
		modal: 		true,
		buttons:	{
			'OK':	function() { $J(this).dialog("close"); }
				},
		title:		'Error'
	});
	$J('IMG.loader').hide();
</SCRIPT>
	
