function control_user_save()
{
	clear_errors();
	data = $J('#control_user-edit_form').serialize();
	load('/control_user/save/',$J('#control_user-edit_form').parent(),data);
}

function control_user_save()
{
	clear_errors();
	data = $J('#control_user-edit_form').serialize();
	load('/control_user/save/',$J('#control_user-edit_form').parent(),data);
}

function control_user_reset_password()
{
	clear_errors();
	data = $J('#control_user-edit_form').serialize();
	load('/control_user/do_reset_password/',$J('#control_user-edit_form').parent(),data);
}

function control_user_new()
{
	load('/control_user/create/',$J('#control_user-edit_form').parent());
}

function control_enrollments() {}

control_enrollments.show_details = function (enrollment_id)
{
	clear_errors();
	load('/control_user/show_enrollment_details/'+enrollment_id,'#control_enrollments_details_'+enrollment_id+'_TD');
	$J('#control_enrollments_details_'+enrollment_id+'_TR').css('display','table-row');
	$J('#control_enrollments_details_'+enrollment_id+'_show_button').hide();
	$J('#control_enrollments_details_'+enrollment_id+'_hide_button').show();
}

control_enrollments.hide_details = function (enrollment_id)
{
	clear_errors();
	$J('#control_enrollments_details_'+enrollment_id+'_TR').hide();
	$J('#control_enrollments_details_'+enrollment_id+'_show_button').show();
	$J('#control_enrollments_details_'+enrollment_id+'_hide_button').hide();
}

control_enrollments.toggle_group = function (group)
{
	if ( $J('#control_user-enrollment_group_'+group+'_hide').css('display') == 'none' )
	{
		$J('#control_user-enrollment_group_'+group+'_hide').show();
		$J('#control_user-enrollment_group_'+group+'_show').hide();
		$J('#control_user-enrollment_group_'+group+'').show();
	}
	else
	{
		$J('#control_user-enrollment_group_'+group+'_hide').hide();
		$J('#control_user-enrollment_group_'+group+'_show').show();
		$J('#control_user-enrollment_group_'+group+'').hide();
	}
}

control_enrollments.show_module_details = function (enrollment_id, module_id,target)
{
	clear_errors();
	load('/control_user/show_module_details/'+enrollment_id+'/'+module_id,target);
	$J(target).parent().css('display','table-row');
}

control_enrollments.show_test_results = function (uniq)
{
	clear_errors();
	$J('#'+uniq).css('display','table-row');
}

control_enrollments.enroll = function (user_id,course,link)
{
	clear_errors();
	$J('#'+link).text('Enrolling...');
	load('/control_user/enroll/'+user_id+'/'+course,'#HIDDEN');
}

