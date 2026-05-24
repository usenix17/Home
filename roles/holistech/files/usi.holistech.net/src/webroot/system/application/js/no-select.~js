(function($)
{
	$.fn.noSelect = function() 
	{
		return this.each( function()
		{
			if ( $.browser.mozilla )
			{
				$(this).css('MozUserSelect','none');
			}
			else if ( $.browser.msie )
			{
				$(this).bind('selectstart', function() 
				{
					return false;
				});
			}
			else
			{
				$(this).mousedown( function()
				{
					return false;
				});
			}
		});
	}
})(jQuery);

