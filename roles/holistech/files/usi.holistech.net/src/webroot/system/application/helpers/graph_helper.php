<?
//////////////////////////////////////////////////////////////////////
//
//      graph.php
//      Jason Karcz
//      Creates a graph
//
//////////////////////////////////////////////////////////////////////
//
//      10 September 2004 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading graph.php');
class Graph
{
	var $data;
	var $title;
	var $highlight;
	
	// One associative array x => y
	function Graph( $data, $title, $highlight = '' )
	{
		debug_log('New Graph(...)');
		$this->data = $data;
		$this->title = $title;
		$this->highlight = $highlight;
	}

	function bar( $min = '', $max = '' )
	{
		$sm = 1; // Step multiplier (steps must be multiples of $sm)
	
		if ( count( $this->data ) == 0 ) return '';//"<P>Insufficient Data</P>";

		if ( !is_int( $min ) )
			$min = min( $this->data );

		if ( !is_int( $max ) )
			$max = max( $this->data );
			
		$step = 1;
		
		if ( $max - $min > 10 )
			$step = $sm * ceil( ( $max - $min ) / ( 10 * $sm ) );
		
		if ( $max - $min < 5 )
			$step = 0.5;
			
		$min = $step * floor( $min / $step ); // Ensures $min is the next step down from $min

		$max = $step * ceil( $max / $step ); // Ensures $max is the next step up from $max


		$out = '<STYLE TYPE="text/css">

				th.graphTitle
				{
					border-bottom:	1px solid black;
					padding-bottom:	0.5em;
				}

				td.graphFilled
				{
					border-top:	1px solid black;
					border-left:	1px solid black;
					border-right:	1px solid black;
					background:	black;
				}

				td.graphHighlight
				{
					background-color:	#E0FFE0;
				}
				
				td.graphGray
				{
					border-bottom:	1px solid #DDD;
				}

				td.graphLast
				{
					border-right:	1px solid black;
				}
				
				td.graphNumber
				{
					border-left:	1px solid black;
					border-right:	1px solid black;

					text-align:	center;
					vertical-align:	middle;
				}

				td.graphBottom
				{
					border-top:	1px solid black;
				}
				
				td.graphLabel
				{
					vertical-align:	top;

					font-size:	10pt;
				}

			</STYLE>';

		
		$cols = count( $this->data ) * 3 + 1;
		$span = $cols - 1;
		$pct = 100 / $cols;
		
		$out .= "<TABLE CELLSPACING=0><COLGROUP>"
			. str_repeat( "<COL WIDTH='{$pct}%'>", $cols )
			. "</COLGROUP><TR><TH COLSPAN={$cols} CLASS=graphTitle>{$this->title}</TH></TR>\n";

		for ( $i = $max; $i >= $min; $i -= $step )
		{
			$out .= "<TR><TD ROWSPAN=2 CLASS=graphNumber>&nbsp;{$i}&nbsp;</TD>";
			$next = "<TR>";

			foreach ( $this->data as $label => $value )
			{
				$last = $label == array_pop( array_keys( $this->data ) ) ? ' graphLast' : '';
				$top = $value > $i + ( $step / 4 ) ? ( $label == $this->highlight ? 'graphHighlight' : 'graphFilled' ) : 'graphGray';
				$bottom = $value >= $i - ( $step / 4 ) ? ( $label == $this->highlight ? 'graphHighlight' : 'graphFilled' ) : '';
				
				$out  .= "<TD CLASS=graphGray>&nbsp;</TD><TD CLASS={$top}>&nbsp</TD><TD CLASS='graphgray{$last}'>&nbsp</TD>";
				$next .= "<TD>&nbsp;</TD><TD CLASS={$bottom}>&nbsp</TD><TD CLASS='{$last}'>&nbsp</TD>";
			}

			$out .= "</TR>\n";
			
			if ( $i != $min ) $out .= "{$next}</TR>\n";
		}

		$out .= "<TR><TD COLSPAN={$cols} CLASS=graphBottom>&nbsp;</TD></TR><TR><TD CLASS=graphBottom>&nbsp;</TD>";

		foreach ( $this->data as $label => $value )
		{
			$value = round($value,2);
			$out .= "<TD COLSPAN=3 ROWSPAN=2 CLASS="
			 . ( $label == $this->highlight ? 'graphHighlight' : 'graphLabel' )
			 . " ALIGN=CENTER>{$label} ({$value})</TD>";
		}

		$out .= "</TR></TABLE>";

		return $out;

	}

