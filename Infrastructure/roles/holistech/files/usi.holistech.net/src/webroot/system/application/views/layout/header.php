<?
	$scripts = array();
	foreach ( array_merge( glob(BASEPATH.'application/js/*/*.js'), glob(BASEPATH.'application/js/*.js') ) as $script )
	{
		$scripts[] = str_replace(BASEPATH.'application/js/','',$script);
	}
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<LINK REL="stylesheet" TYPE="text/css" MEDIA="all" HREF="<?=base_url()?>layout/css" />

<SCRIPT>var BASEURL='<?=base_url();?>';</SCRIPT>
<?/* foreach ( $scripts as $script ): ?>
<SCRIPT SRC="<?=base_url()?>layout/script/<?=$script?>" TYPE="text/javascript"></SCRIPT>
<? endforeach; */?>
<SCRIPT SRC="<?=base_url()?>layout/js/" TYPE="text/javascript"></SCRIPT>

<?
	// Create special base_url for prototype and script.aculo.us
	// since script.aculo.us wants to bootstrap its own files, and that
	// messes with my automatic script inclusion engine
	$url = preg_replace("#".COURSENAME."/$#",'',base_url());
?>
<!--SCRIPT SRC='<?=$url?>js/prototype.js' TYPE='text/javascript'></SCRIPT>
<SCRIPT SRC='<?=$url?>js/script.aculo.us/scriptaculous.js' TYPE='text/javascript'></SCRIPT-->
<SCRIPT SRC='<?=$url?>js/jquery-cluetip/jquery.cluetip.all.min.js' TYPE='text/javascript'></SCRIPT>
<LINK REL="stylesheet" TYPE="text/css" MEDIA="all" HREF="<?=$url?>js/jquery-cluetip/jquery.cluetip.css" />
<BASE HREF="<?=base_url()?>">

