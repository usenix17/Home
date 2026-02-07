<!DOCTYPE html>
<HTML>

	<HEAD>

		<TITLE><?=strip_tags($GLOBALS['course']->displayName)?></TITLE>
        <link rel="icon" href="http://holistech.net/QI_icon.png" type="image/x-icon"> 
        <link rel="shortcut icon" href="http://holistech.net/QI_icon.png" type="image/x-icon"> 
		<meta http-equiv="X-UA-Compatible" content="IE=8">

		<? $this->load->view('layout/header'); ?>

	</HEAD>

	<BODY>

		<DIV CLASS="container_12">
	
			<DIV CLASS="grid_12 banner"><?=$banner?></DIV>
			<DIV CLASS="clear"></DIV>
	
			<DIV CLASS="grid_6 utility1" ID='utility1'>
				<?=$utility1?>
			</DIV>

			<DIV CLASS="grid_6 utility2" ID='utility2'>
				<?=$utility2?>
			</DIV>
			<DIV CLASS="clear"></DIV>

			<DIV CLASS="grid_12 spacer"></DIV>
			<DIV CLASS="clear"></DIV>

		</DIV>
		<DIV CLASS="container_12" ID=layout>
            <DIV ID=course_syllabus_label>
                Course Syllabus
            </DIV>
			<DIV CLASS="error-round red" ID='error_bg' STYLE="display: none">
				<DIV CLASS="TL"></DIV>
				<DIV CLASS="T"></DIV>
				<DIV CLASS="TR"></DIV>
				<DIV CLASS="L"></DIV>
				<DIV CLASS="M"></DIV>
				<DIV CLASS="R"></DIV>
				<DIV CLASS="BL"></DIV>
				<DIV CLASS="B"></DIV>
				<DIV CLASS="BR"></DIV>

				<DIV ID=error-wrapper><DIV ID=error><UL></UL></DIV></DIV>
			</DIV>

			<DIV CLASS="round" ID='content_bg'>
				<DIV CLASS="TL"></DIV>
				<DIV CLASS="T"></DIV>
				<DIV CLASS="TR"></DIV>
				<DIV CLASS="L"></DIV>
				<DIV CLASS="M"></DIV>
				<DIV CLASS="R"></DIV>
				<DIV CLASS="BL"></DIV>
				<DIV CLASS="B"></DIV>
				<DIV CLASS="BR"></DIV>

				<DIV CLASS="content" ID='pager'>
					<DIV CLASS="content" ID="content" >
						<DIV ID="modules" CLASS="modules">
						</DIV>
					</DIV>

					<A CLASS="left arrows" ONCLICK="clear_errors(); pager.prev()"></A>
					<A CLASS="right arrows" ONCLICK="clear_errors(); pager.next()"></A>

					<DIV ID='scrollbar'></DIV>
				</DIV>

				<DIV ID='unpaged'>

					<DIV ID="login"	STYLE="display: none;">
						<?=$login?>
					</DIV>

					<DIV ID='browser' STYLE="display: none;">
						<H1>Please upgrade your browser</H1>
						<P>Your web browser is not compatible with this website.  
						We recommend the following browsers:</P>

						<TABLE>
							<TR>
								<TD><SPAN CLASS="browser_icon chrome"></TD>
								<TD><A HREF="http://www.google.com/chrome">Google Chrome</A></TD>
							</TR>
							<TR>
								<TD><SPAN CLASS="browser_icon firefox"></TD>
								<TD><A HREF="http://www.mozilla.com/">Mozilla Firefox</A></TD>
							</TR>
							<TR>
								<TD><SPAN CLASS="browser_icon opera"></TD>
								<TD><A HREF="http://www.opera.com/">Opera</A></TD>
							</TR>
							<TR>
								<TD><SPAN CLASS="browser_icon safari"></TD>
								<TD><A HREF="http://www.apple.com/safari/">Apple Safari</A></TD>
							</TR>
							<TR>
								<TD><SPAN CLASS="browser_icon ie"></TD>
								<TD><A HREF="http://www.microsoft.com/windows/internet-explorer/default.aspx">Microsoft Internet Explorer 8</A></TD>
							</TR>
						</TABLE>
					</DIV>
				</DIV>
			</DIV>
			<DIV CLASS="round" ID='nav_bg'>
				<DIV CLASS="TL"></DIV>
				<DIV CLASS="T"></DIV>
				<DIV CLASS="TR"></DIV>
				<DIV CLASS="L"></DIV>
				<DIV CLASS="M"></DIV>
				<DIV CLASS="R"></DIV>
				<DIV CLASS="BL"></DIV>
				<DIV CLASS="B"></DIV>
				<DIV CLASS="BR"></DIV>

			</DIV>
			<DIV ID="selector" CLASS='selector'>
				<DIV CLASS="L"></DIV>
				<DIV CLASS="M"></DIV>
				<DIV CLASS="R"></DIV>
			</DIV>
			<DIV ID="nav">

				<DIV ID='navwrapper'>
					<UL ID="navigation"></UL>
					<SPAN CLASS='up'></SPAN>
					<SPAN CLASS='down'></SPAN>
				</DIV>

			</DIV>
		</DIV>
		<DIV CLASS="container_12">

			<DIV CLASS="grid_12 footer"><SPAN CLASS=logo></SPAN><SPAN CLASS='holistech'>Holistic Technology Services, LLC</SPAN></DIV>
			<DIV CLASS="clear"></DIV>

		</DIV>

		<DIV STYLE="display: none;" ID=HIDDEN></DIV>
		<DIV ID=background></DIV>

		<!--DIV STYLE="width: 1px; height: 1000px; background: #0FF; position: fixed; left: 320px; top: 0px; z-index: 1000"></DIV>
		<DIV STYLE="width: 1px; height: 1000px; background: #0FF; position: fixed; left: 1280px; top: 0px; z-index: 1000"></DIV-->

	</BODY>
