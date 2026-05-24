<? 
	$this->load->helper('form');
	$this->load->plugin('multilanginput');
?>
<TABLE ID="tests_edit_language_dropdown" STYLE="display:none">
<TR>
	<TD></TD>
	<TD COLSPAN=2><?=form_dropdown('languages[]',array_merge(array(''=>''),$GLOBALS['iso639']),'','CLASS=language_dropdown');?></TD>
</TR>
</TABLE>
<FORM ID='tests_edit_<?=$module_id?>' ONSUBMIT="return false;">
<?=form_hidden('module_id',$module_id);?>
<DIV ID="tests_edit_top_buttons">
<TABLE CLASS=horizontal_buttons><TR>
	<TD ARROWSTOP="0" TAB="test_name">Test Name and Instructions</TD>
	<TD ARROWSTOP="1" TAB="custom_response">Custom User Response Language</TD>
	<TD ARROWSTOP="2" TAB="edit_questions">Edit Questions</TD>
	<TD ARROWSTOP="3" TAB="manage_questions" ONCLICK="edit.test_generate_question_list('EN')">Rearrange Questions</TD>
</TR></TABLE>
<SPAN CLASS=arrowup>
</DIV>
<DIV ID="tests_edit_test_name" CLASS='test_edit_tab_content'>
	<H2>Title</H2>
	<?=multi_lang_input('title',$test->title,$test->languages);?>
	<H2 STYLE="margin-top: 1em;">Instructions</H2>
	<?=multi_lang_textarea('instructions',$test->instructions,'ROWS=5',$test->languages);?>
</DIV>
<DIV ID="tests_edit_test_settings" CLASS='test_edit_tab_content'>
	<H2>Test Settings</H2>
	<TABLE CLASS='formtable ticTacToe'>
		<TR>
			<TH ALIGN=RIGHT>E-Mail Results To:</TH>
			<TD><?=form_input('email',$test->email);?></TD>
		</TR>
		<TR>
			<TH ALIGN=RIGHT>"Next" Button Action:</TH>
			<TD><?=form_input('nextButton',$test->nextButton);?></TD>
		</TR>
		<TR>
			<TH ALIGN=RIGHT>Randomize:</TH>
			<TD><?=form_checkbox('randomize','1',$test->randomize);?></TD>
		</TR>
		<TR>
			<TH ALIGN=RIGHT>Number of<BR>Questions to Show:</TH>
			<TD><?=form_input('numQuestions',$test->numQuestions);?></TD>
		</TR>
		<TR>
			<TH ALIGN=RIGHT>Use Course<BR>Minimum Score:</TH>
			<TD>
				<?=form_hidden('customMinScore','1');?>
				<?=form_checkbox('customMinScore','0',!$test->customMinScore);?>
			</TD>
		</TR>
		<TR ID="minimum_score_row">
			<TH ALIGN=RIGHT>Minimum Score:</TH>
			<TD><?=form_input('minScore',$test->minScore);?></TD>
		</TR>
			<SCRIPT>
				$J('#tests_edit_test_settings INPUT[name=customMinScore]').change(function () {
					if ( $J(this).attr('checked') )
						$J('#minimum_score_row').hide();
					else
						$J('#minimum_score_row').show();
				}).change();
			</SCRIPT>
		<TR>
			<TH ALIGN=RIGHT>Maximum Attempts:</TH>
			<TD><?=form_input('maxAttempts',$test->maxAttempts);?></TD>
		</TR>
		<TR>
			<TH ALIGN=RIGHT>Attempts Until<BR>Hints Shown:</TH>
			<TD><?=form_input('showHints',$test->showHints);?></TD>
		</TR>
		<TR>
			<TH ALIGN=RIGHT>Suppress<BR>Detailed Results:</TH>
			<TD><?=form_checkbox('suppressResults','1',$test->suppressResults);?></TD>
		</TR>
		<TR>
			<TH ALIGN=RIGHT>Suppress Score:</TH>
			<TD><?=form_checkbox('suppressScore','1',$test->suppressScore);?></TD>
		</TR>
	</TABLE>
