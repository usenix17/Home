<?
//////////////////////////////////////////////////////////////////////
//
//	question.php
//	Jason Karcz
//	Test question handling class
//
//////////////////////////////////////////////////////////////////////
//
//	8 September 2004 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading question.php');
class Question
{
	var $question;
	var $type;
	var $options;
	var $randomize = false;
	var $statistics;
	var $hash;
	var $courseName;
	var $testName;
	var $test;
	var $number;
	var $hint;
	var $answered = false;
	var $last_attempt = NULL;
	var $attempt_id = NULL;
	var $response_data;
	var $response_text;
	var $disabled = FALSE;		// Placeholder for disabling questions

	function Question( $test )
	{
		debug_log('New Question()');

		$this->test = $test;
		$this->testName = $test->name;
		$this->courseName = $test->courseName;
	}

	function load_db($id)
	{
		$data = $GLOBALS['db']->get_row( '_questions', 'id', $id );

		$this->question = unstore( $data['question'] );
		$this->type = $data['type'];
		$this->options = unstore( $data['options'] );
		$this->randomize = $data['randomize'];
		//$this->statistics = unstore( $data['statistics'] );
		$this->id = $id;
		$this->number = $data['number'];
		$this->hint = unstore( $data['hint'] );
	}

	function __destruct()
	{
		debug_log('Destructing Question');
		unset($this->test);
		debug_log('Finished Destructing Question');
	}
	
	function generate_hash()
	{
		$this->hash = sha1($this->serialize());
	}

	function serialize()
	{
		return serialize(array(
			'question' => $this->question,
			'type' => $this->type,
			'options' => $this->options
		));
	}

	function save_object()
	{
		$this->generate_hash();
		$ci =& get_instance();

		// Find out if question object has been saved
		$result = $ci->db
			->from('question_objects')
			->where('question_hash',$this->hash)
			->get();

		// Insert object if its not there
		if ( $result->num_rows() != 1 ) {
			$ci->db->insert('question_objects',array(
				'object' => $this->serialize(),
				'question_hash' => $this->hash,
			));
		}
	}

	function display( $lang, $questionNumber, $highlight = FALSE, $showHint = false )
	{
		$out = '';
		$append = '';
		$hint = '';

		// Disable if the user got it right and disableCorrectAnswers is set
		if ( $GLOBALS['course']->disableCorrectAnswers && $this->last_attempt['correct'] )
		{
			$this->disabled = TRUE;
		}	

		// Display options
		// Ensure we have text for this language
		if ( ! isset($this->question[$lang]) ) {
			$this->question[$lang] = '<I>This question is not available in '.$GLOBALS['iso639'][$lang].'.';
		}
		else
		switch ( $this->type )
		{
			case 'short':
				$out .= $this->displayShort( $lang, $highlight );
				break;

			case 'single':
				$out .= $this->displaySingle( $lang, $highlight );
				break;

			case 'multiple':
				$out .= $this->displayMultiple( $lang, $highlight );
				$append = " <B>[SAY Check all that apply]</B>";
				break;
		}
		
		$disabled = '';
		if ( $this->disabled )
			$disabled = 'CLASS="questionDisabled"';
			
		if ( $showHint && $this->hint[$lang] )
			$hint = "<TR {$disabled}><TD>&nbsp;</TD><TD>Hint: {$this->hint[$lang]}</TD></TR>";
			
		$out = "<TR {$disabled}>
				<TD CLASS=questionNumber>{$questionNumber}.</TD>
				<TD CLASS=question>
					{$this->question[$lang]}{$append}
					<INPUT NAME='questions[]' TYPE='HIDDEN' VALUE='{$this->hash}'>
				</TD>
				</TR>
			{$hint}
			<TR {$disabled}><TD>&nbsp;</TD><TD>{$out}</TD></TR>";

		return $out;
	}

