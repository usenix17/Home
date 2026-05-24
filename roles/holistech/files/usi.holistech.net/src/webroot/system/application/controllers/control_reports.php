<?php

/**
 * Reporting Control Panel
 */
class Control_Reports extends Controller
{
	var $x;		// Simple XML object

	function Control_Reports()
	{
		parent::Controller();

		$this->session->set_userdata('control_selected','control_reports');

		if ( ! $GLOBALS['user']->has(new Token('auth','view_reports',COURSENAME)) )
			fatal('That operation is not allowed');
	}

	function index()
	{
		$reports = array();
		foreach ( glob(BASEPATH.'application/reports/*/') as $report ) {
            $report = basename($report);
            if ( $GLOBALS['user']->has(new Token('auth', "rep:$report", COURSENAME)) || $GLOBALS['user']->has(new Token('auth', "rep:*", COURSENAME)) )
                $reports[] = $report;
		}

		$this->load->view('control/reports/list',array('reports'=>$reports));
	}

	function show($report)
	{
        if ( ! $GLOBALS['user']->has(new Token('auth', "rep:$report", COURSENAME)) && ! $GLOBALS['user']->has(new Token('auth', "rep:*", COURSENAME)) )
			fatal('That operation is not allowed');

	/* Load XML file */
		$this->_load_xml($report);

	/* Read the user parameters */
		$parameters = array();
		foreach ( $this->x->xpath('/report/parameters/param') as $param ) {
			switch ( $param['type'] ) {
			case 'date':
				$parameters[] = $this->_param_date($param);
				break;

			default:
				fatal('Unknown parameter type: '.$param['type']);
				break;
			}
		}

    /* Process additional parameters if necessary */
		$parameters_include = BASEPATH."application/reports/{$report}/parameters.php";
		if ( file_exists($parameters_include) ) 
            include($parameters_include);

    /* Include a CSV control if there's an available generator file */
		if ( file_exists(BASEPATH."application/reports/{$report}/csv.php" ) ) 
            $parameters[] = "<BR><INPUT TYPE=checkbox ID=csv_output> <LABEL FOR=csv_output>CSV Output</LABEL>";

		$this->load->view('control/reports/show',array(
			'report' => $report,
			'parameters' => $parameters,
			'columns' => 2,
		));
	}

	function run($report)
	{
        $output_type='html';
        if ( substr($report,-4) == '.csv' )
        {
            $output_type = 'csv';
            $report = substr($report,0,-4);
        }

		$replace = array();

    /* Restrict access */
        if ( ! $GLOBALS['user']->has(new Token('auth', "rep:$report", COURSENAME)) && ! $GLOBALS['user']->has(new Token('auth', "rep:*", COURSENAME)) )
			fatal('That operation is not allowed');

	/* Load XML file */
		$this->_load_xml($report);

	/* Grab the parameters */
		foreach ( $this->x->xpath('/report/parameters/param') as $param ) {
			$post = $this->input->post((string) $param['name']);
			if ( empty($post) )
				$replace[(string) $param['name']] = (string) $param->default;
			else {
				switch ( $param['type'] ) {
				case 'date':
					$this->load->plugin('mysql_date');
					$post = mysql_date(strtotime($post));
					break;
				}

				$replace[(string) $param['name']] = str_replace(
                                "[value]",
                                $this->db->escape($post),
                                (string) $param->sql
                );
			}
		}		

	/* Select the courses */
		$courses = array_pop($this->x->xpath('/report/courses'));
		if ( $courses ) {
			switch ( $courses['value'] ) {
			case 'current':
				$replace[(string) $courses['name']] = (string) $courses['field']
									.'='
									.$this->db->escape(COURSENAME);
				break;

			case 'enrolled':
				$list = array();
				foreach ( $GLOBALS['user']->courses as $c )
					$list[] = $this->db->escape((string) $c);
				$replace[(string) $courses['name']] = (string) $courses['field']
									.' in '
									.'('.implode(',',$list).')';
				break;

			case 'all':
				$replace[(string) $courses['name']] = 'true';
				break;

			case 'specified':
				$list = array();
				foreach ( $this->x->xpath('/report/courses/course') as $c )
					$list[] = $this->db->escape((string) $c);
				$replace[(string) $courses['name']] = (string) $courses['field']
									.' in '
									.'('.implode(',',$list).')';
				break;
			}
		}

	/* Calculate the SQL */
		$sql = (string) array_pop($this->x->xpath('/report/sql'));
		foreach ( $replace as $k => $v )
			$sql = str_replace("[$k]",$v,$sql);
				
	/* Run the query */
		$result = $this->db->query($sql)->result_array();

	/* Print debugging information */
	if ( $this->x->xpath('/report/debug') )
		var_dump($sql,$result);

	/* Format the output */
		if ( count($result) )
		{
            if ( $output_type == 'csv' ) 
                $this->output->set_header('Content-type: text/csv');
                $this->output->set_header('Content-Disposition: attachment; filename="'.$report.'.csv"');

			$include = BASEPATH."application/reports/{$report}/".$output_type.".php";
			if ( ! file_exists($include) )
				fatal("Report view file for '{$report}' does not exist.");

			ob_start();
			include($include);
			$this->output->set_output(ob_get_contents());
			ob_end_clean();
		} else {
			$this->output->set_output('<I>No results found.</I>');
		}
			
	}

	private function _load_xml($report)
	{
		$file = BASEPATH."application/reports/{$report}/report.xml";
		if ( ! file_exists($file) )
			fatal("Report definition file for '{$report}' does not exist.");

		$this->x = simplexml_load_string(file_get_contents($file));
	}

	private function _param_date($param)
	{
		return '<B>'.$param->label.':</B> <INPUT TYPE=TEXT NAME="'.$param['name'].'"><SCRIPT>
			$J("#report_parameters INPUT[NAME='.$param['name'].']").datepicker({
				"changeMonth": true,
				"changeYear": true
			});</SCRIPT>';
	}
}
