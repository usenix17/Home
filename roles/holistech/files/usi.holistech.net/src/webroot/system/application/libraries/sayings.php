<?
//////////////////////////////////////////////////////////////////////
//
//      say.php
//      Jason Karcz
//      Common expressions in multiple languages
//
//////////////////////////////////////////////////////////////////////
//
//      16 October 2003 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading say.php');

class Sayings
{
	var $sayings;
	var $defaultSayings;

	function Sayings($config)
	{
		$this->defaultSayings = $config['defaultSayings'];
	}

	function say( $saying, $lang )
	{
		$out = array();
		$defaultText = '';

		if ( $lang == 'default' )
		{
			//foreach ( $this->defaultSayings as $lang => $sayings ) {
			foreach ( $GLOBALS['course']->getLanguages() as $lang ) {
				$sayings = $this->defaultSayings[$lang];
				$out[$lang] = $sayings[$saying];
			}
			$defaultText = lang($out);
		}
		else
			$defaultText = $this->defaultSayings[strtoupper( $lang )][$saying];

		if ( $defaultText != "" )
			return $defaultText;
		else
			warn("Unknown saying: \"" . $saying . "\".");
	}

	function input( $saying, $args )
	{
		$out = '';
		//foreach ( $this->defaultSayings as $lang => $sayings )
		foreach ( $GLOBALS['course']->getLanguages() as $lang )
		{
			$sayings = $this->defaultSayings[$lang];
			$out .= "<INPUT {$args} CLASS='lang_{$lang} lang' VALUE=\"{$sayings[$saying]}\">";
		}
		return $out;
	}
}

function say( $saying, $lang = "default" )
{
	$ci =& get_instance();

	return $ci->sayings->say($saying,$lang);
}
?>
