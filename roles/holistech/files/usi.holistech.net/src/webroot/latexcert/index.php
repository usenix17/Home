<?
	$data = unserialize( gzuncompress( base64_decode( $_REQUEST['data'] ) ) );

	// If not multiple certs at once, rework the input to coform to multiple certs format
	if ( !$data[0] )
	{
		$certs[0] = $data;
	}
	else
	{
		$certs = $data;
	}


	// Get a temporary name
	$temp = tempnam( '/tmp', 'LCERT' );

	// Grab the LaTeX parts
	$preamble = file_get_contents( 'preamble.latex' );
	$template = file_get_contents( 'template.latex' );

	// Go through each cert
	foreach ( $certs as $data )
	{
		$name = $data['name'];
		$date = date( "j F Y", $data['date'] );

		$latex[] = str_replace( array( '_NAME_', '_DATE_' ), array( $name, $date ), $template );
	}

	file_put_contents( $temp, $preamble . join( "\\newpage\n", $latex ) . '\end{document}' );

	shell_exec( 'latex -output-directory /tmp ' . $temp );
	shell_exec( 'dvipdfm -p letter -l -o '.$temp.'.pdf ' . $temp . '.dvi' );
	$pdf = file_get_contents( $temp . '.pdf' );
	shell_exec( 'rm ' . $temp . '*' );

	header( 'Content-type: application/pdf' );

	print $pdf;
?>