	function displayShort( $lang, $highlight )
	{
		// Get the last response for this question
		// (it will be text)
		$last_attempt = '';
		if ( $this->last_attempt !== NULL 
			&& $this->last_attempt['response'] !== NULL 
			&& $this->last_attempt['response'] !== FALSE )
			$last_attempt = $this->last_attempt['response'];

		// Change response to the correct answer string if highlighting is active
		if ( $highlight ) {
			$last_attempt = $this->options[$lang][0]['text'];
		}

		$disabled = ( $this->disabled ? 'DISABLED="disabled"' : '' );
		$out = "<TEXTAREA NAME={$this->hash} COLS=75 ROWS=5 {$disabled}>{$last_attempt}</TEXTAREA>";

		// Add a hidden correct response if the question is disabled
		if ( $this->disabled )
		{
			$response = addSlashes( $last_attempt );
			$out .= "<INPUT TYPE=hidden NAME={$this->hash} VALUE='{$response}'>";
		}

		return $out;
	}

	function displaySingle( $lang, $highlight )
	{
		// Prep variabes
		$disabled = ( $this->disabled ? 'DISABLED="disabled"' : '' );
		$out = '';

		// Get the last response for this question 
		// (it will be a numeric key from the array $this->options[$lang])
		$last_attempt = NULL;
		if ( $this->last_attempt !== NULL 
			&& $this->last_attempt['response'] !== NULL
			&& $this->last_attempt['response'] !== FALSE
			&& $this->last_attempt['response'] !== ''
	       	)
			$last_attempt = $this->last_attempt['response'];

		// For multiple choice (single answer), make a table of responses
		$out .= "<TABLE WIDTH=\"100%\">";

		// Start the option counter
		$optionNumber = 'a';
		
		$keys = isset($this->options[$lang]) ? array_keys( $this->options[$lang] ) : array();
		
		// Shuffle options if necessary
		if ( $this->randomize 
		&& ! $highlight 
		//&& ! $GLOBALS['user']->has(new Token('auth','dont_randomize_tests',COURSENAME)) 
		) {
			shuffle( $keys );
		}
		// Iterate through each option
		foreach ( $keys as $number ) {
			// Get the full option
			$option = $this->options[$lang][$number];

			// Find out if this option is selected from the user's last attempt
			$selected = '';
			if ( $last_attempt !== NULL && (int) $last_attempt === (int) $number )
				$selected = 'CHECKED';

			// Highlight the correct response if highlighting is enabled
			$correct = $highlight ? ( $option['answer'] ? 'CLASS=right' : '' ) : '';
			
			$out .= "<TR $correct>
					<TD CLASS=optionNumber>
						<INPUT TYPE=RADIO VALUE={$number} 
						NAME={$this->hash} ID={$this->hash}_{$lang}_{$number} 
						$selected {$disabled}>$optionNumber.
					</TD>
					<TD class=option>
						<LABEL FOR={$this->hash}_{$lang}_{$number}>{$option['text']}</LABEL>
					</TD>
				</TR>";
				
			// Increment the option number
			$optionNumber++;
		}
		
		// End the table of responses
		$out .= "</TABLE>";
		
		// Add a hidden response if the question is disabled
		if ( $this->disabled )
		{
			$out .= "<INPUT TYPE=hidden NAME={$this->hash} VALUE={$last_attempt}>";
		}

		return $out;
	}

