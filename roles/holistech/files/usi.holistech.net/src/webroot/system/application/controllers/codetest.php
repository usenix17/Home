<?php

/**
 * Code Testing Class
 */
class CodeTest extends Controller
{

	/**
	 * Code testing function
	 */
	function code()
	{
		$result = $this->db
			->select('name')
			->from('courses')
			->where('link_to','flatbread')
			->get()
			->result_array();

		$courses = array();
		foreach ( $result as $r ) {
			$course = getCourse($r['name']);
			if ( is_array($course->syllabus) )
			foreach ( $course->syllabus as $s ) 
				if ( $s['type'] == 'test' 
				&& $s['module'] != 'menu_ne_1' && $s['module'] != 'menu_paia_1' )
					$courses[$r['name']][] = $s['module'];
		}

		//var_dump($courses);

		$result = $this->db
			->select("username,realName,classification,email,employer,years,current,data")
			->from('_flatbread_users')
			->get()
			->result_array();

		foreach ( $result as $r )
		{
			$data = unstore($r['data']);
			unset($r['data']);
			print "<H2>{$r['realName']}</H2><UL>";

			foreach ( $courses as $c => $s ) {
				//print "<H2>$c</H2>";
				$passed = TRUE;
				foreach ( $s as $m ) {
					/*print "-----$m: ";
					if ( isset($data['test'][$m]) && is_array($data['test'][$m]['attempts']) ) {
						foreach ( $data['test'][$m]['attempts'] as $a ) {
							print $a['score'].' ';
						}
						if ( isset($data['test'][$m]['highscore']) )
							print "HIGH: ".$data['test'][$m]['highscore'];
					}
					print "<BR>";*/
					if ( !$passed || !isset($data['test'][$m]) )
					{
						$passed = FALSE;
						continue;
					}
					if ( !isset($data['test'][$m]['highscore']) || $data['test'][$m]['highscore'] != 100 )
						$passed = FALSE;
				}
				if ( $passed )
					print "<LI>$c</LI>";
			}
			print "</UL>";
			/*foreach ( $data['test'] as $testName => $t ) {
				if ( $t['attempts'] === null )
					continue;
				print "<H2>{$testName}</H2><P>Highscore: {$t['highscore']}</P><P>Attempts:<BR>";
				foreach ( $t['attempts'] as $i => $a )
					print "{$i}: {$a['score']}<BR>";
				print "</P>";
			}*/
		}
	}

	function code2()
	{
		$out['plain'] = 'password123';
		$out['encoded'] = $this->encode_pass($out['plain']);
		$out['check'] = $this->check_pass($out['plain'],$out['encoded']);
		$out['check wrong'] = $this->check_pass('wrong pass',$out['encoded']);
		$out['salt'] = md5(uniqid(rand(),true));
		var_dump($out);
	}

	function encode_pass($pass,$salt=NULL)
	{
		$i = 5000;
		if ( $salt === NULL )
			$salt = substr(md5(uniqid(rand(),TRUE)),0,32);

		while ($i--) {
			$pass = $salt.hash('sha512',$salt.$pass);
		}

		return $pass;
	}

	function check_pass($pass,$hash)
	{
		$check = $this->encode_pass($pass,substr($hash,0,32));
		return $check == $hash;
	}


	function import_syllabi()
	{
		$this->db->query('TRUNCATE modules');
		$this->db->query('TRUNCATE syllabi');

		// Get old modulesets
		$moduleSets = $this->db
			->from('_moduleSets')
			->order_by('clientName,name,position')
			->get()
			->result_array();

		// Import each line
		foreach ( $moduleSets as $m ) {
			$course = $m['clientName'];
			if ( ! in_array($course,array('food','flatbread','pinal','backcountry','studentsfirst','coconino_wia','sanitarian','enes','windmill','cocopah','custserv','food2')) )
				continue;
			if ( $course == 'enes' && $m['name'] != 'enes' ) continue;
			if ( $m['name'] == 'stressDemo' ) continue;
			if ( $m['module'] == 'header' ) continue;
			$orig_course = $course;
			if ( $course == 'flatbread' )
				$course .= '_'.$m['name'];

			// Locate or create the module
			$result = $this->db
				->from('modules')
				->where('course',$orig_course)
				->where('module',$m['module'])
				->get()
				->result_array();

			$module_id = NULL;
			if ( count($result) != 1 ) {
				$this->db->insert('modules',array(
					'course' => $orig_course,
					'module' => $m['module'],
					'type' => $m['type'],
				));
				$module_id = $this->db->insert_id();
			}
			else
				$module_id = $result[0]['id'];

			// Add line in syllabus
			$this->db->insert('syllabi',array(
				'course' => $course,
				'module_id' => $module_id,
				'position' => $m['position'],
				'availability' => $m['availability'],
				'after' => $m['after'],
				'number' => $m['number'],
			));

			print "Processed {$course} {$m['module']}<BR>";
			flush();
		}

		// Update "after" column to contain module ids
		$this->db->query("
update syllabi s
left join modules m on s.after=m.module and s.course=m.course
set s.after=m.id
where s.after != ''");
	}

	// Alter the constructor to read from the DB before running!
	function import_tests()
	{
		$this->load->helper('test');
		$result = $this->db
			->from('modules')
			->where('type','test')
			->get()
			->result_array();

		foreach ( $result as $r ) {
			var_dump($r);
			$test = new Test($r['id']);
			$test->__destruct();
			//unset($test);
		}
	}

	// Alter the constructor to read from the DB before running!
	function import_skills()
	{
		$this->errors->echo = TRUE;
		$this->load->helper('skill');
		$result = $this->db
			->from('modules')
			->where('type','skill')
			->get()
			->result_array();

		foreach ( $result as $r ) {
			var_dump($r);
			$skill = new Skill($r['id']);
			//$skill->__destruct();
			unset($skill);
		}
	}

	// Alter the constructor to read from the DB before running!
	function transfer_syllabi()
	{
		$this->errors->echo = TRUE;
		$this->load->helper('course');
		$result = $this->db
			->from('courses')
			->get()
			->result_array();

		foreach ( $result as $r ) {
			var_dump($r['name']);
			$course = new Course(strtolower($r['name']));
			//$skill->__destruct();
			unset($course);
		}
	}
}
		
/* End of file codetest.php */		
