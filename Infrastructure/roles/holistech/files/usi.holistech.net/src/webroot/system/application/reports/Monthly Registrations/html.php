<?
	$this->load->helper('graph');
	
	$registrations = array();
	foreach ( $result as $row ) 
		$registrations[date('M Y',strtotime($row['date']))] = $row['count'];

	$graph = new Graph($registrations,'Registrations by Month');
	echo $graph->bar();
?>
