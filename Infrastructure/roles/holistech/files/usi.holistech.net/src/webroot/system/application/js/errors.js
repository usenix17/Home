function process_error_headers(xhr)
{
	if ( xhr.getResponseHeader('clear-errors') == 'true' )
		clear_errors();
	
	if ( xhr.getResponseHeader('update-utility') == 'true' )
		pager.update_utility();
	
	header = xhr.getResponseHeader('errors-warnings');

	if ( header == '' || header == undefined )
		return;

	errors = eval('('+header+')');
	
	// Log all debug to the console
	if ( typeof(console) !== 'undefined' )
	$J.each(errors.debug, function (i,error) 
	{
		console.log(error.msg)
	});

	//ids = [];

	error_count = 0;
	// Add new errors and messages to the box
	$J.each(errors.errors, function (i,error)
	{
		error_count++;
		append_error(error,'error');
		//ids.push(error.id);
	});
	$J.each(errors.messages, function (i,error)
	{
		error_count++;
		append_error(error,'message');
		//ids.push(error.id);

	});

	// Remove errors and messages that are no longer on the server
	//$J('#error LI').each(function ()
	//{
	//	test_id = $J(this).attr('id');

	//	found = false;
	//	$J.each(ids, function(i,id)
	//	{
	//		if ( test_id == id )
	//			found = true;
	//	});

	//	if ( !found )
	//	{
	//		console.log('Gargbage collecting error '+test_id,ids);
	//		clear_error(test_id);
	//	}
	//});

	if ( error_count > 0 ) {
		set_error_color();
		language.refresh();
	}
}

function append_error(error,level)
{
	id = error.id;
	msg = error.msg;

	if ( id == undefined )
		return;

	// Find out if this is a unique error
	unique = true;
	$J('#error LI').each(function () {
		if ( 	$J(this).attr('id') == id
			|| $J('SPAN.msg', this).html() == msg )
			unique = false;
	});

	if ( ! unique )
		return;

	time = new Date;
	li = $J('<LI><SPAN CLASS=close/><SPAN CLASS=msg>'+msg+'</SPAN></LI>')
		.attr('level',level)
		.attr('time',time.getTime())
		.attr('id',id)

	$J('SPAN.close',li).click(function () {
		clear_error($J(this).parent().attr('id'));
		set_error_color();
	});
	
	li.appendTo('#error UL');
}

function set_error_color()
{
	errors = 0;
	messages = 0;

	$J('#error LI').each(function ()
	{
		li = $J(this);
		time = new Date;
		time = time.getTime();

		if ( li.attr('level') == 'message' )
			messages++;
		else
			errors++;
	});

	if ( errors > 0 )
	{
		pager.error_color('red');
	}
	else if ( messages > 0 )
	{
		pager.error_color('yellow');
	}

	pager.show_errors();
}

function clear_errors()
{
	//console.log('clear_errors()');
	error_count = 0;
	$J('#error LI').each(function()
	{
		error_count++;
		clear_error($J(this).attr('id'));
	});
	
	if ( error_count > 0 )
		pager.show_errors();
}

function clear_error(id)
{
	//console.log('clear_error('+id+')');
	//load('/error/clear/'+id,'#HIDDEN');
	$J('#'+id).remove();
}
