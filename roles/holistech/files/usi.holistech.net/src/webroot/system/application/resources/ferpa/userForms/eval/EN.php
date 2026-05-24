<P>Please give your overall impression of the course.<BR>
Bad <?=survey_range('overall',1,5)?> Good</P>

<P>Rate how easy it was to locate this training (training site)<BR>
Difficult <?=survey_range('easy_to_locate',1,5)?> Easy</P>

<P>Rate how easy it was sign up for/pay for the course<BR>
Difficult <?=survey_range('easy_to_register',1,5)?> Easy</P>

<P>Rate how easy it was to proceed through the course / user-friendliness<BR>
Difficult <?=survey_range('easy_to_proceed',1,5)?> Easy</P>

<P>Rate value for money of the course (content vs. cost)<BR>
Bad <?=survey_range('value_for_money',1,5)?> Good</P>

<P>Rate the value of the training course to helping you build your resume.<BR>
Bad <?=survey_range('value_for_resume',1,5)?> Good</P>

<P>Please write anything else you would like to share about your experience with this course.<BR>
<?=form_textarea(array(
	'name' => 'other',
	'value' => set_value('other'),
	'cols' => 60,
	'rows' => 5))?></P>
