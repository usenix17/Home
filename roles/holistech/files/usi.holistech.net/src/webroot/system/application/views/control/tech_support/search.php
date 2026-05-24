<DIV ID="tech_support_search" WIDTH="100%" CLASS="control_frame_div">
	<FORM ONSUBMIT="tech_support.search(); return false;">
	Search for Names, Usernames, E-Mail Addresses, Phone Numbers, or Order Numbers:</BR>
	<INPUT ID="tech_support_search_query" NAME='search' STYLE="width: 690px;" DEFAULT="TRUE" VALUE=''>
	<SPAN STYLE="float: right">
	<SPAN STYLE="display: none;" ID="tech_support_search_throbber"><IMG SRC="<?=base_url();?>../images/ajax-loader.gif"></SPAN>
	<INPUT TYPE=BUTTON ONCLICK="tech_support.search()" VALUE="Search">
	</SPAN>
	</FORM>
</DIV>

<DIV ID="tech_support_search_results" WIDTH="100%"></DIV>

<SCRIPT>
	$J('#tech_support_search_query').click(function () {
		if ( $J(this).attr('default') == 'TRUE' )
			$J(this).val('').attr('default','FALSE');
		$J(this).select();
	});
</SCRIPT>