</DIV>
<DIV ID="tests_edit_test_custom_response" CLASS='test_edit_tab_content'>
	<H2>This test has been graded:</H2>
	<?=multi_lang_textarea('graded',$test->graded,'ROWS=3',$test->languages);?>
	<H2>You have passed this test:</H2>
	<?=multi_lang_textarea('passed',$test->passed,'ROWS=3',$test->languages);?>
	<H2>You have failed this test:</H2>
	<?=multi_lang_textarea('failed',$test->failed,'ROWS=3',$test->languages);?>
	<H2>This question is correct:</H2>
	<?=multi_lang_textarea('correct',$test->correct,'ROWS=3',$test->languages);?>
	<H2>This question in incorrect:</H2>
	<?=multi_lang_textarea('tryAgain',$test->tryAgain,'ROWS=3',$test->languages);?>
</DIV>

<DIV ID="tests_edit_test_edit_questions" CLASS='test_edit_tab_content'>
	<TABLE CLASS='question_template' STYLE="display: none;">
		<TR CLASS=question_header>
			<TD COLSPAN=2>
				<H2 STYLE="margin-bottom: 5px" CLASS=question_number></H2>
				<P STYLE="margin-bottom: 0">
					Question Type:
					<SELECT NAME="template[type]" CLASS=type>
						<OPTION VALUE='single' SELECTED>Single Answer</OPTION>
						<OPTION VALUE='multiple'>Multiple Answer</OPTION>
						<OPTION VALUE='short'>Short Answer</OPTION>
					</SELECT> | 
					<LABEL FOR="template[randomize]" CLASS=randomize>Randomize:</LABEL>
					
					<INPUT TYPE=CHECKBOX CLASS=randomize VALUE=1 
						NAME="template[randomize]"
						ID="template[randomize]"> |

					<A HREF="javascript:void(0)" CLASS=remove_question_button>Remove Question</A>
				</P>
			</TD>
		</TR>
		<TR CLASS=question_body>
			<TH CLASS=question_number></TH>
			<TD STYLE="padding: 0; width: 100%;">
				<TABLE WIDTH="100%" CLASS='ticTacToe allLanguages' STYLE="margin-bottom: 0;">
					<? foreach ( $test->languages as $lang ): ?>
					<TR CLASS=input LANG=<?=$lang?>>
						<TD><?=$GLOBALS['iso639'][$lang]?></TD>
						<TD STYLE="padding: 0; width: 100%;">
							<TABLE CLASS="ticTacToe question" WIDTH="100%" STYLE="margin: 0;" LANG=<?=$lang?>>
								<TR><TD COLSPAN=3>
									<TEXTAREA ROWS=3 STYLE="width:100%" CLASS=question NAME="template[question][<?=$lang?>]"></TEXTAREA><BR>

									<TABLE WIDTH="100%" STYLE="margin-bottom: 0;" CLASS='hint'>
										<TR>
											<TD STYLE="vertical-align: middle; padding-right: 10px;">Hint:</TD>
											<TD STYLE="padding: 0; width: 100%;"><TEXTAREA ROWS=3 CLASS=hint STYLE="width:100%" NAME="template[hint][<?=$lang?>]"><?=isset($q->hint[$lang]) ? $q->hint[$lang] : ''?></TEXTAREA></TD>
										</TR>
									</TABLE>
								</TD></TR>
								<TR LANG=<?=$lang?>><TD COLSPAN=3 WIDTH="100%">
									<SPAN CLASS="circlebutton plus add_option_button" STYLE="display: inline-table; position: relative; top: 8px; margin-top: -8px; margin-bottom: 0;" />
									<A HREF="javascript:void(0)" CLASS=add_option_button>Add Option</A>
									<? foreach ( $test->languages as $copy_lang ): ?>
										<? if ( $lang == $copy_lang ) continue; ?>
										| <A HREF="javascript:edit.test_copy_from($J(this).attr('question'),'<?=$copy_lang?>','<?=$lang?>');">Copy from <?=$GLOBALS['iso639'][$copy_lang]?></A>
									<? endforeach; ?>
										| <A HREF="javascript:edit.test_clear_question($J(this).attr('question'),'<?=$lang?>');">Clear</A>
								</TD></TR>
							</TABLE>
						</TD>
					</TR>
					<? endforeach; ?>
				</TABLE>
			</TD>
		</TR>
	</TABLE>
	<TABLE CLASS="option_template" STYLE="display: none;">
		<TR CLASS=option><TD STYLE="white-space: nowrap;">
			<INPUT TYPE=HIDDEN CLASS=hidden_answer VALUE=0>
			<INPUT TYPE=CHECKBOX CLASS=answer VALUE=1>
			<LABEL></LABEL>
		</TD><TD WIDTH="100%">
			<TEXTAREA ROWS=4 STYLE="width:100%" CLASS=text></TEXTAREA><BR>
			<TABLE WIDTH="100%" STYLE="margin-bottom: 0;" CLASS='hint'>
				<TR>
					<TD STYLE="vertical-align: middle; padding-right: 10px;">Hint:</TD>
					<TD STYLE="padding: 0; width: 100%;"><TEXTAREA ROWS=3 STYLE="width:100%" CLASS=hint></TEXTAREA></TD>
				</TR>
			</TABLE>
		<TD CLASS=move_buttons>
			<SPAN CLASS="circlebutton up" />
			<SPAN CLASS="circlebutton minus" />
			<SPAN CLASS="circlebutton down" STYLE="margin-bottom: 0px;" />
		</TD></TR>
	</TABLE>
	<? $n=0; ?>
	<TABLE WIDTH="99%" CLASS="ticTacToe allQuestions">
		<? if ( is_array($test->questions) ) foreach ( $test->questions as $qid => $q ): ?>
		<TR QUESTION=<?=++$n?> CLASS=question_header>
			<TD COLSPAN=2>
				<H2 STYLE="margin-bottom: 5px" CLASS=question_number>Question <?=$n;?>.</H2>
				<P STYLE="margin-bottom: 0">
					Question Type:
					<SELECT NAME="questions[<?=$n?>][type]" CLASS=type>
						<OPTION VALUE='single' <?=$q->type=='single'?'SELECTED':''?>>Single Answer</OPTION>
						<OPTION VALUE='multiple' <?=$q->type=='multiple'?'SELECTED':''?>>Multiple Answer</OPTION>
						<OPTION VALUE='short' <?=$q->type=='short'?'SELECTED':''?>>Short Answer</OPTION>
					</SELECT> | 
					<LABEL FOR="questions[<?=$n?>][randomize]">Randomize:</LABEL>
					
					<INPUT TYPE=CHECKBOX CLASS=randomize VALUE=1 
						NAME="questions[<?=$n?>][randomize]"
						ID="questions[<?=$n?>][randomize]" 
						<?=$q->randomize?'CHECKED':''?>> |
					<A HREF="javascript:void(0)" CLASS=remove_question_button>Remove Question</A>
				</P>
			</TD>
		</TR>
		<TR QUESTION=<?=$n?> CLASS=question_body>
			<TH CLASS=question_number><?=$n;?></TH>
			<TD STYLE="padding: 0; width: 100%;">
				<TABLE WIDTH="100%" CLASS='ticTacToe allLanguages' STYLE="margin-bottom: 0;">
					<? foreach ( $test->languages as $lang ): ?>
					<TR CLASS=input LANG=<?=$lang?>>
						<TD><?=$GLOBALS['iso639'][$lang]?></TD>
						<TD STYLE="padding: 0; width: 100%;">
							<TABLE CLASS="ticTacToe question" WIDTH="100%" STYLE="margin: 0;" QUESTION=<?=$n?> LANG=<?=$lang?>>
								<TR><TD COLSPAN=3>
									<TEXTAREA ROWS=3 STYLE="width:100%" CLASS=question NAME="questions[<?=$n?>][question][<?=$lang?>]"><?=isset($q->question[$lang]) ? $q->question[$lang] : ''?></TEXTAREA><BR>

									<TABLE WIDTH="100%" STYLE="margin-bottom: 0;" CLASS='hint'>
										<TR>
											<TD STYLE="vertical-align: middle; padding-right: 10px;">Hint:</TD>
											<TD STYLE="padding: 0; width: 100%;"><TEXTAREA ROWS=3 CLASS=hint STYLE="width:100%" NAME="questions[<?=$n?>][hint][<?=$lang?>]"><?=isset($q->hint[$lang]) ? $q->hint[$lang] : ''?></TEXTAREA></TD>
										</TR>
									</TABLE>
								</TD></TR>
								<? $i = 'a' ?>
								<? if ( isset($q->options[$lang]) ) foreach ( $q->options[$lang] as $oid => $o ): ?>
								<TR CLASS=option OPTION=<?=$oid?>><TD STYLE="white-space: nowrap;">
									<INPUT TYPE=HIDDEN CLASS=hidden_answer 
										NAME="questions[<?=$n?>][options][<?=$lang?>][<?=$oid?>][answer]" VALUE=0>
									<INPUT TYPE=CHECKBOX CLASS=answer VALUE=1 
										NAME="questions[<?=$n?>][options][<?=$lang?>][<?=$oid?>][answer]"
										ID="questions[<?=$n?>][options][<?=$lang?>][<?=$oid?>][answer]" 
										<?=$o['answer']?'CHECKED':''?>>
									<LABEL FOR="questions[<?=$n?>][options][<?=$lang?>][<?=$oid?>][answer]"><?=$i++?>.</LABEL>
								</TD><TD WIDTH="100%">
									<TEXTAREA ROWS=4 STYLE="width:100%" CLASS=text NAME="questions[<?=$n?>][options][<?=$lang?>][<?=$oid?>][text]"><?=$o['text']?></TEXTAREA><BR>
									<TABLE WIDTH="100%" STYLE="margin-bottom: 0;" CLASS='hint'>
										<TR>
											<TD STYLE="vertical-align: middle; padding-right: 10px;">Hint:</TD>
											<TD STYLE="padding: 0; width: 100%;"><TEXTAREA ROWS=3 CLASS=hint STYLE="width:100%" NAME="questions[<?=$n?>][options][<?=$lang?>][<?=$oid?>][hint]?>"><?=$o['suggestion']?></TEXTAREA></TD>
										</TR>
									</TABLE>
								</TD>
								<TD CLASS=move_buttons>
									<SPAN CLASS="circlebutton up" />
									<SPAN CLASS="circlebutton minus" />
									<SPAN CLASS="circlebutton down" STYLE="margin-bottom: 0px" />
								</TD></TR>
								<? endforeach; ?>
								<TR LANG=<?=$lang?>><TD COLSPAN=3 WIDTH="100%">
									<SPAN CLASS="circlebutton plus add_option_button" STYLE="display: inline-table; position: relative; top: 8px; margin-top: -8px; margin-bottom: 0;" />
									<A HREF="javascript:void(0)" CLASS=add_option_button>Add Option</A>
									<? foreach ( $test->languages as $copy_lang ): ?>
										<? if ( $lang == $copy_lang ) continue; ?>
										| <A HREF="javascript:edit.test_copy_from('<?=$n?>','<?=$copy_lang?>','<?=$lang?>');">Copy from <?=$GLOBALS['iso639'][$copy_lang]?></A>
									<? endforeach; ?>
										| <A HREF="javascript:edit.test_clear_question('<?=$n?>','<?=$lang?>');">Clear</A>
								</TD></TR>
							</TABLE>
						</TD>
					</TR>
					<? endforeach; ?>
				</TABLE>
			</TD>
		</TR>
		<? endforeach; ?>
	</TABLE>
