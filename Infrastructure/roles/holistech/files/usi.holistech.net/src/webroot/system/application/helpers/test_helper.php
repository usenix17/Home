<?
//////////////////////////////////////////////////////////////////////
//
//	test.php
//	Jason Karcz
//	Test handling class
//
//////////////////////////////////////////////////////////////////////
//
//	2 November 2003 - Created
//
//////////////////////////////////////////////////////////////////////
//
//	VI Commands for formatting XML test
//
//	%s/\n\s*/\r/g
//	%s/\n\n\+/\r\r/g
//	%s/\s\+/ /g
//	%s/</\&lt;/g
//	%s/>/\&gt;/g
//	%s/?/'/g
//	%s/[??]/"/g
//	%s/?/-/g
//	%s#^\(\d\+\)\.\s*\(.\+\)#\t\t</QUESTION>\r\r\t\t<QUESTION TYPE="" NUMBER="\1">\r\r\t\t\t<TEXT LANG="EN">\2</TEXT>#g
//	%s#^\w\+\.\s*\*\(.\+\)#\t\t\t<OPTION LANG="EN" CORRECT="TRUE">\1</OPTION>#g
//	%s#^\w\+\.\s*\(.\+\)#\t\t\t<OPTION LANG="EN">\1</OPTION>#g
//	%s#^hint:\?\W\+\(.\+\)#\t\t\t<HINT LANG="EN">\1</HINT>#ig

debug_log('Loading test.php');
class Test extends Module
{
	// Instance Variables
	var $title;			// The corresponding skill's title
	var $instructions;		// Instructions
	var $email;			// E-mail address(es) to send results
	var $skill;			// The name of the corresponding skill
	var $questions;			// An array of all of the questions and answers in all languages
	var $randomize;			// Present the questions in random order?
	var $numQuestions;		// How many questions to present
	var $survey;			// Information for the test if it's a survey
	var $customMinScore;		// Use custom score or course default
	var $minScore;			// Minimum grade to pass in percent
	var $maxAttempts;		// The maximum number of attempts to allow
	var $suppressResults;		// Do not show the correct/incorrect answers
	var $suppressScore;		// Do not show the score
	var $nextButton;		// The button to go to the next page after the results.
	var $graded;			// Statement to appear at the top of the results page after submitting
	var $passed;			// Congratulatory statement to appear on the results page after passing
	var $failed;			// Statement to appear on the results page after failing
	var $correct;			// Text that indicates a correct answer
	var $tryAgain;			// Text that indicates an incorrect answer
	var $showHints;			// Attempt number to show hints with questions (0 for never)

	var $statistics;		// Test-wide stats

	var $allowPrevAndNext;		// Switch for prev and next buttons
	var $number;			// Test / skill number - access via number()
	var $skillTitle;		// The menu entry of the corresponding skill
	var $display;			// What to be displayed (after parsing)
	var $responses;			// Placeholder for user responses
	var $suggestions;		// Placeholder for suggestions
	var $languages = array();	// The languages supported by this test
	var $attempt_id;		// Identifier for the specific test attempt
	var $last_results = NULL;	// The results of the last attempt made by the current user

	/***************************************************
	 * Importing Variables
	 ***************************************************/

	private $cdata_settings = array('email','numQuestions','minScore','maxAttempts','nextButton','showHints');
	private $localized_settings = array('title','instructions','graded','passed','failed','correct','tryAgain');
	private $boolean_settings = array('randomize','survey','suppressResults','suppressScore','customMinScore');
	
	// Constructor
	function Test( $module_id )
	{
		parent::Module($module_id);

		debug_log('New Test('.$this->name.','.$this->courseName.')');
		$ci =& get_instance();
		$ci->load->helper('skill');
		$ci->load->helper('question');
		$ci->load->plugin('replaceTags');

		//$this->read_DB();
		//$this->write_XML();
		$this->read_XML();
	}

