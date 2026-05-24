<?php

/**
 * Error Controller
 */
class Error extends Controller
{
	function clear($id)
	{
		$this->output->enable_profiler(true);
		$this->errors->clear($id);
	}
}
