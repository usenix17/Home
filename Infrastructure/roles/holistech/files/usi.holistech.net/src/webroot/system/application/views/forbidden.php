<P><?=lang(array(
	'EN' => 'To see this page, you must first complete "'.$requirement.'."',
	'ES' => 'Para ver esta p&aacute;gina, necesita completar "'.$requirement.'."',
))?></P>
<SCRIPT>
	pager.forbidden('<?=$module?>',<?=$page?>);
</SCRIPT>
