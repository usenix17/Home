<?
//////////////////////////////////////////////////////////////////////
//
//	token_helper
//	Jason Karcz
//	Manages permission tokens
//
//////////////////////////////////////////////////////////////////////

class Token
{
	var $type;
	var $scope;
	var $key;

	var $ci;
	var $has = array();	// Array of tokens and their associated values: {(token,value),(token,value)...}

	function Token( $type, $key, $scope=NULL )
	{
		$this->type = $type;
		$this->key = $key;
		$this->scope = $scope;
        $this->xml = FALSE;

		$this->ci =& get_instance();

        if ( file_exists($GLOBALS['tokens_xml_file']) ) {
            $xml_text = file_get_contents($GLOBALS['tokens_xml_file']);
            $this->xml=new SimpleXMLElement($xml_text);
        }
	}

	function has($token)
	{
	/* Everyone has the NULL token */
		if ( $token === NULL )
			return TRUE;

	/* Wildcarded tokens cannot have anything, or even try to have anything.  Generate a fatal error */
		if ( $this->type == '*' || $this->key == '*' || $this->scope == '*' )
			fatal('Wildcarded token tested for "has".');

	/* Get the records from the database if that hasn't been done yet */
		if ( empty($this->has) )
			$this->_get_has();

		foreach ( $this->has as $h )
			if ( $token->equals($h['token']) )
				return TRUE;

		return FALSE;
	}

	function issue($token,$value=NULL)
	{
	/* Don't reissue a token */
		if ( $this->has($token) )
			return;

	/* Add the relationship to the database */
		$this->ci->db->insert('tokens',array(
			'subject_type' => $this->type,
			'subject_scope' => $this->scope,
			'subject_key' => $this->key,
			'predicate_type' => $token->type,
			'predicate_scope' => $token->scope,
			'predicate_key' => $token->key,
			'value' => $value
		));

	/* Reset has array */
		$this->has = array();
	}

	function revoke($token)
	{
	/* Don't try to revoke something that's not there */
		if ( ! $this->has($token) )
			return;

	/* Remove the relationship from the database */
		$this->ci->db->delete('tokens',array(
			'subject_type' => $this->type,
			'subject_scope' => $this->scope,
			'subject_key' => $this->key,
			'predicate_type' => $token->type,
			'predicate_scope' => $token->scope,
			'predicate_key' => $token->key
		));

	/* Reset has array */
		$this->has = array();
	}

	function set_value($token,$value)
	{
	/* Update the value in the database */
		$this->ci->db
			->where(array(
				'subject_type' => $this->type,
				'subject_scope' => $this->scope,
				'subject_key' => $this->key,
				'predicate_type' => $token->type,
				'predicate_scope' => $token->scope,
				'predicate_key' => $token->key
			))
			->set('value',$value)
			->update('tokens');

	/* Reset has array */
		$this->has = array();
	}

	function value($token)
	{
	/* NULL tokens always have NULL values */
		if ( $token === NULL )
			return NULL;

		if ( empty($this->has) )
			$this->_get_has();

		foreach ( $this->has as $h )
			if ( $token->equals($h['token']) )
				return $h['value'];

		return FALSE;
	}

	// Determines whether the passed token is equivalent to $this.  
	// This function considers "*" as a wildcard that will match anything
	function equals($token)
	{
		return 
			( $this->type == $token->type || $this->type == '*' || $token->type == '*' ) && 
			( $this->scope == $token->scope || $this->scope == '*' || $token->scope == '*' ) && 
			( $this->key == $token->key || $this->key == '*' || $token->key == '*' );
	}

	// This function will determine all tokens, groups, and subsequent tokens $this has if passed nothing
	private function _get_has($token=NULL)
	{
	/* Get has for this token by default */
		if ( $token === NULL )
			$token = $this;

	/* Search the database for what $token has */
		$this->ci->db
			->from('tokens')
			->where('subject_type',$token->type)
			->where('subject_key',$token->key);

		// Don't use scope on groups
		if ( $token->type != 'group' )
			$this->ci->db->where('subject_scope',$token->scope);

		$has = $this->ci->db
			->get()
			->result_array();

    /* Search the XML tokens file as well */
        if ( $this->xml !== FALSE )
            $has = array_merge($has, $this->_get_tokens_from_xml($token->type,$token->key));

	/* Iterate through each result and add it to the list */
		foreach ( $has as $t )
		{
			// If the token we're looking at is a group, all sub-tokens need to have the same scope
			// unless that scope is NULL, then we use the scope from the child tokens.
			$scope = $t['predicate_scope'];
			if ( $token->type == 'group' && $token->scope !== NULL )
				$scope = $token->scope;

			$x = new Token($t['predicate_type'],$t['predicate_key'],$scope);
			$this->has[] = array(
				'token' => $x,
				'value' => $t['value']
			);

			// Search recursively if we've found a group, but not if it's a group in a group:
			// I decided for simplicity to only set up one level of recursion.
			if ( $x->type == 'group' && $token->type != 'group' )
				$this->_get_has($x);
		}
	}

    function _get_tokens_from_xml($subject_type, $subject_key) {
        $tokens = Array();
        $xpaths = Array( "/tokens/{$subject_type}[@key=\"{$subject_key}\"]" );
        if ( $subject_type == 'user' ) { // Search by user name if we're looking for user tokens
            $username_array = $this->ci->db->from('users')->where('user_id',$subject_key)->get()->result_array();
            $username = $username_array[0]['username'];
            $xpaths[] = "/tokens/{$subject_type}[@name=\"{$username}\"]";
        }

        foreach ( $xpaths as $xpath) {
            foreach ( $this->xml->xpath($xpath) as $subject_token ) {
                foreach( $subject_token->children() as $predicate_token ) {
                    $token = Array();
                    $subject_attrs = $subject_token->attributes();
                    $attrs = $predicate_token->attributes();

                    $token['subject_type'] = $subject_token->getName();
                    $token['subject_key'] = (string) $subject_attrs['key'];
                    $token['predicate_type']  = $predicate_token->getName();
                    $token['predicate_key']   = $attrs['key']   == NULL ? NULL : (string) $attrs['key'];
                    $token['predicate_scope'] = $attrs['scope'] == NULL ? NULL : (string) $attrs['scope'];
                    $token['value']           = $attrs['value'] == NULL ? NULL : (string) $attrs['value'];
                    $tokens[] = $token;
                }
            }
        }
        return $tokens;
    }

	function to_string()
	{
		return "{{$this->type},{$this->key},".($this->scope===NULL?'NULL':$this->scope)."}";
	}

	function dump()
	{
		if ( empty($this->has) )
			$this->_get_has();

		print "<PRE>".$this->to_string()." has:\n\n";

		foreach ( $this->has as $h )
		{
			print $h['token']->to_string()."({$h['value']})\n";
		}
	}
}
