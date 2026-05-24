<?
//////////////////////////////////////////////////////////////////////
//
//      codes.php
//      Jason Karcz
//      Manages login codes
//
//////////////////////////////////////////////////////////////////////
//
//      22 March 2004 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading codes.php');

class Codes
{
	// Instance Variables
	
	// Constructor
	function Codes()
	{
	}

	// Creates login codes
	static function create( $quantity, $label = '', $purchase_id = '', $coursename = '' )
	{
		if ( !$purchase_id )
			$purchase_id = time() . ':' . $_SERVER['REMOTE_ADDR'];
			
		for ( $i = 0; $i < $quantity; $i++ )
		{
			Codes::put( $label, $purchase_id, $coursename );
		}
		
		return Codes::list_codes( 'c.purchase_id', $purchase_id );
	}

	// Put a code in 
	static function put( $label, $purchase_id, $coursename = '' )
	{
		$ci =& get_instance();

		if ( empty($coursename) )
			$coursename = COURSENAME;

		if ( ! $GLOBALS['user']->has(new Token('auth','create_codes',$coursename)) )
			fatal('You do not have permission to create codes.');

		$ci->db->insert('codes',array(
			'course' => $coursename,
			'label' => $label,
			'purchase_id' => $purchase_id,
			'creator_user_id' => $GLOBALS['user']->user_id,
		));

		return $ci->db->insert_id();
	}

	// Uses a registration code on a user
	static function apply( $code, &$user )
	{
		debug_log("Codes::use($code)");

	/* Validate the code */
		$row = Codes::validate( $code );
		if ( $row === FALSE )
			return FALSE;

	/* Enroll the user */
		if ( $user->enroll($row['course'],$row['code']) )
			warn("Using registration code: '{$code}'.");
		else {
			warn("The registration code: \"{$code}\" was NOT used and is still valid.");
			return FALSE;
		}

	/* Update the DB to reflect the code was used and by whom */
		$ci =& get_instance();
		$ci->db
			->set('user_id',$user->user_id)
			->set('time_used','now()',FALSE)
			->set('status','Used')
			->where('code',$row['code'])
			->update('codes');

		return TRUE;
	}

	// Get an array of available codes
	static function available_codes()
	{
		return Codes::list_codes(NULL,NULL,TRUE);
	}

	static function list_codes( $search_field=NULL, $search_key=NULL, $available=FALSE )
	{
	/* Search the DB */
		$ci =& get_instance();
		$ci->db
			->select('c.*, u.realName as created_by, u2.email as used_by_email, 
				u2.username as used_by_username, u2.realName as used_by_realName')
			->from('codes c')
			->join('users u','u.user_id=c.creator_user_id','left')
			->join('users u2','u2.user_id=c.user_id','left')
			->join('purchases p','p.purchase_id=c.purchase_id','left');

		if ( $available )
			$ci->db->where('c.user_id',NULL);

		if ( $search_field !== NULL && $search_key !== NULL )
			$ci->db->where($search_field,$search_key);
		else
			$ci->db->where('c.course',COURSENAME);

		$result = $ci->db
			->order_by('c.code')
			->get()
			->result_array();

	/* Change serial numbers to codes */
		foreach( $result as &$row )
		{
			$row['serial'] = $row['code'];
			$row['code'] = Codes::generate($row['code']);
		}

		return $result;
	}

	// Validates that a code is ready to use, returns database row
	static function validate( $code, $validate_course = FALSE )
	{
		$serial = Codes::verify_checksum( $code );
		
		if ( !$serial )
		{
			if ( ! preg_match('/^\d{10}$/',$code) )
				error( "You have entered an invalid code: \"{$code}\"  Registration codes are 10-digit numbers only." );
			else
				error( "You have entered an invalid code: \"{$code}\"." );
			return false;
		}
			
		$row = $GLOBALS['db']->get_row( 'codes', 'code', $serial );

		if ( $validate_course && $row['course'] != COURSENAME )
		{
			error( "You have entered a code for a different training course." );
			return false;
		}
			
		if ( $row['user_id'] )
		{
			error( "You have entered a code that has already been used.  Registration codes are only used to register for the training.  You will need to log in using your username and password." );
			return false;
		}
			
		return $row;
	}
	
	// Generates a code given a serial number
	static function generate( $x )
	{
		return sprintf ( "%05d%05d", $x, abs( crc32( $x ) % 100000 ) );
	}

	static function generate32( $x )
	{
		$crc = crc32($x);

		if($crc & 0x80000000){
		    $crc ^= 0xffffffff;
		    $crc += 1;
		    $crc = -$crc;
		}

		return sprintf ( "%05d%05d", $x, abs( $crc % 100000 ) );
	}
	
	// Generates a serial number given a code (or false if the checksum does not match)
	static function verify_checksum( $code )
	{
		$code = preg_replace( "/\D/", '', $code );
		$code = sprintf( "%010d", $code );
		
		$x = intval( substr( $code, 0, 5 ) ); 

		if ( $code == Codes::generate( $x ) 
	        ||   $code == Codes::generate32( $x ) )
		{
			return $x;
		}
		else
		{
			return false;
		}
	}
	
	static function refund( $code )
	{
		if ( !isset($GLOBALS['user']) )
			fatal('Global user must be defined to refund codes.');

		$ci =& get_instance();

		$ci->db
			->where('code',$code)
			->set('status','Refunded')
			->set('refunded_by',$GLOBALS['user']->user_id)
			->set('refunded_on','now()',FALSE)
			->update('codes');
		$ci->db
			->where('code',$code)
			->set('status','Refunded')
			->update('enrollments');

	}
}
?>
