function edit() {}

edit.skill_edit = function (module_id,page)
{
	index = pager.get_index_from_module_id(module_id);
	pager.unpaged_show('/skills/edit/'+module_id+'/'+page,undefined,true);
}
	
edit.skill_save = function (module_id,page)
{
    //console.log('skill_save('+module_id+','+page+')');
	var index = pager.get_index_from_module_id(module_id);
	// We need to escape the colon(s) in module_id
	var data = $J('#skills_edit_'+module_id.replace(/:/g,'\\:')+'_'+page).serialize();
    //console.log('load: /skills/save/'+module_id+'/'+page,'.module[MODULE='+index+'] .page[PAGE='+page+']',data)
	load('/skills/save/'+module_id+'/'+page,'.module[MODULE='+index+'] .page[PAGE='+page+']',data,function () {
		pager.unpaged_hide();
	});
}

edit.skill_cancel = function (module_id,page)
{
	pager.unpaged_hide();
}
	
edit.test_edit = function (module_id,page)
{
	pager.unpaged_show('/tests/edit/'+module_id+'/'+page,undefined,true);
}
	
edit.test_save = function (module_id)
{
	index = pager.get_index_from_module_id(module_id);
	// We need to escape the colon(s) in module_id
	form = $J('#tests_edit_'+module_id.replace(/:/g,'\\:'));
	data = form.serialize();
	fn=null;

	if ( $J('SELECT.language_dropdown',form).length > 0 )
	{
		fn = function () {
			edit.test_edit(module_id);
		}
	}
	load('/tests/save/'+module_id+'/'+page,'.module[MODULE='+index+'] .page[PAGE=0]',data,fn);
}

edit.test_save_close = function (module_id)
{
	index = pager.get_index_from_module_id(module_id);
	// We need to escape the colon(s) in module_id
	data = $J('#tests_edit_'+module_id.replace(/:/g,'\\:')).serialize();
	load('/tests/save/'+module_id+'/'+page,'.module[MODULE='+index+'] .page[PAGE=0]',data,function () {
		pager.unpaged_hide();
	});
}

edit.test_cancel = function (module_id)
{
	pager.unpaged_hide();
}

edit.test_add_question = function ()
{
	table = $J('#tests_edit_test_edit_questions TABLE.allQuestions');

	var question_number = 0;
	$J('TR.question_header',table).each(function () { 
		question = parseInt($J(this).attr('question'));
		question_number =  question > question_number ? question : question_number;
	}); 
	question_number++;
	prefix = 'questions['+question_number+"]";
	
	header_template = $J('#tests_edit_test_edit_questions .question_template TR.question_header').clone();
	body_template = $J('#tests_edit_test_edit_questions .question_template TR.question_body').clone();

	header_template.attr('QUESTION',question_number);
	$J('SELECT.type',header_template).attr('NAME',prefix+'[type]');
	$J('LABEL.randomize',header_template).attr('FOR',prefix+'[randomize]');
	$J('INPUT.randomize',header_template).attr('NAME',prefix+'[randomize]').attr('ID',prefix+'[randomize]');
	
	body_template.attr('QUESTION',question_number);
	edit.test_question_logic(header_template,body_template);
	$J('TABLE.question', body_template).each(function () {
		lang = $J(this).attr('lang');
		$J(this).attr('QUESTION',question_number);
		$J('A', this).attr('QUESTION',question_number);
		$J('TEXTAREA.question',$J(this)).attr('NAME',prefix+'[question]['+lang+']');
		$J('TEXTAREA.hint',$J(this)).attr('NAME',prefix+'[hint]['+lang+']');
	});

	table.append(header_template);
	table.append(body_template);

	edit.test_redo_numbers();

	div = $J('#tests_edit_test_edit_questions')[0];
	div.scrollTop=div.scrollHeight;

	$J('TEXTAREA.question:first',body_template).focus();
}		

