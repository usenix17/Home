<?php

/**
 * Layout Controller
 */
class Layout extends Controller
{
	var $layout;

	function Layout()
	{
		parent::Controller();

	/* Determine layout name */
		if ( !isset($GLOBALS['course']) )
			$GLOBALS['course'] = new Course(COURSENAME);

		$this->layout = $GLOBALS['course']->layout;
	}

	function index()
	{
		$this->load->helper('file');
		$this->load->helper('formtable');
		$text = lang_files('login');
		$login = $this->load->view('/users/login',array('text'=>$text),TRUE);

		$this->load->view('layout/index',array(
			'banner' => lang_files('banner'),
			'login' => $login,
			'utility1' => $this->_utility1(),
			'utility2' => $this->_utility2(),
		));
	}

	function css()
	{
		$out = '';

	/* Set the header */
		$this->output->set_header('Content-type: text/css');

	/* Concatenate all of the files */
		$csss = array();
		foreach ( 
			array_merge( 
				glob(BASEPATH.'application/css/*/*.css'), 
				glob(BASEPATH.'application/css/*.css'),
				glob(BASEPATH.'application/layouts/'.$this->layout.'/css/*.css'),
				glob(BASEPATH.'application/resources/'.COURSENAME.'/css/*.css')
			) 
		as $css )
		{
			$out .= "/* File: ".str_replace(BASEPATH.'application/','/',$css)." */\n\n"
				.file_get_contents($css)."\n\n";
		}

	/* Send to output */
		$this->load->view('layout/css', array(
			'css' => $out
		));
	}

	function layout_JS()
	{
		$out = '';

	/* Concatenate all of the files */
		foreach ( 
			array_merge( 
				glob(BASEPATH.'application/layouts/'.$this->layout.'/js/*.js'),
				glob(BASEPATH.'application/resources/'.COURSENAME.'/js/*.js')
			) 
		as $script )
		{
			$out .= "/* File: ".str_replace(BASEPATH.'application/','/',$script)." */\n\n"
				.file_get_contents($script)."\n\n";
		}

	/* Send to output */
		$this->load->view('layout/script', array(
			'script' => $out
		));
	}

	function script($a,$b=NULL)
	{
		$this->output->set_header('Content-type: text/javascript');
		$path = ( $b==NULL ? $a : $a.'/'.$b );

		$out = array( 'script' => file_get_contents(BASEPATH.'application/js/'.$path) );

		$this->load->view('layout/script', $out);
	}

	function js()
	{
		// Skip real-time concatenation and produce pre-concatenated all.min.js
		//$this->load->view('layout/script', array(
		//	'script' => file_get_contents(BASEPATH.'../js/all.min.js'),
		//));
		//return;
		
		$this->load->helper('jsmin-1.1.1');
		$out = '';

	/* Concatenate all of the files */
		foreach ( 
			array_merge( 
				glob(BASEPATH.'application/layouts/'.$this->layout.'/js/*.js'),
				glob(BASEPATH.'application/resources/'.COURSENAME.'/js/*.js'),
				glob(BASEPATH.'application/js/*/*.js'),
				glob(BASEPATH.'application/js/*.js')
			) 
		as $script )
		{
			$out .= "/* File: ".str_replace(BASEPATH.'application/','/',$script)." */\n\n"
				//.JSMin::minify(file_get_contents($script))."\n\n";
				.file_get_contents($script)."\n\n";
		}

	/* Send to output */
		$this->load->view('layout/script', array(
			'script' => $out
		));
	}	

	/**
	 * This function can be used to release locks without needing to load any content.
	 */
	function dummy()
	{
	}

	function images($image,$arg2='')
	{
		if ( !empty($arg2) )
			$image = "{$image}/{$arg2}";

	/* Determine possible image paths in selection order */
		$paths[] = BASEPATH.'application/resources/'.COURSENAME.'/images/'.$image;
		$paths[] = BASEPATH.'application/layouts/'.$this->layout.'/images/'.$image;
		$paths[] = BASEPATH.'application/images/'.$image;

	/* TODO: add more paths for internationalized images. */

	/* Look for images and return first found */
		foreach ( $paths as $path )
		{
			if ( file_exists($path) )
			{
				$ext = substr($path, -3, 3);
				header('Content-type: image/'.$ext);

				print file_get_contents($path);
				return;
			}
		}
	}

	function set_layout($layout)
	{
		//$this->output->enable_profiler(TRUE);
		$this->session->set_userdata('layout',$layout);
		print "<SCRIPT>window.location.reload();</SCRIPT>";
	}

	function utility1()
	{
		$this->output->set_output($this->_utility1());
	}

	function utility2()
	{
		$this->output->set_output($this->_utility2());
	}

	private function _utility1()
	{
		$logged_in = FALSE;
		// Test authentication without considering this session-free controller
		if ( $this->auth->authenticate(TRUE,TRUE) &&
			isset($GLOBALS['user']) )
			$logged_in = TRUE;

		return $this->load->view('layout/utility1',array(
			'logged_in' => $logged_in,
		),TRUE);
	}

	private function _utility2()
	{
		$layouts = glob(BASEPATH.'application/layouts/*');
		foreach ( $layouts as &$l )
			$l = str_replace(BASEPATH.'application/layouts/','',$l);

		$logged_in = FALSE;
		// Test authentication without considering this session-free controller
		if ( $this->auth->authenticate(TRUE,TRUE) &&
			isset($GLOBALS['user']) )
			$logged_in = TRUE;

		$languages = array();
		foreach ( $GLOBALS['course']->getLanguages() as $lang ) {
			$languages[$lang] = $GLOBALS['iso639'][$lang];
		}	

		return $this->load->view('layout/utility2',array(
			'layouts' => $layouts,
			'layout' => $this->layout,
			'logged_in' => $logged_in,
			'languages' => $languages,
		),TRUE);
	}

	function flash($a,$b=NULL)
	{
		if ( $b !== NULL )
			$a .= '/'.$b;

		$path = BASEPATH.'application/resources/'.COURSENAME.'/flash/'.$a;

		if ( file_exists($path) ) {
			header('Content-type: application/x-shockwave-flash');
			echo file_get_contents($path);
		}
	}
}
