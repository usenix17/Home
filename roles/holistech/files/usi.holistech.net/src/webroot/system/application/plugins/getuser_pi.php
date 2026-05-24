<?
//////////////////////////////////////////////////////////////////////
//
//      getUser.php
//      Jason Karcz
//
//////////////////////////////////////////////////////////////////////
//
//      10 Dec 2009 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading getUser.php');

// Initialize module cache
$GLOBALS['userCache'] = array();

// Any users that are retrieved with this function with be automatically saved at the end via the saveUsers hook
// This can be prevented by passing FALSE to $cache_user.
function &getUser( $key_type, $key, $cache_user=TRUE )
{
	debug_log("getUser($key_type, $key)");

	if ( empty($key) )
	{
		$user = new User();
		return $user;
	}

	if ( $key_type != 'user_id' && $key_type != 'email' && $key_type != 'username' )
		fatal('Unknown user key type: '.$key_type);

	if ( $key_type == 'user_id' )
		$key = (int) $key;

	if ( !$cache_user || !isset($GLOBALS['userCache'][$key_type][strtolower($key)]) )
	{
		// Get the user row to feed to User()
		$ci =& get_instance();
		$result = $ci->db
			->from('users')
			->where($key_type,$key)
			->get()
			->result_array();

		if ( count($result) == 0 ) {
			$user = new User();
			$user->$key_type = $key;
			return $user;
		}
		elseif ( count($result) > 1 ) {
			fatal('Too many users found.');
		}

		$row = $result[0];	
		$user = new User($row);

        if ( $cache_user == FALSE ) 
            return $user;

		debug_log("Writing user to cache");
		$GLOBALS['userCache']['user_id'][$user->user_id] =& $user;
		if ( ! empty($user->username) )
			$GLOBALS['userCache']['username'][strtolower($user->username)] =& $user;
		if ( ! empty($user->email) )
			$GLOBALS['userCache']['email'][strtolower($user->email)] =& $user;
	}

	return $GLOBALS['userCache'][$key_type][strtolower($key)];
}
