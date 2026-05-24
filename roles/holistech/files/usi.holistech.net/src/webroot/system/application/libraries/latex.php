<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * LaTeX PDF output library
 *
 * @author Jason Karcz
 */
class LaTeX
{
	private	$pdf;		// FPDI instance
	private $latex;		// LaTeX source
	private $coursename;
	private $tempname;
	private $size;		// Font size
	private $spread=1.1;	// Line spread
	private $letterhead=FALSE;
	var $no_preamble=FALSE;
	private $line_number=1; // Starting line number for debug output

	function LaTeX($latex='',$coursename=NULL,$size=9)
	{
		ini_set('display_errors',TRUE);
		$this->latex = $latex;
		$this->size = $size;
		$this->coursename = ( $coursename === NULL ? COURSENAME : $coursename );

		$this->tempname = tempnam("/tmp", "LATEX");

		if ( $this->tempname === FALSE )
			fatal( "Error generating temporary filename" );
	}

	function __destruct()
	{
		`rm -f {$this->tempname}* /tmp/LATEX*`;
	}

	function pdf(&$pdf)
	{
		$this->pdf =& $pdf;

		$file = $this->_make_pdf();

		$pages = $this->pdf->setSourceFile($file);

		for ( $i = 1; $i <= $pages; $i++ )
		{
			$j = $i;
			while( $j>4 )
				$j -= 4;

			// Import Shell Page
			//$this->pdf->setSourceFile('images/wl_shell.pdf');
			//$tplidx = $this->pdf->ImportPage($j);

			//$s = $this->pdf->getTemplateSize($tplidx);
			//$this->pdf->AddPage('P', array($s['w'], $s['h']));
			
			//$this->pdf->useTemplate($tplidx);

			// Import LaTeX Page
			$this->pdf->setSourceFile($file);
			$tplidx = $this->pdf->ImportPage($i);
			
			$s = $this->pdf->getTemplateSize($tplidx);
			$this->pdf->AddPage('P', array($s['w'], $s['h']));
			
			$this->pdf->useTemplate($tplidx);

			if ( $this->letterhead && $i == 1 )
			{
				define('FPDF_FONTPATH','Fonts/');
				$this->pdf->AddFont('BeraSans','','BeraSans.php');
				$this->pdf->AddFont('BeraSans','B','BeraSansBold.php');

				$nau_logo_path = str_replace('index.php','',$_SERVER['SCRIPT_FILENAME']).'images/NAU_Road_Scholar.jpg';
				$exp_logo_path = str_replace('index.php','',$_SERVER['SCRIPT_FILENAME']).'images/Road_Scholar.jpg';
				$this->pdf->Image($nau_logo_path,.414,.414,0,.659);

				$this->pdf->Image($exp_logo_path,5.6,.414,0,0.5);

				$this->pdf->SetTextColor(0,51,102);

				// Old 1st Column
				//$this->pdf->SetFont('BeraSans','B',8);
				//$this->pdf->Cell(0,0,'Road Scholar');

				$this->pdf->SetFont('BeraSans','',8);

				// 1st Column
				$x = .96;
				$y = 1.5;
				$this->pdf->SetXY($x,$y);
				$this->pdf->Cell(0,0,'P.O. Box 5604');
				$this->pdf->SetXY($x,$y+.2);
				$this->pdf->Cell(0,0,'Flagstaff, AZ 86011-5604');

				// 2nd Column
				$x = 1+2;
				$this->pdf->SetXY($x,$y);
				$this->pdf->Cell(0,0,'928-523-2359');
				$this->pdf->SetXY($x,$y+.2);
				$this->pdf->Cell(0,0,'928-523-5991 fax');
				$this->pdf->SetXY($x,$y+.4);
				$this->pdf->Cell(0,0,'www.nau.edu/roadscholar');

				// 3rd Column
				//$x = 1.06+3.25;
				//$this->pdf->SetXY($x,1.75);
			}
		}
	}

	function add_letterhead()
	{
		$this->letterhead = TRUE;
	}

