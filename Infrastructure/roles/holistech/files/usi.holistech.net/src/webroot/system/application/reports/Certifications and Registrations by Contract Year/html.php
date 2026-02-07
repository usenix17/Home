<H2>Monthly Certifications by Course</H2>

<?
	$courses = array();
	$report = array();
	foreach ( $result as $r ) {
		if ( !isset($report[$r['contract_year']]) )
			$report[$r['contract_year']] = array('Total' => array(0,0));
		$report[$r['contract_year']][$r['course']] = array($r['registrations'], $r['certifications']);
		$report[$r['contract_year']]['Total'][0] += $r['registrations'];
		$report[$r['contract_year']]['Total'][1] += $r['certifications'];
		$courses[] = $r['course'];
	}
	sort($courses);
	$courses = array_unique($courses);
	array_push($courses,'Total')

?>
<TABLE CLASS='ticTacToe'>
	<TR>
		<TD></TD>
		<? foreach ( $courses as $c ): ?>
			<TH><?=$c?></TH>
		<? endforeach; ?>
	</TR>
	<? foreach ( $report as $contract_year => $info ): ?>
		<TR>
			<TD><B><?=$contract_year?></B></TD>
			<? foreach ( $courses as $c ): ?>
				<TD>
					<? if ( isset($info[$c]) ): ?>
						Registrations: <?=$info[$c][0]?><BR>
						Certifications: <?=$info[$c][1]?>
					<? endif; ?>
				</TD>
			<? endforeach; ?>
		</TR>
	<? endforeach; ?>
</TABLE>
