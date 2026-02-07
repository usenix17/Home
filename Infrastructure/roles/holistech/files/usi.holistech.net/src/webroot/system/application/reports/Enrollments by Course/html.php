<H2>Enrollments by Course</H2>

<CENTER><TABLE CLASS='ticTacToe'>
	<TR><TH>Course</TH><TH>Date</TH><TH>Number of Enrollments</TH></TR>
<?
	$this->load->helper('graph');
	
	$registrations = array();
	foreach ( $result as $row ):
		$course = getCourse($row['course']);
?>
	<TR><TD><?=str_replace('<BR>',' ',$course->displayName)?></TD><TD ALIGN=CENTER><?=date('F Y',strtotime($row['date']))?></TD><TD ALIGN=CENTER><?=$row['count']?></TR>
<? endforeach; ?>

</TABLE></CENTER>
