<?

class dbForm
{
	var $fields = array();
	private $confirmation;
	private $query;
	var $result;
	var $ci;

	function dbForm( $form )
	{
		debug_log('New dbForm('.$form.')');

		//$this->ci->output->enable_profiler(true);
	}
	
	function getPage($page)
	{
		if ( $page != 1 )
			return;
	}



}

