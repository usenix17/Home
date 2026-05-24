<?
function saveUsers()
{
	if ( isset($GLOBALS['userCache']['user_id']) )
	foreach ( $GLOBALS['userCache']['user_id'] as &$user )
	{
		debug_log("Saving {$user->username} via saveUsers");
		$user->update_row();
	}
}
