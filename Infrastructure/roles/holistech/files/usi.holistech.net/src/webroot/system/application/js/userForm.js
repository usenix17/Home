function userForm() {}

userForm.save = function (id)
{
	data = $J('#'+id).serialize();
	load('/userForms/save/',pager.dom.current_page,data);
}
