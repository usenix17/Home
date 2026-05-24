<DIV ID="control_codes_create_div" CLASS="control_frame_div">
<TABLE WIDTH="100%" STYLE="margin-bottom: 0px;">
	<TR>
		<TD WIDTH="23%"><B>Create New Codes:</B></TD>
		<TD WIDTH="30%">Quantity: <INPUT ID=control_codes_create_quantity></TD>
		<TD WIDTH="30%">Label: <INPUT ID=control_codes_create_label></TD>
		<TD ALIGN=CENTER><INPUT TYPE=BUTTON ID=control_codes_create_button VALUE=Create></TD>
	</TR>
</TABLE>
</DIV>
<SCRIPT>
$J('#control_codes_create_button').click(function () {
	load('/control_codes/create/'+
		$J('#control_codes_create_quantity').val() + '/' + 
		$J('#control_codes_create_label').val(), '#control_codes_show_codes');
});
</SCRIPT>
