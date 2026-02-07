function tech_support() {}

tech_support.search = function (page) {
	clear_errors();
	query = $J('#tech_support_search_query').val();
	if ( typeof(page) == 'undefined' )
		page = 0;

	load('/tech_support/search/'+page,'#tech_support_search_results','query='+query,function () {
		$J('#tech_support_search_throbber').hide();
	},false);
	$J('#tech_support_search_throbber').show();
}

tech_support.show_details = function (user_id)
{
	clear_errors();
	load('/tech_support/show_details/'+user_id,'#tech_support_details_'+user_id+'_TD');
	$J('TR.tech_support_details').hide();
	$J('#tech_support_details_'+user_id+'_TR').css('display','table-row');
	$J('TABLE.tech_support_results SPAN.tech_support_hide_button').hide();
	$J('TABLE.tech_support_results SPAN.tech_support_details_button').show();
	$J('#tech_support_details_'+user_id+'_show_button').hide();
	$J('#tech_support_details_'+user_id+'_hide_button').show();
}

tech_support.hide_details = function (user_id)
{
	clear_errors();
	$J('#tech_support_details_'+user_id+'_TR').hide();
	$J('TABLE.tech_support_results SPAN.tech_support_hide_button').hide();
	$J('TABLE.tech_support_results SPAN.tech_support_details_button').show();
}

tech_support.fulfill = function (purchase_id)
{
	clear_errors();
	load('/tech_support/fulfill/'+purchase_id,'#tech_support_details_'+purchase_id+'_codes_TD');
}

tech_support.reset_password = function (user_id)
{
	clear_errors();
	load('/tech_support/reset_password/'+user_id,'#HIDDEN');
}

tech_support.save_email = function (user_id)
{
	clear_errors();
	target = $J('#tech_support_details_'+user_id);
	data = $J('INPUT',target).serialize();

	load('/tech_support/save_email/'+user_id,'#HIDDEN',data);
}

tech_support.refund = function (user_id)
{
	clear_errors();
	data = $J('#tech_support_details_'+user_id+' INPUT').serialize();
	load('/tech_support/refund_codes/','#tech_support_search_results',data);
}
tech_support.refund_verify = function ()
{
	clear_errors();
	data = $J('#tech_support_refund_verify INPUT').serialize();
	load('/tech_support/refund_address_verify/','#tech_support_search_results',data);
}
tech_support.address_verify = function (destination)
{
	clear_errors();
	data = $J('#tech_support_address_verify INPUT')
		.add('#tech_support_address_verify SELECT')
		.add('#tech_support_address_verify TEXTAREA').serialize();
	load(destination,'#tech_support_search_results',data);
}
tech_support.cancel = function ()
{
	clear_errors();
	$J('#tech_support_search_results').html('');
}

tech_support.email = function (user_id)
{
	clear_errors();
	data = $J('#tech_support_details_'+user_id+' INPUT').serialize();

	load('/tech_support/email_codes/','#tech_support_search_results',data);
}
tech_support.email_all = function (user_id)
{
	clear_errors();
	data = $J('#tech_support_details_'+user_id+' INPUT').serialize();

	load('/tech_support/email_all_codes/','#tech_support_search_results',data);
}
tech_support.certify = function (user_id,enrollment_id)
{
	clear_errors();
	data = $J('#tech_support_details_'+user_id+' INPUT').serialize();

	load('/tech_support/certify/'+enrollment_id,'#tech_support_search_results',data);
}

tech_support.email_certificate = function (user_id,enrollment_id)
{
	clear_errors();
	data = $J('#tech_support_details_'+user_id+' INPUT').serialize();

	load('/tech_support/email_certificate/'+enrollment_id,'#HIDDEN',data);
}
