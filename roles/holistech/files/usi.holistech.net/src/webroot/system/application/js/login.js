function login()
{
}

login.show_enter_code = function()
{
	$J('#enter_code').show();
	$J('#pre_code').hide();
	$J('#purchase').hide();
}

login.hide_enter_code = function()
{
	$J('#enter_code').hide();
	$J('#pre_code').show();
	$J('#purchase').show();
	$J("#register_form INPUT[name=code]").val('');
}

login.do_login = function()
{
	data = $J('#login_form').serialize();
	clear_errors();
	load('/users/do_login',"#HIDDEN",data+"&lang="+$J('#language_toggle').val()+"&width="+screen.width+"&height="+screen.height,login.show_button);
	login.hide_button();
}

login.purchase = function()
{
	clear_errors();
	load('/purchase/start_purchase',"#HIDDEN",undefined,login.show_register_button);
}

login.enter_code = function()
{
	data = $J('#enter_code INPUT[name=code]').serialize();
	clear_errors();
	load('/purchase/enter_code',"#HIDDEN",data,login.show_register_button);
	login.hide_register_button();
}

login.register = function()
{
	//console.log('register');
}

login.emailCert = function()
{
	//console.log('emailCert');

}

login.logout = function()
{
	clear_errors();
	pager.unpaged_show('/users/logout');
}

login.show_button = function()
{
	$J('#users_login-button').show();
	$J('#users_login-throbber').hide();
}

login.hide_button = function()
{
	$J('#users_login-button').hide();
	$J('#users_login-throbber').show();
}
	
login.show_register_button = function()
{
	$J('#users_login-register_button').show();
	$J('#users_login-register_throbber').hide();
}

login.hide_register_button = function()
{
	$J('#users_login-register_button').hide();
	$J('#users_login-register_throbber').show();
}
	
login.show_login_form = function()
{
    $J('#login_form').show();
    $J('#non_cas_login_link').hide();
}

