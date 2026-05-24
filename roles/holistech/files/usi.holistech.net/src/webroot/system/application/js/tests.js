// Test sumbit button passes itself.
function submit_test(button)
{
	form = $J(button).closest('FORM');
	form_data = form.serialize();
	
	load('/tests/grade/'+pager.get_current_module_id(),pager.dom.current_page,form_data,function ()
			{
				pager.page_scroll_set();
			});
	
	return false;
}

// Practice Question Answer Funcion
function pq_ans(input)
{
	i = $J(input);
	pqans = $J('.pq-ans',$J(input).parent().parent().parent());

	pqans.html($J('#ans_'+i.attr('id')).html());
}

	