edit.test_add_option = function (question,nologic)
{
	question_number = question.attr('question');
	var option_number = 0;
	$J('TR.option',question).each(function () { 
		option = parseInt($J(this).attr('option'));
		option_number =  option > option_number ? option : option_number;
	}); 
	option_number++;
	lang = question.attr('lang');
	prefix = 'questions['+question_number+"][options]["+lang+"]["+option_number+"]";
	
	template = $J('#tests_edit_test_edit_questions .option_template TR.option').clone();
	template.attr('option',option_number);
	$J('INPUT.hidden_answer',template).attr('name',prefix+'[answer]');
	$J('INPUT.answer',template).attr('name',prefix+'[answer]').attr('id',prefix+'[answer]');
	$J('LABEL',template).attr('for',prefix+'[answer]').html(String.fromCharCode(97+option_number)+'.');
	$J('TEXTAREA.text',template).attr('name',prefix+'[text]');
	$J('TEXTAREA.hint',template).attr('name',prefix+'[hint]');

	$J('TR:last',question).before(template);
	question_body = question.closest('TR.question_body');
	
	if ( nologic !== true )
		edit.test_question_logic(question_body.prev(),question_body);

	edit.test_redo_letters(question);

	$J('TEXTAREA',template).focus();
}		

edit.test_init = function ()
{
	grid = new Grid('#unpaged',15,15,10);
	//grid.showGrid();
	grid.div('tests_edit_top_buttons',1,1,12,1,0).scroll(false);

	grid.div('tests_edit_test_name',1,2,8,15,0);
	grid.div('tests_edit_test_settings',9,2,12,15,0)
	;
	grid.div('tests_edit_test_custom_response',1,2,12,15,0);
	grid.div('tests_edit_test_edit_questions',1,2,12,15,0);
	grid.div('tests_edit_manage_questions',1,2,12,15,0);

	grid.div('tests_edit_attributes',13,1,15,14,0);

	grid.div('tests_edit_buttons',13,15,15,15,0).scroll(false);

	$J("#tests_edit_test_edit_questions INPUT.show_options").change(function () {
		if ( $J(this).attr('checked') ) {
			$J('#unpaged TR.option').show();
		} else {
			$J('#unpaged TR.option').hide();
		}
	});
	$J("#tests_edit_show_hints").change(function () {
		if ( $J(this).attr('checked') ) {
			$J('#unpaged TABLE.hint').show();
		} else {
			$J('#unpaged TABLE.hint').hide();
		}
	}).change();

	$J("#tests_edit_attributes INPUT.language").change(function () {
		//langs = 0;
		inputs = $J('#unpaged TR.input')
		inputs.hide();
		$J('> TD',inputs).css('border-bottom-width','1px');
		//$J('#tests_edit_main TR.input TD.label').hide();
		last = null;
		$J("#tests_edit_attributes INPUT.language").each(function () {
			if ( $J(this).attr('checked') ) {
				//langs++;
				//if ( langs > 1 )
				//	$J('#tests_edit_main TR.input TD.label').show();
				last = $J('#unpaged TR.input[LANG='+$J(this).attr('lang')+']').show();
			}
		});
		$J('> TD',last).css('border-bottom-width', '0px');
	});
	$J("#tests_edit_attributes A.add_language").click(function () {
		row = $J('#tests_edit_language_dropdown TR').clone();
		$J('SELECT.language_dropdown',row).change(function () { $J('#tests_edit_lang_save_warning').show() } );
		$J('#tests_edit_language_table').append(row);
	});
	$J("#tests_edit_attributes A.remove_language").click(function () {
		row = $J(this).closest('TR');
		$J('INPUT.language',row).removeAttr('checked').change();
		row.remove();
	});

	up = $J("#tests_edit_top_buttons SPAN.arrowup");
	table = $J("#tests_edit_top_buttons TABLE");
	up.css('top',table.height());
	up.css('left',$J('TD:first-child',table).width() * 0.5 - up.width()/2);
	$J("#tests_edit_top_buttons TD").click(function () {
		up = $J("#tests_edit_top_buttons SPAN.arrowup");
		up.animate({
			left: $J('TD:first-child',table).width() * (parseInt($J(this).attr('arrowstop'))+.5) - up.width()/2,
		},300);
		
		$J("DIV.test_edit_tab_content").hide();
		switch ( $J(this).attr('TAB') )
		{
			case 'test_name':
				$J('#tests_edit_test_name').show();
				$J('#tests_edit_test_name_description').show();
				$J('#tests_edit_test_settings').show();
			break;

			case 'custom_response':
				$J('#tests_edit_test_custom_response').show();
				$J('#tests_edit_test_custom_response_description').show();
			break;

			case 'edit_questions':
				$J('#tests_edit_test_edit_questions').show();
				$J('#tests_edit_edit_tools').show();
				$J('#tests_edit_questions_descripton').show();
			break;

			case 'manage_questions':
				$J('#tests_edit_manage_questions').show();
				$J('#tests_edit_manage_questions_descripton').show();
			break;
		}
	});
	$J("#tests_edit_top_buttons TD:first").click();

	$J("#tests_edit_edit_tools .add_question_button").unbind('click').click(function () {
		edit.test_add_question();
	});

	$J("#tests_edit_test_edit_questions TR.question_header").each(function () {
		edit.test_question_logic($J(this),$J(this).next());
	});
}
	
