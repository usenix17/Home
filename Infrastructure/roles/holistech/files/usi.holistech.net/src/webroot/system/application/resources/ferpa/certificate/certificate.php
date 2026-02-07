<?
	$date = date( "j F Y" );
	$expiry = date( "j F Y", strtotime( "+3 years", time() ) );

	$pdf->AddPage('L');

	$pdf->Image($imagePath.'certificate.png',.25,.3,10.5);
	$pdf->Image($imagePath.'COC.jpg',1.5,1.5,8);

	$pdf->SetFont('Times','',20);
	$pdf->SetXY(.5,2.75);
	$pdf->Cell(10,0,'This document certifies that',0,1,'C');

	$pdf->SetFont('Times','',30);
	$pdf->SetXY(1.5,3.25);
	$pdf->Cell(8,.5,$name,'B',1,'C');

	$pdf->SetFont('Times','',16);
	$pdf->SetXY(.5,4.375);
	$pdf->Cell(10,0,'has successfully completed "'.$GLOBALS['course']->text_name().'" on',0,1,'C');

	$pdf->SetFont('Times','I',20);
	$pdf->SetXY(.5,5);
	$pdf->Cell(10,0,$date,0,1,'C');

//	$pdf->Image( $imagePath.'CCHD.gif', 11/3-1.5, 5.05+1.33-.787, 3 );
    $logo_width = 4;
    $logo_top   = 5.75;
	$pdf->Image( $imagePath.'Holistech_Logo_1024x217.png', (11-$logo_width)/2-1, $logo_top, $logo_width );

	//$pdf->Rect(.75,.75,9.5,7);
