function Language()
{
	this.show = function(lang)
	{
		// Ensure lang is a valid language for the course
		if ( $J("#language_toggle OPTION[VALUE="+lang+"]").length == 0 )
			lang = $J("#language_toggle OPTION:first-child").val();

		stopAllFlash();
		$J('.lang').hide();
		$J('.lang_'+lang).show();
		pager.nav_reset_selector();
		pager.page_scroll_set();
		this.current = lang;
		$J.cookie('lang',lang,{path:'/',expires:1});
		$J('#language_toggle').val(lang);
	}

	this.refresh = function()
	{
		this.show(this.current);
	}

	this.init = function ()
	{
		lang = $J.cookie('lang');
		if ( lang == null )
			lang = 'EN';
		this.show(lang);
		$J('#language_toggle').change(function()
		{
			language.show($J(this).val());
		});
	}

	this.init();
}