edit.test_redo_numbers = function ()
{
	var i=1;	
	$J('#tests_edit_test_edit_questions TABLE.allQuestions TR.question_header .question_number').each(function () {
		$J(this).html("Question "+i+'.');
		i++;
	});
	i=1;	
	$J('#tests_edit_test_edit_questions TABLE.allQuestions TR.question_body .question_number').each(function () {
		$J(this).html(i);
		i++;
	});
}

edit.test_redo_letters = function (table)
{
	var i=0;	
	$J('TR.option',table).each(function () {
		$J('LABEL',this).html(String.fromCharCode(97+i)+'.');
		i++;
	});
}

edit.test_question_logic = function (header,body)
{
	$J('SPAN.up', body).click(function () {
		row = $J(this).closest('TR');
		prev = row.prev();
		if ( prev.hasClass('option') )
			prev.before(row);
		edit.test_redo_letters(row.closest('TABLE.question'));
	});
	$J('SPAN.down', body).click(function () {
		row = $J(this).closest('TR');
		next = row.next();
		if ( next.hasClass('option') )
			next.after(row);
		edit.test_redo_letters(row.closest('TABLE.question'));
	});
	$J('SPAN.minus', body).click(function () {
		table = $J(this).closest('TABLE.question');
		$J(this).closest('TR').remove();
		edit.test_redo_letters(table);
	});
	$J('TABLE.question',body).sortable({
		items: "TR.option",
		stop: function () { edit.test_redo_letters(table); },
	});

	$J(".add_option_button", body).unbind('click').click(function () {
		edit.test_add_option($J(this).closest('TABLE.question'));
	});

	$J(".remove_question_button", header).unbind('click').click(function () {
		header = $J(this).closest('TR.question_header');
		body = header.next();
		header.remove();
		body.remove();
		edit.test_redo_numbers();
	});

	$J("SELECT.type", header).unbind('change').change(function () {
		type = $J(this).val();

		$J('TABLE.question',body).each(function () {
			var question = $J(this);
			$J('TR.option',question).show();
			$J('TR.option TD',question).show();
			$J("SPAN.add_option_button",question).css('display','inline-table');
			$J("A.add_option_button",question).show();
			$J("INPUT.answer",question).removeAttr('disabled');

			if ( type == 'single' ) {
				// Deselect everything but the first checked answer
				// and make them behave like radio buttons
				answer = false;
				checked = $J('INPUT.answer:checked:first',question);
				$J('INPUT.answer',question).removeAttr('checked');
				checked.attr('checked',true);
				$J('INPUT.answer',question).unbind().bind('click',function () {
					checked = $J(this).attr('checked');
					if ( checked ) {
						question = $J(this).closest('.question');
						$J('INPUT.answer',question).removeAttr('checked');
						$J(this).attr('checked',checked);
					}
				});
			}
			else if ( type == 'multiple' ) {
				// Deselect everything but the first checked answer
				// and make them behave like radio buttons
				$J('INPUT.answer',question).unbind();
			}
			else if ( type == 'short' ) {
				// Deselect everything but the first checked answer
				// and make them behave like radio buttons
				if ( $J('TR.option[OPTION=0]',question).length == 0 )
					edit.test_add_option(question,true);
				$J('INPUT.answer',question).unbind();
				$J('INPUT.answer',question).removeAttr('checked');
				$J('INPUT.answer:first',question).attr('checked',true).attr('disabled',true);
				$J('TR.option',question).hide();
				$J(".add_option_button",question).hide();
				$J('TR.option[OPTION=0]',question).show();
				$J('TR.option TD.move_buttons',question).hide();
			}
		});
	}).change();
}

