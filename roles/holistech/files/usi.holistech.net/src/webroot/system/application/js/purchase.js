function Purchase()
{
}

Purchase.submit_form = function()
{
	data = $J('#purchase_form').serialize();

	pager.unpaged_show('/purchase/duplicates',data+"&submit=true");
}

Purchase.start_order = function()
{
	pager.unpaged_show('/purchase/show_create_user/',
			'num_codes='+$J('#num_codes').text()+
			'&myself='+($J('#purchase_course_for_me').attr('checked')?'1':'0'));
}

Purchase.enroll = function()
{
	clear_errors();
	if ( $J('INPUT[name=purchase_RADIO_create]:checked').val() == 'yes' ) {
		data = $J('#purchase_create_user_FORM').serialize();
		load('/purchase/create_user','#unpaged',data);
	} else {
		data = $J('#purchase_login_form').serialize();
		load('/purchase/do_login','#HIDDEN',data);
	}
}

function purchase_select_update_total()
{
	evilie_num_codes = parseInt($J('#purchase_course_num_codes').val());
	if ( isNaN(evilie_num_codes) )
		evilie_num_codes = 0;
	if ( evilie_num_codes > 999 )
		evilie_num_codes = 999;

	$J('#purchase_course_num_codes').val(evilie_num_codes==0?'':evilie_num_codes);

	if ( $J('#purchase_course_for_me').attr('checked') )
		evilie_num_codes++;

	total = evilie_num_codes * purchase_price;

	$J('#num_codes').text(evilie_num_codes);
	$J('#total_price').text(total);

	if ( evilie_num_codes == 1 ) {
		$J('#purchase_text .registrations').hide();
		$J('#purchase_text .registration').show();
	}
	else {
		$J('#purchase_text .registrations').show();
		$J('#purchase_text .registration').hide();
	}

	if ( evilie_num_codes > 0 )
		$J('#purchase_select_continue').removeAttr('disabled');
	else
		$J('#purchase_select_continue').attr('disabled','true');
}

