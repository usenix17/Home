<?
/**
 * survey_range( field, min, max )
 * Creates a min-max rating scale form element
 *
 * @param string
 * @param int
 * @param int
 * @return string
 */
function survey_range( $field, $min, $max )
{
	$out = "<SPAN CLASS='survey_range'>";

	for ( $i=$min; $i<=$max; $i++ ) {
		$checked = '';
		if ( $i == $_POST[$field] )
			$checked = 'CHECKED';

		$id = uniqid();
		$out .= "<INPUT TYPE=RADIO VALUE={$i} {$checked} NAME=\"$field\" ID='{$id}'><LABEL FOR='{$id}'>{$i}.</LABEL>&nbsp;";
	}

	return $out . "</SPAN>";
}
