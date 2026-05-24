<?
$this->load->helper('form'); 
$uniq = uniqid();
?>
<DIV ID="<?=$uniq?>">
<P>Did the photos/graphics enhance the text materials?  Why or why not?</P>

<?=form_textarea('photos',set_value('photos'))?>

<P>Did the quizzes help prepare you for the final exam?  Why or why not?</P>

<?=form_textarea('quizzes',set_value('quizzes'))?>

<P>Did you use the Spanish Version? If yes, what was your experience with it?</P>

<?=form_textarea('spanish',set_value('spanish'))?>

<P>Please describe any problems you had with the lessons or workbook activities.</P>

<?=form_textarea('problems',set_value('problems'))?>

<P>What parts of the training program did you like least? Why?</P>

<?=form_textarea('least',set_value('least'))?>

<P>What parts of the training program did you like most? Why?</P>

<?=form_textarea('most',set_value('most'))?>

<P>What changes would you recommend to us so we can improve this training?</P>

<?=form_textarea('improve',set_value('improve'))?>

<P>What were the most helpful skills you learned and why?</P>

<?=form_textarea('helpful',set_value('helpful'))?>

<P>How are you accessing the Internet?</P>

<?=form_textarea('internet',set_value('internet'))?>

<P>Where did you hear about this online course?</P>

<?=form_textarea('hear',set_value('hear'))?>

<P>Approximately how long did it take you to complete this course?</P>

<?=form_textarea('time',set_value('time'))?>

<P>What else would you like us to know about your experience with this training program?</P>

<?=form_textarea('experience',set_value('experience'))?>

<P ALIGN=CENTER><INPUT TYPE=button VALUE="Save" ONCLICK="save_form_<?=$uniq?>()"></P>
</DIV>

<SCRIPT>
	function save_form_<?=$uniq?>()
	{
		form_data = $J('#<?=$uniq?> TEXTAREA').serialize();

		load('/userForms/save/<?=$name?>',$J('#<?=$uniq?>').parent(),form_data,function()
			{
				pager.page_scroll_reset();
				pager.page_scroll_set();
			});
	}
</SCRIPT>