	function box()
	{
		if ( count( $this->data ) <= 3 ) return '';//"<P>Insufficient Data</P>";
		
		$title = $this->title;
		$min = min( $this->data );
		$q1  = $this->percentile( $this->data, 25 );
		$med = $this->percentile( $this->data, 50 );
		$q3  = $this->percentile( $this->data, 75 );
		$max = max( $this->data );
		$n = count( $this->data );
		$mean = $this->mean( $this->data );
		$stdev = $this->stdev( $this->data );

		$w1 = $q1 - $min;
		$w2 = $med - $q1;
		$w3 = $q3 - $med;
		$w4 = $max - $q3;

		return "
		<STYLE TYPE='text/css'>

			th.graphTitle
			{
				border-bottom:	1px solid black;
				padding-bottom:	0.5em;
			}

			td.graphQ1_top
			{
				border-left:	1px solid black;
				border-bottom:	1px solid black;
			}

			td.graphQ1_bottom
			{
				border-left:	1px solid black;
			}

			td.graphQ2
			{
				border-top:	1px solid black;
				border-left:	1px solid black;
				border-bottom:	1px solid black;
			}

			td.graphQ3
			{
				border-top:	1px solid black;
				border-left:	1px solid black;
				border-right:	1px solid black;
				border-bottom:	1px solid black;
			}

			td.graphQ4_top
			{
				border-right:	1px solid black;
				border-bottom:	1px solid black;
			}

			td.graphQ4_bottom
			{
				border-right:	1px solid black;
			}

			td.graphLeftGray
			{
				border-left:	1px solid #DDD;
			}

			td.graphRightGray
			{
				border-right:	1px solid #DDD;
			}

			td.graphLeft
			{
				border-left:	1px solid black;
			}
			
			td.graphRight
			{
				border-right:	1px solid black;
			}
			
			td.graphBottom
			{
				border-top:	1px solid black;
			}
			
			td.graphLabel
			{
				text-align:	left;
				vertical-align:	top;

				font-size:	10pt;
			}

		</STYLE>

	</HEAD>

	<BODY>

		<TABLE CELLSPACING=0>

			<COLGROUP SPAN=1 WIDTH='5%'></COLGROUP>
			<COLGROUP>
				<COL WIDTH='$w1*'>
				<COL WIDTH='$w2*'>
				<COL WIDTH='$w3*'>
				<COL WIDTH='$w4*'>
			</COLGROUP>
			<COLGROUP SPAN=1 WIDTH='5%'></COLGROUP>

			<TR>
				
				<TH CLASS=graphTitle COLSPAN=6 ROWSPAN=2>$title</TH>

			</TR>
			<TR></TR>
			<TR>
				
				<TD CLASS='graphLeft' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphLeftGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphLeftGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphLeftGray graphRightGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphRightGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphRight' ROWSPAN=2>&nbsp;</TD>

			</TR>
			<TR></TR>
			<TR>
				
				<TD CLASS='graphLeft' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphLeftGray' ROWSPAN=2>&nbsp;</TD> 
				<TD CLASS='graphQ2' ROWSPAN=6>&nbsp;</TD>
				<TD CLASS='graphQ3' ROWSPAN=6>&nbsp;</TD>
				<TD CLASS='graphRightGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphRight' ROWSPAN=2>&nbsp;</TD>
			</TR>
			<TR></TR>
			<TR>
				
				<TD CLASS='graphLeft' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphQ1_top'>&nbsp;</TD> 
				<TD CLASS='graphQ4_top'>&nbsp;</TD> 
				<TD CLASS='graphRight' ROWSPAN=2>&nbsp;</TD>
			</TR>
			<TR>
				
				<TD CLASS='graphQ1_bottom'>&nbsp;</TD> 
				<TD CLASS='graphQ4_bottom'>&nbsp;</TD> 
			</TR>
			<TR>
				
				<TD CLASS='graphLeft' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphLeftGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphRightGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphRight' ROWSPAN=2>&nbsp;</TD>

			</TR>
			<TR></TR>
			<TR>
				
				<TD CLASS='graphLeft' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphLeftGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphLeftGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphLeftGray graphRightGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphRightGray' ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphRight' ROWSPAN=2>&nbsp;</TD>

			</TR>
			<TR></TR>
			<TR>
				
				<TD CLASS='graphBottom' COLSPAN=6 ROWSPAN=2>&nbsp;</TD>

			</TR>
			<TR></TR>
			<TR>
				
				<TD CLASS='graphLabel' ALIGN=LEFT ROWSPAN=2>&nbsp;</TD>
				<TD CLASS='graphLabel' ALIGN=LEFT ROWSPAN=2>$min</TD>
				<TD CLASS='graphLabel' ALIGN=LEFT ROWSPAN=2>$q1</TD>
				<TD CLASS='graphLabel' ALIGN=LEFT ROWSPAN=2>$med</TD>
				<TD CLASS='graphLabel' ALIGN=LEFT ROWSPAN=2>$q3</TD>
				<TD CLASS='graphLabel' ALIGN=LEFT ROWSPAN=2>$max</TD>

			</TR>
			<TR></TR>
			<TR>
				
				<TD COLSPAN=6 ALIGN=CENTER>n = $n, mean = $mean, standard deviation = $stdev</TD>

			</TR>

		</TABLE>";
		
	}

	function percentile( $data, $percentile )
	{
		sort( $data );
		
		$a = floor( ( count( $data ) + 1 ) * ( $percentile / 100 ) ) - 1;
		$b =  ceil( ( count( $data ) + 1 ) * ( $percentile / 100 ) ) - 1;

		return ( $data[$a] * ( $percentile / 100 ) ) + ( $data[$b] * ( 1 - ( $percentile / 100 ) ) );
	}

	function mean( $data )
	{
		$sum = 0;

		foreach ( $data as $datum )
		{
			$sum += $datum;
		}

		return round( $sum / count( $data ), 2 );
	}

	function stdev( $data )
	{
		if ( count( $this->data ) <= 1 ) return "Insufficient Data";

		$sum = 0;
		$mean = $this->mean( $data );

		foreach ( $data as $datum )
		{
			$sum += pow( $datum - $mean, 2 );
		}

		return round( sqrt( $sum / ( count( $data ) - 1 ) ), 2 );
	}
}
?>
