function control_course_save()
{
	clear_errors();
	data = $J('#control_course-edit_form').serialize();
	load('/control_course/save/',$J('#control_course-edit'),data);
}

function control_course_edit()
{
	clear_errors();
	course = $J('#control_course-selector SELECT[name=coursename]').val();
	load('/control_course/edit/'+course,$J('#control_course-edit'));
}
