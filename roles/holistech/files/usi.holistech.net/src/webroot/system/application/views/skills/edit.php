<? $this->load->helper('form'); ?>
<FORM ID='skills_edit_<?=$module_id?>_<?=$page?>' ONSUBMIT="return false;">
<?=form_hidden('module_id',$module_id);?>
<?=form_hidden('page',$page);?>

<DIV ID="skills_edit_title" CLASS=gridDiv>
<H1>Title</H1>
<TABLE WIDTH="100%" CLASS=ticTacToe STYLE="margin-bottom: 0;">
	<? foreach ( $GLOBALS['course']->getLanguages() as $lang ): ?>
	<TR>
		<TD WIDTH="0%"><?=$GLOBALS['iso639'][$lang]?>:</TD>
		<TD WIDTH="100%">
			<?=form_input("title[$lang]",
			isset($skill->pages[$page]['title'][$lang]) ? $skill->pages[$page]['title'][$lang] : '',
			'STYLE="width: 100%;"')?>
		</TD>
	</TR>
	<? endforeach; ?>
</TABLE>
</DIV>

<DIV ID="skills_edit_content" CLASS=gridDiv>
<H1>Content</H1>
<TABLE WIDTH="100%" CLASS=ticTacToe STYLE="margin-bottom: 0;">
	<? foreach ( $GLOBALS['course']->getLanguages() as $lang ): ?>
	<TR>
		<TD WIDTH="0%"><?=$GLOBALS['iso639'][$lang]?>:</TD>
		<TD WIDTH="100%"><TEXTAREA ROWS=40 NAME="content1[<?=$lang?>]" STYLE="width: 100%; font-size: 14px;"><?=isset($skill->pages[$page]['content1'][$lang]) ? $skill->pages[$page]['content1'][$lang] : ''?></TEXTAREA></TD>
	</TR>
	<? endforeach; ?>
</TABLE>
</DIV>

<DIV ID="skills_edit_buttons" CLASS=gridDiv>
<P ALIGN=RIGHT>
	<INPUT TYPE=BUTTON VALUE="Save" ONCLICK="javascript:edit.skill_save('<?=$module_id?>',<?=$page?>);">
	<INPUT TYPE=BUTTON VALUE="Cancel" ONCLICK="javascript:edit.skill_cancel('<?=$module_id?>',<?=$page?>);">
</FORM>
</DIV>

<SCRIPT>
	grid = new Grid('#unpaged',12,15,0);
	//grid.showGrid();
	grid.div('skills_edit_title',1,1,12,3,0);
	grid.div('skills_edit_content',1,4,12,14,0);
	grid.div('skills_edit_buttons',1,15,12,15,0);

    // Enable ctrl-enter and esc. on textareas to save
    $J('#skills_edit_content TEXTAREA, #skills_edit_title INPUT').keydown(function (e) {

      if (e.ctrlKey && e.keyCode == 13) {
          // Ctrl-Enter pressed
          edit.skill_save('<?=$module_id?>',<?=$page?>);
      }
      if (e.keyCode == 27) {
          // Esc. pressed
          edit.skill_cancel('<?=$module_id?>',<?=$page?>);
      }
    });

    $J('#skills_edit_content TEXTAREA').eq(0).focus();
    
</SCRIPT>