	function letterhead()
	{
		$ci =& get_instance();
		$this->letterhead = TRUE;
		$spread = $this->size * $this->spread;
		$this->latex = <<<LATEX
\\documentclass[{$this->size}pt,letterpaper]{article}
\\usepackage[letterpaper]{anysize}		% Macro to specify exact margins
\\marginsize{1in}{1in}{.95in}{.5in}	% Set the margins to 1in (3rd param (top): -.55in=>0in, .95=>1.5in)
\\usepackage{longtable}
%\\usepackage{tabls}
\\usepackage{multirow}
\\usepackage{multicol}
\\usepackage{ulem} % Provides strikeout (\sout{})

\\setlength{\\pdfpagewidth}{\\paperwidth}
\\setlength{\\pdfpageheight}{\\paperheight}

\\usepackage{cellspace}
\\setlength{\\cellspacetoplimit}{2pt}
\\setlength{\\cellspacebottomlimit}{2pt}

\\usepackage[T1]{fontenc}
\\usepackage[scaled]{berasans}
\\renewcommand*\\familydefault{\\sfdefault}

\\parindent 0in					% Make new paragraphs not indent.
\\begin{document}
\\vspace*{.63in} % 1.1=>2.75" first page margin
\\fontsize{{$this->size}}{{$spread}}\selectfont
{$this->latex}

\\end{document}
LATEX;
	}

	private function _make_pdf()
	{
		//print($this->latex);exit;
		$latex = $this->tempname.'.latex';
		$pdf = $this->tempname.'.pdf';
		$dvi = $this->tempname.'.dvi';
		$log = $this->tempname.'.log';

		$content = ( $this->letterhead || $this->no_preamble ? '' : $this->_preamble()).$this->latex;
		$content = preg_replace("/\[FONT ([^]]+)\]/e","\$this->font('\\1')",$content);
		$content = LaTeX::convert_smart_quotes($content);

		// Write LaTeX file
		file_put_contents($latex,$content);

		// Create PDF
		// More than one pass through LaTeX is necessary to fix longtables
		// Exporting TEXINPUTS adds the application latex directory to the LaTeX search path
		// so .sty files will be included
		$basepath = BASEPATH;
		$output = `export TEXINPUTS=\$TEXINPUTS:{$basepath}application/latex/ && pdflatex -output-directory /tmp "$latex" && pdflatex -output-directory /tmp "$latex"`;

		//`dvipdfm -p letter -o "$pdf" "$dvi"`;

		if ( !file_exists($pdf) || strpos($output,'Fatal') !== FALSE )
		{
			header('Content-type: text/plain');
			$out = file_get_contents($latex)."\n\n".file_get_contents($log);

			preg_replace('/^(.*)$/me','$this->_number_line(\'\1\')',$out);
			exit;
		}

		return $pdf;
	}

	private function _preamble() 
	{ 
	       return <<<LATEX
\documentclass[{$this->size}pt,letterpaper]{article}
						% Declare an article with 12pt font
						% 	(and different left and right margins = twoside)
\usepackage[dvips]{graphicx}			% Add \includegraphics capability
\usepackage{tabls}				% Make tables prettier
\usepackage[letterpaper]{anysize}		% Macro to specify exact margins
\usepackage{listings}				% Handles printing of source code

\marginsize{1in}{1in}{1in}{1in}			% Set the margins to 1in
\parindent 0in					% Make new paragraphs not indent.
\usepackage{multirow}				% Allow multirow command in tabulars
\usepackage{array}				% Allow m{width} in tabular
\\usepackage{longtable}

\\setlength{\\pdfpagewidth}{\\paperwidth}
\\setlength{\\pdfpageheight}{\\paperheight}

\\usepackage{cellspace}
\\setlength{\\cellspacetoplimit}{2pt}
\\setlength{\\cellspacebottomlimit}{2pt}


LATEX;
	}

	private function font($size)
	{
		$size += $this->size;
		$spread = $size*$this->spread;

		return "\\fontsize{{$size}}{{$spread}}\selectfont";
	}

	public static function escape($text)
	{
		$text = preg_replace("/([%&#_$])/",'\\\$1',$text);

		return $text;
	}

	// http://shiflett.org/blog/2005/oct/convert-smart-quotes-with-php
	// Altered by Jason Karcz
	public static function convert_smart_quotes($string) 
	{ 
	    $search = array(chr(226).chr(128).chr(152), // lsquo
			    chr(226).chr(128).chr(153), // rsquo
			    chr(226).chr(128).chr(156), // ldquo
			    chr(226).chr(128).chr(157)); // rdquo
	 
	    $replace = array("`", 
			     "'", 
			     '``', 
			     "''");
	 
	    return str_replace($search, $replace, $string); 
	} 

	private function _number_line($line)
	{
		$line = $this->line_number++.": ".$line."\n";
		print $line;
	}
}
