<?
//////////////////////////////////////////////////////////////////////
//
//	store.php
//	Jason Karcz
//	Makes data storable into a NySQL Database
//
//////////////////////////////////////////////////////////////////////
//
//	27 February 2004 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading store.php');
function store( $data )
{
	return base64_encode( gzcompress( serialize( $data ) ) );
}

function unstore( $data )
{
	if ( $data != "" )
	{
		return unserialize( gzuncompress( base64_decode( $data ) ) );
	}
}
?>
