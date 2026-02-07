<?
function multi_lang_input($field,$contents,$languages)
{	
	$out = '<TABLE WIDTH="99%" CLASS=ticTacToe STYLE="margin-bottom: 0;">';

	foreach ( $languages as $lang ) {
		$input = form_input("{$field}[{$lang}]",
			isset($contents[$lang]) ? $contents[$lang] : '',
			'STYLE="width: 100%;"');

		$out .= <<<HTMLFIN
	<TR CLASS=input LANG={$lang}>
		<TD WIDTH="0%" CLASS=label>{$GLOBALS['iso639'][$lang]}:</TD>
		<TD WIDTH="100%">{$input}</TD>
	</TR>
HTMLFIN;
	}

	return $out.'</TABLE>';
}	

function multi_lang_textarea($field,$contents,$data,$languages)
{	
	$out = '<TABLE WIDTH="99%" CLASS=ticTacToe STYLE="margin-bottom: 0;">';

	foreach ( $languages as $lang ) {
		$input = "<TEXTAREA NAME=\"{$field}[{$lang}]\" STYLE=\"width: 100%;\" {$data}>"
			. (isset($contents[$lang]) ? $contents[$lang] : '')
			. '</TEXTAREA>';

		$out .= <<<HTMLFIN
	<TR CLASS=input LANG={$lang}>
		<TD WIDTH="0%" CLASS=label>{$GLOBALS['iso639'][$lang]}:</TD>
		<TD WIDTH="100%">{$input}</TD>
	</TR>
HTMLFIN;
	}

	return $out.'</TABLE>';
}	
