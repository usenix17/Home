function load(url,target,data,fn,throbber)
{
    //console.log('load',url,target);
	$J('#temp').html(url);
	if ( throbber == null || throbber == true )
		$J(target).html('<IMG SRC="'+BASEURL+'../images/ajax-loader.gif">');
	$J.ajax({
		type:		"POST",
		url:		BASEURL+url,
		data:		data,
		success:	function(msg) {
					if ( msg.substring(0,7) == '<ERROR>' )
					{
						$J('IMG.loader').hide();
						$J('#HIDDEN').append(msg);
					}
					else
					{
						$J(target).html(msg);
						language.refresh();
						//body_resize();
						//var children = $J(target).children('DIV.ui-tabs');
						//$J(children[0]).css('height', $J(target).innerHeight());
						//$J(children[0]).css('width', $J(target).innerWidth());
						//$J('#MAIN').css('height', $J('body').innerHeight());
						if ( fn != null )
						{
							fn();
						}
					}
				},
		error:		function(XMLHttpRequest, textStatus, errorThrown) {
					$J(target).html(XMLHttpRequest.status+" "+XMLHttpRequest.statusText+": "+url);
					//console.log(XMLHttpRequest);
				}
	});
}

$J().ajaxComplete(function(ev,xhr,ajaxOptions) {
			process_error_headers(xhr);
		});