</DIV>
<DIV ID="tests_edit_attributes">
	<H2>Languages</H2>
	<TABLE CLASS=ticTacToe STYLE="text-align: center; margin-left: auto; margin-right: auto;" ID="tests_edit_language_table">
		<TR>
			<TH>Show</TH>
			<TH>Language</TH>
			<TD><A HREF="javascript:void(0)" CLASS="add_language">Add</A></TD>
		</TR>
	<? foreach ( $test->languages as $lang ): ?>
		<TR>
			<TD>
				<INPUT TYPE=CHECKBOX CLASS=language LANG=<?=$lang?> CHECKED>
				<INPUT TYPE=HIDDEN NAME="languages[]" VALUE="<?=$lang?>">
			</TD>
			<TD><?=$GLOBALS['iso639'][$lang]?></TD>
			<TD><A HREF="javascript:void(0)" CLASS="remove_language">Remove</A></TD>
		</TR>
	<? endforeach; ?>
	</TABLE>
	<DIV ID="tests_edit_lang_save_warning" STYLE="text-align: center; margin-bottom: 1em; font-family: serif; margin-top: -1em; display: none;">
	Press "Save" to show the new language.
	</DIV>

	<DIV ID="tests_edit_edit_tools" CLASS="test_edit_tab_content">
	<H2>Tools</H2>
	<TABLE STYLE="margin-bottom: 5px;">
		<TR>
			<TH><LABEL FOR="tests_edit_show_hints">Show Hints:</LABEL></TH>
			<TD><INPUT TYPE=CHECKBOX CLASS=show_hints ID=tests_edit_show_hints></TD>
		</TR>
		<TR><TD COLSPAN=2>
			<SPAN CLASS="circlebutton plus add_question_button" STYLE="display: inline-table; position: relative; top: 13px;" />
			<A HREF="javascript:void(0)" CLASS=add_question_button>Add Question</A>
		</TD></TR>
	</TABLE>

	</DIV>

	<DIV ID="tests_edit_test_name_description" CLASS="test_edit_tab_content">
	<DL CLASS="tests_edit_description">
		<DT>Languages</DT>
		<DD>Use the check boxes in the "Show" column to show or hide alternate translations that you don't want to see.  
		You can also add or remove languages.</DD>

		<DT>Title</DT>
		<DD>The title will be displayed in the menu on the left, at the top of the test, and in reports.</DD>

		<DT>Instructions</DT>
		<DD>This text will appear at the top of the test.</DD>

		<DT>E-Mail Results To:</DT>
		<DD>If set, a copy of the results screen shown to the student will be sent to the specified email address.</DD>

		<DT>"Next" Button Action</DT>
		<DD>This refers to the "Next" button at the bottom of the results page after passing a test.  Use this to change what that button does.  Leave blank for the default.</DD>

		<DT>Randomize</DT>
		<DD>Shuffle the questions each time the student views the test.</DD>

		<DT>Number of Questions to Show</DT>
		<DD>If set to 0, the student will see all of the questions, otherwise they will see that number of questions.  
		This is most useful with "Randomize" checked.</DD>

		<DT>Use Course Minimum Score</DT>
		<DD>If not checked, you will be given the option of specifying a minimum score for this test that will override the default passing score for the course.</DD>

		<DT>Maximum Attempts</DT>
		<DD>If set to 0, a student can try as many times as they wish to pass a test.  Otherwise, they will only be able to try the specified number of times.  Once that limit is reached, the student will no longer be able to progress without administrative intervention.</DD>

		<DT>Attempts Until Hints Shown</DT>
		<DD>How many times must a student fail a test until they will be shown hints along with the test questions.</DD>

		<DT>Suppress Detailed Results</DT>
		<DD>This will stop the system from showing the student which questions they answered correctly and incorrectly.</DD>

		<DT>Suppress Score</DT>
		<DD>Do not show the student their score after the test.</DD>
	</DL>
	</DIV>
	<DIV ID="tests_edit_test_custom_response_description" CLASS="test_edit_tab_content">
	<DL CLASS="tests_edit_description">
		<DT>This test has been graded</DT>
		<DD>This language is placed at the top of the results page to acknowledge receipt of the test</DD>

		<DT>You have passed the test</DT>
		<DD>This is the language to alert the student that they have passed the test.  It appears in a box after the detaled results.</DD>

		<DT>You have failed the test</DT>
		<DD>This is the language to alert the student that they have failed the test.  It appears in a box after the detaled results.</DD>

		<DT>This question is correct</DT>
		<DD>This is the language to alert the student that they have answered a question correctly. It appears on each line of the detailed results.</DD>

		<DT>This question is incorrect</DT>
		<DD>This is the language to alert the student that they have answered a question incorrectly.
		It appears on each line of the detailed results, and it will be overridden by each selected option's "Hint" text if set.</DD>

	</DL>
	</DIV>
	<DIV ID="tests_edit_questions_descripton" CLASS="test_edit_tab_content">
	<DL CLASS="tests_edit_description">
		
	</DL>
	</DIV>
	<DIV ID="tests_edit_manage_questions_descripton" CLASS="test_edit_tab_content">
	<DL CLASS="tests_edit_description">
		<DT>Rearrange Quetsions</DT>
		<DD>Drag-and-drop each question to where you would like it to appear in the test.</DD>
	</DL>
	</DIV>
</DIV>

<DIV ID="tests_edit_manage_questions" CLASS='test_edit_tab_content'>
</DIV>

<DIV ID="tests_edit_buttons" CLASS=gridDiv>
<P STYLE="text-align: center; margin: 0">
	<INPUT TYPE=BUTTON VALUE="Save and Close" ONCLICK="javascript:edit.test_save_close('<?=$module_id?>');">
	<INPUT TYPE=BUTTON VALUE="Save" ONCLICK="javascript:edit.test_save('<?=$module_id?>');">
	<INPUT TYPE=BUTTON VALUE="Close" ONCLICK="javascript:edit.test_cancel('<?=$module_id?>');">
</FORM>
</DIV>

<SCRIPT>
	edit.test_init();
</SCRIPT>