edit.test_copy_from = function (question,from_lang,to_lang)
{
	target_question = $J('TABLE.question[LANG='+to_lang+'][QUESTION='+question+']');
	from_question = $J('TABLE.question[LANG='+from_lang+'][QUESTION='+question+']');

	from_options = $J('TR.option',from_question);

	$J('SPAN.minus',target_question).click();
	add_button = $J('SPAN.plus',target_question);
	end = from_options.length;
	for ( i=0; i<end; i++ ) {
		edit.test_add_option(target_question,true);
	}

	target_options = $J('TR.option',target_question);

	$J('TEXTAREA.question',target_question).val($J('TEXTAREA.question',from_question).val());
	for ( i=0; i<from_options.length; i++ ) {
		$J('TEXTAREA.text',target_options[i]).val($J('TEXTAREA.text',from_options[i]).val());
		$J('TEXTAREA.hint',target_options[i]).val($J('TEXTAREA.hint',from_options[i]).val());
		$J('INPUT.answer',target_options[i]).attr('checked',$J('INPUT.answer',from_options[i]).attr('checked'));
	}

	body = target_question.closest('TR.question_body');
	edit.test_question_logic(body.prev(),body);
}

edit.test_clear_question = function (question,lang)
{
	target_question = $J('TABLE.question[LANG='+lang+'][QUESTION='+question+']');
	$J('TEXTAREA',target_question).val('');
	$J('INPUT.answer',target_question).removeAttr('checked');
}

edit.test_generate_question_list = function (lang)
{
	ul = $J('<ul class="sortable"></ul>');

	$J('#tests_edit_test_edit_questions TABLE.allQuestions TABLE.question[LANG='+lang+'] TEXTAREA.question').each(function () {
		question = $J(this).closest('TABLE.question').attr('question');
		ul.append('<li class="ui-state-default" QUESTION="'+question+'"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>'+$J(this).val()+'</li>');
	});

	$J("#tests_edit_manage_questions").html(ul);

	ul.sortable({
		stop: function (ev,ui) {
			question = ui.item.attr('question');
			header = $J("#tests_edit_test_edit_questions TR.question_header[QUESTION="+question+"]");
			body = $J("#tests_edit_test_edit_questions TR.question_body[QUESTION="+question+"]");

			next = ui.item.next();

			if ( next.length == 0 ) {
				prev = ui.item.prev();
				real_prev = $J("#tests_edit_test_edit_questions TR.question_body[QUESTION="+prev.attr('question')+"]");
				real_prev.after(body);
				real_prev.after(header);
			} else {
				real_next = $J("#tests_edit_test_edit_questions TR.question_header[QUESTION="+next.attr('question')+"]");
				real_next.before(header);
				real_next.before(body);
			}
			edit.test_redo_numbers();
		},
	}).disableSelection();
}