	function __destruct()
	{
		debug_log('Destructing test '.$this->name);
		unset($this->questions);
		foreach ( array_merge($this->cdata_settings,$this->localized_settings,$this->boolean_settings) as $x )
			unset($this->$x);
		debug_log('Done destructing');
	}

	function read_DB()
	{
		// Get test from DB
		debug_log("Get test from db");
		//$contents = $GLOBALS['db']->get_row_from_sql( "SELECT * FROM tests WHERE name='{$this->name}' AND course='{$this->courseName}';" );
		$ci =& get_instance();
		$result = $ci->db
			->select('t.*,s.title as skillTitle,m.number as skillNumber')
			->from('_tests t')
			->join('_skills s','t.skill = s.name AND t.clientName = s.clientName','left')
			->join('_moduleSets m','t.skill = m.module AND t.clientName = m.clientName AND m.type="skill"','left')
			->where('t.name',$this->name)
			->where('t.clientName',$this->courseName)
			->get()
			->result_array();

		$contents = $result[0];
		debug_log("Separate into variables");
		
		// Distribute to instance variables
		$this->randomize = $contents['randomize'];
		$this->numQuestions = $contents['numQuestions'];
		$this->instructions = unstore( $contents['instructions'] );
		$this->email = $contents['email'];
		$this->skill = $contents['skill'];
		$this->survey = $contents['survey'];
		$this->customMinScore = $contents['customMinScore'];
		$this->minScore = $contents['minScore'];
		$this->maxAttempts = $contents['maxAttempts'];
		$this->suppressResults = $contents['suppressResults'];
		$this->suppressScore = $contents['suppressScore'];
		$this->title = unstore( $contents['title'] );
		$this->menuColor = $contents['menuColor'];
		$this->headerColor = $contents['headerColor'];
		$this->nextButton = ( strpos($contents['nextURL'],'logout') !== FALSE ? '[LOGOUT]' : '' );
		$this->graded = unstore( $contents['graded'] );
		$this->passed = unstore( $contents['passed'] );
		$this->failed = unstore( $contents['failed'] );
		$this->correct = unstore( $contents['correct'] );
		$this->tryAgain = unstore( $contents['tryAgain'] );
		//$this->statistics = unstore( $contents['statistics'] );
		$this->showHints = $contents['showHints'];

		// Determine all of the languages used in this test
		foreach ( $this->localized_settings as $s ) {
			if ( is_array($this->$s) )
			foreach ( $this->$s as $lang => $dummy ) {
				$this->languages[] = $lang;
			}
		}
		$this->languages = array_unique($this->languages);

		// Grab the associated skill's title and normalize the languages
		if ( $contents['skillTitle'] !== NULL )
			$this->title = unstore($contents['skillTitle']);
		foreach ( $this->languages as $lang )
			if ( empty($this->title[$lang]) )
				$this->title[$lang] = $this->title['EN'];

		// Ensure the number is correct in the syllabus
		if ( $this->number() != $contents['skillNumber'] ) {
			$ci->db
				->set('number',$contents['skillNumber'])
				->where('course',$this->courseName)
				->where('module_id',$this->module_id)
				->update('syllabi');
		}

		// Get the list of questions from the database
		$result = $GLOBALS['db']->query( 
			"SELECT id FROM _questions WHERE 
			clientName = '{$this->courseName}' AND
			testName = '{$this->name}'
			ORDER BY number;" );
		foreach ( $result as $row ) {
			$question = new Question($this);
			$question->load_db($row['id']);
			$this->questions[] = $question;
		}
	}
	
	function getTitle($wrapper='DIV')
	{
		$title = array();

		foreach ( $GLOBALS['course']->getLanguages() as $lang )
		{
			if ( empty($this->title[$lang]) )
				$title[$lang] = ( ( $this->number() != 0 ) ? say("Test",$lang) 
				. " " . $this->number() . " - " : "" ) . '<I>No Title</I>' ;
			else
				$title[$lang] = ( ( $this->number() != 0 ) ? say("Test",$lang) 
				. " " . $this->number() . " - " : "" ) . $this->title[$lang];
		}

		return lang(str_replace(' - ',' &ndash; ',$title),$wrapper);
	}
	
	function getPage($page)
	{
		if ( $page > 1 )
			fatal('Invalid page number');

		$edit = '';
		if ( $GLOBALS['user']->has(new Token('edit','content',$this->courseName)) ) {
            $file = $this->XML_path();
            if ( is_writable($file) )
                $edit = "<P STYLE='float: right'>[<A HREF=\"javascript:edit.test_edit('{$this->module_id}')\">Edit</A>]</P>";
            else
                $edit = "<P STYLE='float: right'>[Source File Not Writable]</P>";
        }

		return $edit.$this->getTitle('H1').$this->display().'<SCRIPT>pager.page_scroll_set()</SCRIPT>';
	}

	function numPages()
	{
		return 1;
	}

	function display()
	{
		// Disallow after maxAttempts is reached.  Show the last gradetable.
		if ( $this->maxAttempts && $GLOBALS['user']->count_test_attempts($this->module_id) >= $this->maxAttempts )
		{
			return "<P>".lang(array(
					'EN' => 'You have reached the maximum number of attempts for this test.',
					'ES' => 'Usted ha alcanzado el m&aacute;ximo n&uacute;mero de intentos por esta prueba.',
				))."</P>" . 
				$GLOBALS['user']->get_last_test_results_HTML($this->module_id);
		}

		// If were initiating a site shutdown, don't allow the users to start tests.
		if ( $GLOBALS['shutDownLevel'] >= 2 && !$GLOBALS['user']->has(new Token('auth','ignore_shutdown')) )
		{
			return;
		}

		// Start a new test attempt 
		$this->attempt_id = uniqid('',TRUE);

		$test = array();

		foreach ( $GLOBALS['course']->getLanguages() as $lang )
		{
			if ( in_array($lang,$this->languages) )
				$test[$lang] = $this->create_test($lang);
			else
				$test[$lang] = '<I>This test has not been translated into '.$GLOBALS['iso639'][$lang].'.</I>';
		}

		return lang($test,'DIV');
	}

	function create_test($lang)
	{
		$this->load_last_results();

		// Output the instructions
		$out = "<P CLASS=instructions>" . ( isset($this->instructions[$lang]) ? $this->instructions[$lang] : '' ) . "</P><HR>";

		// Start the form and the table
		$out .= "<FORM METHOD=POST>
			<INPUT TYPE=HIDDEN NAME=lang VALUE='".$lang."'>
			<INPUT TYPE=HIDDEN NAME=attempt_id VALUE='".$this->attempt_id."'>
			<TABLE WIDTH=\"100%\">";

		// Start the counter
		$questionNumber = 1;

		// Get the list of questions from the database
		$questions = $this->questions;

        if ( gettype($questions) == 'array' ) {
            if ( $this->randomize /*&& ! $GLOBALS['user']->has(new Token('auth','dont_randomize_tests',COURSENAME))*/ )
            {	
                shuffle( $questions );
            }

            // Iterate through the questions
            foreach( $questions as $question )
            {
                // $this->showHints = 1 would mean to show hints on their "first" attempt: 0 previous attempts
                $showHints = ( $GLOBALS['user']->count_test_attempts($this->module_id) >= $this->showHints - 1 ) 
                    && $this->showHints;
                    
                // Display the question
                $out .= $question->display( $lang, $questionNumber, FALSE, $showHints );
            
                // Put a dividing line in
                $out .= "<TR><TD COLSPAN=2><HR WIDTH=\"75%\"></TD></TR>";

                // Stop when the number of questions is reached
                // (this will never be true if numQuestions is 0)
                if ( $questionNumber == $this->numQuestions )
                    break;
                
                // Increment the counter
                $questionNumber++;
            }
        }

		// Add on submit and end the form
		$ci =& get_instance();
		$button = $ci->sayings->input('Submit','TYPE=BUTTON NAME=submit ONCLICK="submit_test(this);"');
		$out .= "</TABLE><P ALIGN=CENTER>{$button}</P></FORM><HR>";
			
		return filterTags( $out, 'PAGE', $lang );
	}

	function grade()
	{
		// Get the array of questions displayed
		$ci =& get_instance();
		$questions = $ci->input->post('questions');
		$lang = $ci->input->post('lang');
		$this->attempt_id = $ci->input->post('attempt_id');

		$questionNumber = 1;
		$numberPossible = 0;
		$numberCorrect = 0;
		$table = '';
			
		// Iterate through all of the problems in the test they took, checking each one
		// and incrementing the count.  Also, make the results table
		foreach ( $questions as $hash )
		{
			// Get the question object
			$question = $this->questions[$hash];
			$question->attempt_id = $this->attempt_id;
			
			$numberPossible++;

			// Check the answer
			if ( $question->check() )
			{
				$numberCorrect++;
				
				$table .= "<TR CLASS=right>";
				$hint = say("Correct") . "!";
				if ( !empty($this->correct[$lang]) ) $hint = $this->correct[$lang];
			}
			else
			{
				$table .= "<TR CLASS=wrong>";
				$hint = say("Try again") . ".";
				if ( !empty($this->tryAgain[$lang]) ) $hint = $this->tryAgain[$lang];
			}

			// Add the question and the user's response
			$text = $question->question[$lang];
			$table .= "<TD CLASS=option>{$questionNumber}. {$text}</TD>
				<TD CLASS='option response'>{$question->response_text}</TD>
				<TD CLASS=option>{$hint}</TD></TR>\n";

			$questionNumber++;
		}

		// Evaluate the score
		$score = intval( $numberCorrect / $numberPossible * 1000 ) / 10;

		// Put the header on the table
		$out = '<P>' . ( !empty($this->graded[$lang]) ? lang($this->graded) : say("graded...") ) . '</P>';
		
		// Display the results unless suppressed
		if ( !$this->suppressResults )
		{
			$out .=   "<TABLE WIDTH=\"100%\" CLASS='gradeTable ticTacToe'><TR>"
				. "<TH>" . say( "Question" ) . "</TH>"
				. "<TH>" . say( "Your Response" ) . "</TH>"
				. ( $this->survey ? "" : "<TH>" . say( "Our Suggestion" ) . "</TH>" )
				. "</TR>\n{$table}</TABLE>";
		}

		$out .=	( $this->suppressScore ? "" : "<P>".say( 'Your score is' )." {$score}%.</P>" );

		// Retrieve the minimum passing score
		$minScore = ( $this->customMinScore ? $this->minScore : $GLOBALS['course']->minScore );

		// Find out if the user passed
		if ( $score >= $minScore )
		{
			$next = $this->nextButton ? $this->nextButton : '[NEXT]';

			// Issue an updated token if passed with a higher score
			$token = new Token('module_test',$this->module_id,COURSENAME);
			if ( $GLOBALS['user']->enrollment_token()->has($token) )
			{
				if ( $GLOBALS['user']->enrollment_token()->value($token) < $score )
					$GLOBALS['user']->enrollment_token()->set_value($token,$score);
			}
			else {
				$GLOBALS['user']->enrollment_token()->issue($token,$score);
			}
			
			// Check to see if this certifies the user
			if ( $GLOBALS['course']->certify_token && $token->equals($GLOBALS['course']->certify_token) )
				$next = '<P STYLE="font-size: 18pt; color: red; font-weight: bold; text-align: center;">'.say('Get Certificate Required').'</P><P STYLE="text-align: center;"><A HREF="javascript:pager.unpaged_show(\'certificate/\'); pager.next();" STYLE="font-size: 24pt;">'.say('Get Certificate').'</A></P><SCRIPT>pager.update_utility()</SCRIPT>';

			$passed = ( !empty($this->passed[$lang]) ? lang($this->passed) 
				: say( "Congratulations! You have passed this test!" ) );

			$out .= "<P CLASS=controlPanel>" . $passed . "</P>";
			$out .= "<P ALIGN=CENTER STYLE='margin-top: 
				10px'>{$next}</P>";
		}
		else
		{
			// Disallow previous and next buttons
			$this->allowPrevAndNext = 0;
				
			$failed = ( !empty($this->failed[$lang]) ? lang($this->failed)
			       	: say( "The minimum score to pass this test is" ) . " {$minScore}%." );

			$out .= "<P CLASS=error>" . $failed . "</P>";
		}

		// Log the test attempt
		$ci->db->insert('test_attempts',array(
			'attempt_id' => $this->attempt_id,
			'enrollment_id' => $GLOBALS['user']->enrollment_id(),
			'module_id' => $this->module_id,
			'test_hash' => $this->get_hash(),
			'score' => $score,
			'html' => $out,
			'lang' => $lang,
			'post' => store($_POST),
		));
	
		// Create Try Again block (if they can try again...)
		if ( $score < $minScore 
			&& !( $this->maxAttempts 
				&& count( $GLOBALS['user']->count_test_attempts($this->module_id) >= $this->maxAttempts ) )
		) {
			$button = $ci->sayings->input('Try again','TYPE=BUTTON ONCLICK="pager.go(\''.$this->name.'\',0)"');
			$out .= "<P ALIGN=CENTER>{$button}</P>";
			if ( $this->skillTitle )
			{
				$out .= '<P ALIGN=CENTER><A HREF="javascript:pager.go(\'' . $this->skill 
						. '\',0)">' . say( 'Return to' ) . ' ' . $this->skillTitle[0]['title'] . '.</A></P>';
			}
		}
			
		if ( $this->email )
		{
			require_once(BASEPATH.'application/libraries/PHPMailer_v5.1/class.phpmailer.php');
			$mail = new PHPMailer();

			$mail->IsSMTP();
			$mail->Host = 'mailgate.nau.edu';

			$mail->From = $this->email;
			$mail->FromName = $GLOBALS['course']->displayName;
			$mail->AddAddress($this->email);

			$mail->Subject = $GLOBALS['course']->displayName . ' - test results - ' 
				. $this->title['EN'] . ' - ' . $GLOBALS['user']->realName;
			$mail->Body =  "<STYLE TYPE='text/css'>tr.right{background-color: #E0FFE0; 
				border-color: #80FF80;} tr.wrong{background-color: #FFFF80; 
				border-color: #FF8080;} .error{background-color: #FF8888; 
				border: 1px solid #FF0000; padding: 1em;}</STYLE><P>Name:" . 
				$GLOBALS['user']->realName . '</P>' . $out;

			$mail->isHTML(TRUE);
			$mail->Send();
		}
			
		return $out;
	}

	function read_POST()
	{
		$ci =& get_instance();

		$this->languages = array();
		foreach ( array_values($ci->input->post('languages')) as $lang )
		{
			if ( isset($GLOBALS['iso639'][$lang]) )
				$this->languages[] = $lang;
		}

		foreach ( $this->localized_settings as $name ) {
			$this->{$name} = $ci->input->post($name);
		}

		foreach ( $this->cdata_settings as $name ) {
			$this->{$name} = $ci->input->post($name);
		}

		foreach ( $this->boolean_settings as $name ) {
			$this->{$name} = $ci->input->post($name) ? TRUE : FALSE;
		}

		$this->questions = array();
		foreach ( $ci->input->post('questions') as $question_array )
		{
			$question = new Question($this);
			$question->load_array($question_array);
			$this->questions[$question->hash] = $question;
		}
		
		return TRUE;
	}

    function XML_path()
    {
        return BASEPATH.'application/resources/'.$this->courseName.'/tests/'.$this->name.'.xml';
    }

	function write_XML()
	{
		$file = $this->XML_path();
		if ( ! is_writable($file) )
			if ( file_exists($file) || ! is_writable(dirname($file)) )
				fatal("File {$file} is not writable.");

		$fp = fopen($file,'w');
		fwrite($fp,$this->get_XML());
		fclose($fp);
	}

	function read_XML()
	{
		$file = $this->XML_path();
		if ( ! file_exists($file) ) {
			fatal("File {$file} does not exist.");
		}

		$result = $this->load_XML(file_get_contents($file));

		if ( ! $result )
			fatal('Error parsing file "'.$file.'" for test "'.$this->name.'".');
	}

	function get_XML()
	{	
		debug_log('Creating Test XML');
		//var_dump($this->languages);
		$x = new XMLWriter(); 
		$x->openMemory();
		$x->setIndent(true);
		
		$x->startDocument('1.0');

		$x->startElement('test');

		$x->startElement('languages');
		foreach ( $this->languages as $lang )
		{
			$x->writeElement('lang',$lang);
		}
		$x->endElement();

		foreach ( $this->localized_settings as $name ) {
			foreach ( $this->languages as $lang ) {
				if ( ! isset($this->{$name}[$lang]) )
					continue;
				$x->startElement($name);
				$x->writeAttribute('lang',$lang);
				$x->text($this->{$name}[$lang]);
				$x->endElement();
			}
		}

		foreach ( $this->cdata_settings as $name ) {
			$x->writeElement($name,$this->$name);
		}

		foreach ( $this->boolean_settings as $name ) {
			$x->writeElement($name,( $this->$name ? 'TRUE' : 'FALSE' ));
		}

		$x->startElement('questions');
		$n = 1;
		foreach ( $this->questions as $question )
		{
			$question->number = $n++;

			$x->writeRaw($question->get_XML());
		}
		$x->endElement();

		$x->endElement();

		debug_log('Finished Creating Test XML');
		return $x->outputMemory();
	}

	function load_XML($xml)
	{
		$x = simplexml_load_string($xml);
		//var_dump($x);
		
		foreach ( $x->xpath('/test/languages/lang') as $lang )	{
			$this->languages[] = (string) $lang;
		}

		foreach ( $this->localized_settings as $name ) {
			$nodes = $x->xpath('/test/'.$name);
			if ( is_array($nodes) )
			foreach ($nodes as $node) {
				$this->{$name}[(string) $node['lang']] = (string) $node;
			}
		}

		foreach ( $this->cdata_settings as $name ) {
			$nodes = $x->xpath('/test/'.$name);
			if ( isset($nodes[0]) )
				$this->$name = (string) $nodes[0];
		}

		foreach ( $this->boolean_settings as $name ) {
			$nodes = $x->xpath('/test/'.$name);
			if ( isset($nodes[0]) )
				$this->$name = ( strtoupper((string) $nodes[0]) == 'TRUE' ? TRUE : FALSE );
		}

		foreach ( $x->xpath('/test/questions/question') as $node )
		{
			$question = new Question($this);
			$question->load_SimpleXMLObject($node);
			$this->questions[$question->hash] = $question;
		}
		
		return TRUE;
	}

	function load_last_results()
	{
		$ci =& get_instance();

		// Get last attempt row
		$last_attempt = $GLOBALS['user']->get_last_test_attempt($this->module_id);

		if ( $last_attempt === NULL )
			return;

		$this->last_results = array(
			'attempt_id' => $last_attempt['attempt_id'],
			'score' => $last_attempt['score'],
		);

		// Get all of the question attempts associated with this test attempt
		$result = $ci->db
			->from('question_attempts')
			->where('attempt_id',$last_attempt['attempt_id'])
			->get()
			->result_array();

		foreach ( $result as $row )
		{
			// The current test may not have questions contained in the last attempt
			if ( ! isset($this->questions[$row['question_hash']]) )
				continue;

			$this->questions[$row['question_hash']]->load_last_attempt($row);
		}
	}

	/**
	 * Returns an SHA1 hash unique to the set of questions in this test
	 */
	function get_hash()
	{
		$questions = array();
		foreach ( $this->questions as $q ) {
			$questions[] = $q->hash;
		}

		return sha1(serialize($questions));
	}
}
?>