	function displayMultiple( $lang, $highlight )
	{
		$disabled = ( $this->disabled ? 'DISABLED="disabled"' : '' );
		$out = '';

		// Get the last response for this question 
		// (it will be an array of numeric keys from the array $this->options[$lang])
		$last_attempt = array();
		if ( $this->last_attempt !== NULL && ! empty($this->last_attempt['response'] ) )
			$last_attempt = $this->last_attempt['response'];

		// For multiple choice (multiple answers), make a table of responses
		$out .= "<TABLE WIDTH=\"100%\">";

		// Start the option counter
		$optionNumber = 'a';
		
		$keys = isset($this->options[$lang]) ? array_keys( $this->options[$lang] ) : array();

		if ( $this->randomize 
		&& !$highlight 
		&& ! $GLOBALS['user']->has(new Token('auth','dont_randomize_tests',COURSENAME)) ) {
			shuffle( $keys );
		}
		
		// Iterate through each option
		foreach ( $keys as $number )
		{
			// Get the full option
			$option = $this->options[$lang][$number];

			// Find out if this option is selected from the user's last attempt
			$checked = '';
			$value = 0;
			if ( ! empty($last_attempt) && in_array($number,$last_attempt) )
			{
				$checked = 'CHECKED';
				$value = 1;
			}

			// Highlight the correct response if highlighting is enabled
			$correct = $highlight ? ( $option['answer'] ? 'CLASS=right' : '' ) : '';
			
			$out .= "<TR $correct>
					<TD CLASS=optionNumber>
						<INPUT TYPE=CHECKBOX VALUE=1 NAME={$this->hash}_{$number} 
						ID={$this->hash}_{$lang}_{$number} $checked {$disabled}>$optionNumber.
					</TD>
					<TD class=option>
						<LABEL FOR={$this->hash}_{$lang}_{$number}>{$option['text']}</LABEL>
					</TD>
				</TR>";

			// Add a hidden response if the question is disabled
			if ( $disabled )
				$out .= "<INPUT TYPE=hidden NAME={$this->hash}_{$number} VALUE={$value}>";
	
			// Increment the option nember
			$optionNumber++;
		}
		
		// End the table of responses
		$out .= "</TABLE>";

		return $out;
	}

	function check()
	{
		$correct = true;
		
		switch ( $this->type )
		{
			case 'short':
				$correct = $this->checkShort();
				break;

			case 'single':
				$correct = $this->checkSingle();
				break;

			case 'multiple':
				$correct = $this->checkMultiple();
				break;
		}

		// Log the question attempt
		$ci =& get_instance();
		$ci->db->insert('question_attempts', array(
			'attempt_id' => $this->attempt_id,
			'question_hash' => $this->hash,
			'response' => store($this->response_data),
			'correct' => $correct,
		));

		return $correct;
	}

	function checkShort()
	{
		$ci =& get_instance();
		$lang = $ci->input->post('lang');

		// Grab the response
		$this->response_text = stripslashes( $ci->input->post($this->hash) );
		$this->response_data = $this->response_text;

		// Get the information for the question number
		$answer = $this->response_data; // User's answer
		$correct = $this->options[$lang][0]['text']; // Correct answer
		
		if ( $answer )
			$this->answered = true;
		
		// First, trim any whitespace
		$correct = trim( $correct );
		$answer = trim( $answer );
		
		// If the correct answer is a star, return correct if anything was provided.
		if ( $correct == '*' and $answer != '' )
			return true;
		
		// Then, change all single &'s and |'s to doubles
		$correct = preg_replace( "/\&+/", " && ", $correct );
		$correct = preg_replace( "/\|+/", " || ", $correct );
		$correct = ' ' . $correct . ' ';

		// Next, surround the text with preg_match statements
		$correct = preg_replace( "/([\s()&|!]+?)([^\s()&|!]+?)([\s()&|!]+?)/", "\\1 preg_match( \"/\\2/i\", \"$answer\" ) \\3", $correct );

		// Evaluate the truth and return
		eval ( "\$yn = $correct;" );
		return $yn;
	}

	function checkSingle()
	{
		$ci =& get_instance();
		$lang = $ci->input->post('lang');

		// Grab the response
		$this->response_data = $ci->input->post($this->hash);

		if ( $this->response_data !== FALSE ) {
			$this->response_text = $this->options[$lang][$this->response_data]['text'];
			$this->answered = true;
		}
		else
			return FALSE;

		return $this->options[$lang][$this->response_data]['answer'];
	}

