playingFlash = new Object();

function playFlash(id)
{
	$J('#stop_'+id).show();
	$J('#'+id+' TABLE').hide();
	$J('#'+id+' SPAN.flash').html(flash[id]);
	playingFlash[id] = true;
	
}

function stopFlash(id)
{
	$J('#stop_'+id).hide();
	$J('#'+id+' TABLE').show();
	$J('#'+id+' SPAN.flash').html('');
	playingFlash[id] = false;
}

function stopAllFlash()
{
	$J.each(playingFlash, function (k,v) {
		if ( v ) {
			stopFlash(k);
		}
	});
}

