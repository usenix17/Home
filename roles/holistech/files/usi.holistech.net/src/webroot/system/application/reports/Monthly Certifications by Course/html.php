<H2>Monthly Certifications by Course</H2>

<?
	$courses = array();
	$report = array();
	foreach ( $result as $r ) {
		if ( !isset($report[$r['date']]) )
			$report[$r['date']] = array();
		$report[$r['date']][$r['course']] = $r['count'];
		$courses[] = $r['course'];
	}
	sort($courses);
	$courses = array_unique($courses);

?>
<TABLE CLASS='ticTacToe'>
	<TR>
		<TD></TD>
		<? foreach ( $courses as $c ): ?>
			<TH><?=$c?></TH>
		<? endforeach; ?>
	</TR>
	<? foreach ( $report as $date => $certs ): ?>
		<TR>
			<TD><B><?=$date?></B></TD>
			<? foreach ( $courses as $c ): ?>
				<TD><?=( isset($certs[$c]) ? $certs[$c] : '' )?></TD>
			<? endforeach; ?>
		</TR>
	<? endforeach; ?>
</TABLE>