</HTML>

<SCRIPT>
var pager;
document.getElementById('browser').style.display = 'block';
$J('#browser').hide();
$J(document).ready(function() {
	pager = new Pager();
	language = new Language();
	pager.resize();
	flash = [];
<?
	$unpaged = $this->session->flashdata('unpaged');

	if ( $unpaged !== FALSE )
		echo "pager.unpaged_show('{$unpaged}');";
	else
		echo "pager.unpaged_show(); \$J('#login').show();";
?>
	load('/users/check_auth','#HIDDEN');
});

/*
 * Tabbing messes up the jQuery Scrollables when the browser has to move elements to give focus.
 * This easy fix prevents tabs entirely on the page.
 *
 * TODO:  Replace this fix with one that will keep tabbing functionality on forms.
 */
function catchKeys(e)
{
	if (!e) e = window.event;
	
	// Allow tab only in INPUT and SELECT
	if ( e.keyCode==9 )
	{
		if ( typeof(e.srcElement) != 'undefined' ) {
			if ( e.srcElement.tagName.toUpperCase() == 'INPUT' 
			||   e.srcElement.tagName.toUpperCase() == 'SELECT' 
			||   e.srcElement.tagName.toUpperCase() == 'TEXTAREA' )
				return true;
		}

		if ( e.target.nodeName.toUpperCase() == 'INPUT'
		|| e.target.nodeName.toUpperCase() == 'SELECT' 
		|| e.target.nodeName.toUpperCase() == 'TEXTAREA' )
			return true;

		e.cancelBubble = true;
		if ( e.stopPropagation ) e.stopPropagation();
		if ( e.preventDefault ) e.preventDefault();
	}
	
	// Allow backspace only in INPUT and TEXTAREA
	if ( e.keyCode==8 )
	{
		if ( typeof(e.srcElement) != 'undefined' ) {
			if ( e.srcElement.tagName.toUpperCase() == 'INPUT' 
			||   e.srcElement.tagName.toUpperCase() == 'SELECT' 
			||   e.srcElement.tagName.toUpperCase() == 'TEXTAREA' )
				return true;
		}

		if ( e.target.nodeName.toUpperCase() == 'INPUT'
		|| e.target.nodeName.toUpperCase() == 'SELECT' 
		|| e.target.nodeName.toUpperCase() == 'TEXTAREA' )
			return true;

		e.cancelBubble = true;
		if ( e.stopPropagation ) e.stopPropagation();
		if ( e.preventDefault ) e.preventDefault();
		return false;
	}
}
</script>
	