	function checkMultiple()
	{
		$ci =& get_instance();
		$lang = $ci->input->post('lang');

		$correct = true;
        $this->response_text = "<UL>";
		
		// Iterate through each option and form a boolean string that agrees with the correct answer
		// Also, create a displayable string to show the user's choices
		foreach ( $this->options[$lang] as $number => $option )
		{
			$response = $ci->input->post("{$this->hash}_{$number}");

			// If they checked a box, add the option to the response string
			// and verify that it's a correct response
			if ( $response == '1' )
			{
				$this->response_data[] = $number;
				$this->response_text .= "<LI STYLE='margin-left: 1.5em;'>" . $option['text'] . "</LI>";	
				$this->answered = true;			

				// The question is right as long as they haven't missed any previous check boxes 
				// AND their posted response to this option is the correct one.
				$correct = $correct && $option['answer'];
			}

			// If the box is not checked, make sure that it's the correct response
			else
				$correct = $correct && ! $option['answer'];
		}
        $this->response_text .= "</UL>";
		
		return $correct;
	}

	function get_XML()
	{
		// Save object to database
		$this->save_object();

		debug_log('Creating Question XML');
		$x = new XMLWriter();
		$x->openMemory();
		$x->setIndent(true);

		$x->startElement('question');
		$x->writeAttribute('type',$this->type);
		$x->writeAttribute('randomize',($this->randomize ? 'TRUE' : 'FALSE'));
		$x->writeAttribute('number',$this->number);

		foreach ( $this->question as $lang => $text ) {
			$x->startElement('text');
			$x->writeAttribute('lang',$lang);
			$x->text($text);
			$x->endElement();
		}

		foreach ( $this->options as $lang => $options ) {
			foreach ( $options as $option ) {
				$x->startElement('option');
				$x->writeAttribute('lang',$lang);
				$x->writeAttribute('answer',( isset($option['answer']) && $option['answer'] ? 'TRUE' : 'FALSE' ));
				@$x->writeAttribute('suggestion',$option['suggestion']);
				$x->text($option['text']);
				$x->endElement();
			}
			$hint = '';
			if ( isset($this->hint[$lang]) )
				$hint = $this->hint[$lang];
			$x->startElement('hint');
			$x->writeAttribute('lang',$lang);
			$x->text($hint);
			$x->endElement();
		}
		$x->endElement();

		$x->endElement();

		debug_log('Finished Creating Question XML');
		return $x->outputMemory();
	}

	function load_array($x)
	{
		$this->type = $x['type'];
		$this->randomize = isset($x['randomize']) && $x['randomize'] ? TRUE : FALSE;
		$this->question = $x['question'];
		$this->hint = $x['hint'];
		foreach ( $x['options'] as $lang => $options ) 
			foreach ( $options as $o ) {
				$this->options[$lang][] = array(
					'answer' => $o['answer'] ? TRUE : FALSE,
					'suggestion' => $o['hint'],
					'text' => $o['text'],
				);
			}

		$this->generate_hash();
	}

	function load_SimpleXMLObject($x)
	{
		$this->type = (string) $x['type'];
		$this->randomize = strtoupper((string) $x['randomize']) == 'TRUE' ? TRUE : FALSE;
		foreach ( $x->xpath('text') as $node ) {
			$this->question[(string) $node['lang']] = (string) $node;
		}
		foreach ( $x->xpath('option') as $node ) {
			$this->options[(string) $node['lang']][] = array(
				'answer' => (string) strtoupper((string) $node['answer']) == 'TRUE' ? TRUE : FALSE,
				'suggestion' => (string) $node['suggestion'],
				'text' => (string) $node,
			);
		}
		foreach ( $x->xpath('hint') as $node ) {
			$this->hint[(string) $node['lang']] =  (string) $node;
		}

		$this->generate_hash();
	}

	function load_last_attempt($row)
	{
		$this->last_attempt = array(
			'response' => unstore($row['response']),
			'correct' => (bool) $row['correct'],
		);
	}
}
?>
