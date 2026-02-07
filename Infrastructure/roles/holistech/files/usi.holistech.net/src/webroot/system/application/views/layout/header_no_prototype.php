<?
	$scripts = array();
	foreach ( array_merge( glob(BASEPATH.'application/js/*.js'), glob(BASEPATH.'application/js/*/*.js') ) as $script )
	{
		$scripts[] = str_replace(BASEPATH.'application/js/','',$script);
	}
?>

<LINK REL='stylesheet' TYPE='text/css' MEDIA='all' HREF='<?=base_url()?>/css/all.css' />

<SCRIPT SRC='<?=base_url()?>js/jquery/jquery-1.3.1.js' TYPE='text/javascript'></SCRIPT>
<SCRIPT SRC='<?=base_url()?>js/jquery/jquery-ui-personalized-1.6rc6.js' TYPE='text/javascript'></SCRIPT>
<SCRIPT SRC='<?=base_url()?>js/php.default.namespaced.min.js' TYPE='text/javascript'></SCRIPT>
<SCRIPT SRC='<?=base_url()?>js/jquery.tablesorter.js' TYPE='text/javascript'></SCRIPT>
<SCRIPT SRC='<?=base_url()?>js/jquery.tablesorter.pager.js' TYPE='text/javascript'></SCRIPT>
<SCRIPT>
	$J = jQuery.noConflict();
	$P = new PHP_JS();
	var BASEURL='<?=base_url();?>';
</SCRIPT>
<? foreach ( $scripts as $script ): ?>
<SCRIPT SRC='<?=base_url()?>layout/script/<?=$script?>' TYPE='text/javascript'></SCRIPT>
<? endforeach; ?>
