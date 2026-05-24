<?
/**
 * Benchmarking library
 */
class Bench
{
	var $marks = array();

	function start($tag,$comment)
	{
		$timeparts = explode(' ',microtime());
		$time = $timeparts[1].substr($timeparts[0],1);
		
		$this->marks[$tag] = array(
			'comment' => $comment,
			'time' => $time
		);
	}

	function end($tag)
	{
		if ( ! isset($this->marks[$tag]) )
			return;

		$timeparts = explode(' ',microtime());
		$time = $timeparts[1].substr($timeparts[0],1);
		$mark = $this->marks[$tag];

		$fp = fopen(BASEPATH.'application/logs/'.$tag.'.csv','a');
		fputcsv($fp,array(date('r'),$mark['comment'],$time-$mark['time']));
		fclose($fp);
	}

	function mark($tag,$comment)
	{
		$fp = fopen(BASEPATH.'application/logs/'.$tag.'.csv','a');
		fputcsv($fp,array(date('r'),$comment));
		fclose($fp);
	}
}
