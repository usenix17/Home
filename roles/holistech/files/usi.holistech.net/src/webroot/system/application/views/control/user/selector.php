<? $uniq = uniqid(); ?>
<DIV ID="<?=$uniq?>" WIDTH="100%">
	<FORM ONSUBMIT="return false;">
	<INPUT ID="control_user-search_query-<?=$uniq?>" NAME='search' STYLE="width: 690px;" 
	VALUE="Search for Names, Usernames, E-Mail Addresses, or Phone Numbers">
	<DIV ID="control_user-search_autocomplete-<?=$uniq?>" CLASS='autocomplete' STYLE="z-index:50;" />
	<SPAN STYLE="float: right">
	<INPUT TYPE=BUTTON ONCLICK="control_user_new()" VALUE="Create New User">
	</SPAN>
	</FORM>
</DIV>
<BR>

<SCRIPT>
	$J('#control_user-search_query-<?=$uniq?>').click( function () {
		$J('#control_user-search_query-<?=$uniq?>').val('');
	});
	new Ajax.Autocompleter('control_user-search_query-<?=$uniq?>','control_user-search_autocomplete-<?=$uniq?>', BASEURL+'/control_user/search', {
		afterUpdateElement: function(input,li) {
			load( '/control_user/<?=$action?>/user_id/'+$J(li).attr('name'), '<?=$target?>' );
	}});
</SCRIPT>
