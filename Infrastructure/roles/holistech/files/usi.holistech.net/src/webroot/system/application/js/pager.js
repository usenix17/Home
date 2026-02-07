// Terminology:
// 	module = the 0-indexed number of a module
// 	page = page number
// 	api = jQuery Tools Scrollable Object

function Pager()
{
	this.modules = null;
	this.dom = Object();
	this.dom.navigation = $J('#navigation');
	this.dom.navwrapper = $J('#navwrapper');
	this.dom.nav_up = $J('#navwrapper .up');
	this.dom.nav_down = $J('#navwrapper .down');
	this.dom.selector = $J('#selector');
	this.dom.content = $J('#content');
	this.dom.modules = $J('#modules');
	this.dom.scrollbar = $J('#scrollbar');
	this.dom.current_page = undefined;
	this.dom.api = null;
	this.page_scroll_amount = 0;
	this.nav_scroll_amount = 0;
	this.nav_current_scroll = 100;
	this.unpaged = false;
	this.unpaged_path = '';
	this.first_module = 0;
	this.first_page = 0;
	this.error_state = 0;
	this.current_module = 0;
	this.current_page = 0;

	this.forbidden = function(moduleName,page)
	{
		module = this.get_index(moduleName);

		this.modules[module].pages[page].loaded = false;
	}

	this.get_current_module_name = function()
	{
		return this.modules[this.current_module].moduleName;
	}

	this.get_current_module_id = function()
	{
		return this.modules[this.current_module].module_id;
	}

	// Returns the index of a given moduleName
	this.get_index = function(moduleName)
	{
		for ( i=0; i<this.modules.num_modules; i++ )
			if ( moduleName == this.modules[i].moduleName )
				return i;
	}

	// Returns the index of a given moduleName
	this.get_index_from_module_id = function(module_id)
	{
		for ( i=0; i<this.modules.num_modules; i++ )
			if ( module_id == this.modules[i].module_id )
				return i;
	}


	// Returns the name of the next module
	this.get_next_module = function()
	{
		next = parseInt(this.current_module) + 1;

		// Return NULL if current is last
		if ( next == this.modules.num_modules )
			return undefined;

		return next;
	}

	// Returns the name of the previous module
	this.get_prev_module = function()
	{
		// Return NULL if current is last
		if ( this.current_module == 0 )
			return undefined;

		return parseInt(this.current_module) - 1;
	}

	this.go = function(module,page,speed)
	{
        //console.log('pager.go(',module,page,speed,')');
		// Find out if a module index or name was passed
		if ( this.modules[module] == undefined )
			module = this.get_index(module);

		pager = this;

		// Do nothing if not told to go anywhere
		if ( module === undefined )
			return;

		// Default page to 0
		if ( page === undefined )
			page = 0;

		// Remove mousewheel support from the current page
		//if ( this.dom.current_page !== undefined )
		//	this.dom.current_page.unmousewheel();

		// Stop all playing flash animations
		stopAllFlash();

		// Set new page as current in DOM access variables and enable mousewheel support
		this.dom.current_page = $J('.module[MODULE='+module+'] .page[PAGE='+page+']', this.dom.modules);

		// Set the page progress
		this.modules[module].navi.slider('value',page);

		// Ensure the page is loaded
		this.load(module,page,function () {
			//pager.resize();
		});
	
		// Fly to the correct module
		if ( module != this.current_module )
		{
			module = parseInt(module);
			this.dom.api.seekTo(module);
			this.current_module = module;
			this.nav_show_selector(module);
		}
		
		// Fly to the correct page
		if ( module == this.current_module ) //&& page != this.current_page )
		{
			if ( speed !== undefined )
				this.modules[module].api.seekTo(page,speed);
			else
				this.modules[module].api.seekTo(page);
			this.current_page = page;
		}


		// Preload adjacent pages
		this.load_near_current();

		// If this is the last page of a skill, tell the server we're here
		if ( this.modules[module].type == 'skill' && parseInt(this.current_page) == this.modules[module].num_pages - 1 )
		{
			load('skills/finish/'+this.modules[module].module_id,'#HIDDEN');
		}

		// activate the scrollbar if we need it
		this.dom.current_page.scroll(pager.page_scroll_set);

		// Save the session in case of logout
		load('/pager/saveSession/'+module+'/'+page,'#HIDDEN');
	}

	this.init = function()
	{
		// Localize "this" for use out of scop
		pager=this;

		// Run module-specific initialization
		if ( this.modules != undefined )
			this.init_modules();

		// Set the main scrollable
		this.dom.content.scrollable({
			vertical: true,
			size: 1,
			clickable: false,
			nextPage: 'dummy',
			prevPage: 'dummy2',
			keyboard: false,
		});

		this.dom.api = this.dom.content.scrollable({api: true});

		// Set up the scrollbar
		this.dom.scrollbar.slider({
			orientation: 'vertical',
			range:	"min",
			min: 0,
			max: 100,
			value: 100,
			slide: function(e,ui)
				{
					pager.page_scroll_to(ui.value);
				}
		});

		// Set up the layout toggle
		$J('#layout_toggle').change(function()
		{
			load('/layout/set_layout/'+$J(this).val(),'#HIDDEN');
		});

		// Clear tabbing from field to field and attach resizing
		$J('BODY').keydown(catchKeys);
		$J(window).resize(function() {pager.resize()});

		// Set things right from IE7-proofing
		$J('a.arrows, #nav_bg').css('display','block');
		
		//this.update_utility();
        
        // Enable arrow key navigation
        var pager = this;
        $J(document).keydown(function(e){
            if (e.keyCode == 37) { 
                // left
                pager.prev()
            }
            if (e.keyCode == 38) { 
                // up
                var scrollTop = pager.dom.current_page.attr('scrollTop');
                pager.dom.current_page.attr('scrollTop',scrollTop-100);
            }
            if (e.keyCode == 39) { 
                // right
                pager.next()
            }
            if (e.keyCode == 40) { 
                // down
                var scrollTop = pager.dom.current_page.attr('scrollTop');
                pager.dom.current_page.attr('scrollTop',scrollTop+100);
            }
        });
	}

	this.init_load = function()
	{
		// After loading the first page, hide the unpaged view
		this.go(this.first_module,this.first_page);
		this.unpaged_hide();

		//for ( i=1; i<this.modules.num_modules; i++ )
		//{
		//	this.load(i,0);
		//}

		this.load_near_current();		
	}

	this.init_modules = function(first_module)
	{
		for ( i=0; i<this.modules.num_modules; i++ )
		{
			// Add the module to the navigation
			this.dom.navigation.append("<LI MODULE='"+i+"'>"+this.modules[i].name+"</LI><HR>");


			// Create the module containers
			this.dom.modules.append("<DIV CLASS='module content' MODULE="+i+"><DIV CLASS=navi></DIV><DIV CLASS=scrollable><DIV CLASS=pages></DIV></DIV></DIV>");

			pages = $J('.module[MODULE='+i+'] .pages', this.dom.modules);
			this.modules[i].pages = [];

			// Create the page containers
			for ( j=0; j<this.modules[i].num_pages; j++ )
			{
				pages.append("<DIV CLASS='page' PAGE='"+j+"'></DIV>");
				this.modules[i].pages[j] = new Object();
				this.modules[i].pages[j].loaded = false;
			}

			this.modules[i].navi = $J('.module[MODULE='+i+'] .navi', this.dom.modules);
			this.modules[i].navi.slider({
				range:	"min",
				value:	0,
				min:	0,
				max:	this.modules[i].num_pages - 1,
				slide:	function(e,ui)
				{
					pager.nav_slider_go(ui.value);
				}
			});

			// Set the pages scrollable
			scrollable = $J('.module[MODULE='+i+'] .scrollable', this.dom.modules);
			scrollable.scrollable({
				vertical: false,
				size: 1,
				clickable: false,
				keyboard: false,
				//onSeek: function() {
				//	pager.current_page = this.getPageIndex();
				//	pager.go(pager.current_module,this.getPageIndex());
				//},
				//speed: 0,
			});//.navigator(".navi");

			this.modules[i].api = scrollable.scrollable({api: true});
		}

		// Refresh the languages so new internationalized content is hidden
		// when calculating height.
		language.refresh();

		// Adjust the navigation height if necessary
		$J('.lang',this.dom.navigation).each(function () {
			if ( $J(this).height() > layout.nav_li_height_threshold ) {
				$J(this).addClass('short');
			}
		});

		$J('LI',this.dom.navigation).click(function() {
			pager.go($J(this).attr('module'));
		});

		if ( typeof(first_module) == 'undefined' )
			first_module = 0;

		this.nav_set_selector(first_module,true);

		this.init_load();
		//clear_errors();
		this.dom.scrollbar.hide();
	}

	this.load = function(module,page,callback,preload)
	{
		// Do nothing if not told to load anything
		if ( module === undefined )
			return;

		// Find out if a module index or name was passed
		if ( this.modules[module] == undefined )
			module = this.get_index(module);

		// Do nothing if trying to preload a dynamic module
		if ( preload && this.modules[module].dynamic )
			return;

		if ( ! this.modules[module].pages[page].loaded || this.modules[module].dynamic )
		{
			this._load(module,page,callback);
		}

	}

	this._load = function(module,page,callback) 
	{
		url = "/pager/load/"+this.modules[module].module_id+"/"+page;

		load(url,$J('.module[MODULE='+module+'] .page[PAGE='+page+']', this.dom.modules),null,function ()
			{
				if ( typeof callback === 'function' )
				{
					callback();
				}
                $J('.module[MODULE='+module+'] .page[PAGE='+page+'] .cluetip').cluetip({
                    splitTitle: '|', // use the invoking element's title attribute to populate the clueTip...
                                     // ...and split the contents into separate divs where there is a "|"
                    showTitle: true // hide the clueTip's heading
                  });
			});
		this.modules[module].pages[page].loaded = true;
	}

	this.load_modules = function(modules,first_module,first_page)
	{
		if ( modules.num_modules == 0 ) {
			this.unpaged_show('/control/show_tabs');
			return;
		}

		// We can only load the modules once, so return if already done
		if ( this.modules !== null )
			return;

		// Ensure requested module and page really exist
		if ( typeof(modules[first_module]) == 'undefined' )
			first_module = 0;
		if ( first_page + 1 > modules[first_module].num_pages )
			first_page = modules[first_module].num_pages - 1;

		this.modules = modules;
		this.first_module = first_module;
		this.first_page = first_page;
		this.init_modules(first_module);
		//this.unpaged_hide();
		//this.resize();
	}

	this.load_near_current = function()
	{
		this.next(true);
		this.prev(true);
	}

	this.nav_move = function(value)
	{
		value += this.nav_current_scroll;
		value = ( value > 100 ? 100 : value < 0 ? 0 : value );
		this.nav_scroll_to(value);
	}

	this.nav_move_down = function()
	{
		pager.nav_move(-5);
		return false;
	}

	this.nav_move_up = function()
	{
		pager.nav_move(5);
		return false;
	}

	this.nav_reset_selector = function()
	{
		this.nav_set_selector(this.current_module,true);
	}

	this.nav_set_selector = function(module,no_animate)
	{
		error_height = 0;
		if ( this.error_state > 0 )
		{
			error_height = layout.error_height * this.error_state;
		}
		if ( $J('#error').height()+5 > error_height )
			error_height = $J('#error').height()+5;

		if ( error_height > this.size/3 )
			error_height = this.size/3;
		
		nav_top = 	layout.banner+
				layout.utility+
				layout.spacer+
				error_height+
				layout.nav_diff+
				layout.nav_pad

		// Set the class of the LIs
		$J('LI',this.dom.navigation).removeClass('selected');
		current_item = $J('LI[MODULE='+module+']', this.dom.navigation);
		current_item.addClass('selected');

		if ( current_item.position() == undefined )
			return;

		t = current_item.position().top 
			+ parseInt(current_item.css('marginTop'))
			+ parseInt(this.dom.navigation.css('top'));
		h = current_item.height()
			+parseInt(current_item.css('padding-top'))
			+parseInt(current_item.css('padding-bottom'));

		realtop = t + h/2 - this.dom.selector.height()/2 + nav_top + layout.selector_adjust;

		// Hide selector if it's out of bounds
		if ( t < 0 || (t+h) > this.dom.navwrapper.height() )
		{
			this.dom.selector.hide();
			this.dom.selector.is_hidden = true;
			return;
		}
		else {
			this.dom.selector.show();
			this.dom.selector.is_hidden = false;
		}
	
		if ( ! no_animate )
		{
			this.dom.selector.animate({
				top: realtop
				//height:	h,
			}, 500);
		}
		else
		{
			this.dom.selector.css('top',realtop);//.css('height',h);
		}
		
	}

	this.nav_show_selector = function()
	{
		// First, make sure the selector is set
		this.nav_reset_selector();

		// If it's showing, do nothing
		if ( ! this.dom.selector.is_hidden )
			return;

		// Determine where we would need to scroll for the selector to be midway
		current_item = $J('LI[MODULE='+this.current_module+']', this.dom.navigation);
		frame_height = this.dom.navwrapper.height();
		nav_height = this.dom.navigation.height();
		scroll_amount = nav_height - frame_height;
		t = current_item.position().top 
			+ parseInt(current_item.css('marginTop'));

		scroll_down = t-frame_height/2;
		value = 100 - scroll_down / scroll_amount * 100;

		// Truncate values out of bounds
		value = ( value > 100 ? 100 : value < 0 ? 0 : value );

		this.nav_scroll_to(value);
	}


	this.nav_scroll_set = function()
	{
		frame_height = this.dom.navwrapper.height();
		nav_height = this.dom.navigation.height();
		this.nav_scroll_amount = nav_height - frame_height;

		// If there is scrolling to be done
		if ( this.nav_scroll_amount > 0 )
		{
			this.dom.nav_up.show().click(pager.nav_move_up).noSelect();	
			this.dom.nav_down.show().click(pager.nav_move_down).noSelect();

			// This has altered the nav_scroll_amount since the up and down arrows are in the way
			//last_margin = parseInt($J('LI:last-child', this.dom.navigation).css('marginBottom'));
			//this.nav_scroll_amount += this.dom.nav_up.height() + this.dom.nav_down.height()	+ 2*last_margin;

			// Enable the mouse wheel-<F2>5x yxy
			//  
			//  yi y        
			//   
			this.dom.navigation.mousewheel(pager.nav_scroll_wheel);

			// Set the scroll
			this.nav_scroll_to(this.nav_current_scroll);
		}

		// There is no nav scrolling
		else
		{
			this.dom.nav_up.hide();	
			this.dom.nav_down.hide();	

			// Set the scroll
			this.nav_scroll_to(100);
		}
	}

	this.nav_scroll_to = function(value)
	{
		t = 0;

		if ( this.nav_scroll_amount > 0 )
		{
			t = (100-value)/100 * -1 * this.nav_scroll_amount;
		}

		this.dom.navigation.css('top', t+'px');
		this.nav_set_selector(this.current_module,true);
		this.nav_current_scroll = value;
	}

	// This function is called outside of the scope of this object.  
	// I refer here to "pager" which is what I will globally instantiate this into.
	this.nav_scroll_wheel = function(e,delta)
	{
		if ( pager.nav_scroll_amount <= 0 )
			return;

		value = pager.nav_current_scroll;

		// Adjust constant here to change wheel responsiveness
		//value += 25 * delta;

		// Determine direction and increment/decrement scroll value
		// We're going to move 20px, so we have to translate that into a percentage
		// of pager.nav_scroll_amount
		if ( delta < 0 )
			value -= 20 / pager.nav_scroll_amount * 100;
		else
			value += 20 / pager.nav_scroll_amount * 100;

		// Truncate values out of bounds
		value = ( value > 100 ? 100 : value < 0 ? 0 : value );

		pager.nav_scroll_to(value);

		// Stop the event from propagating so the window doesn't scroll.
		if (!e) e = window.event;
		e.cancelBubble = true;
		if ( e.stopPropagation ) e.stopPropagation();
		if ( e.preventDefault ) e.preventDefault();
	}

	// Drives the module nav slider that flips pages
	this.nav_slider_go = function (page)
	{
		this.go(this.current_module,page,0);
	}

	// Navigates to the next page
	this.next = function(load_only)
	{
		// Default the target module and page 
		module = this.current_module;
		page = parseInt(this.current_page) + 1;

		// Next module if it's the target is after the last page
		if ( page == this.modules[module].num_pages )
		{
			page = 0;
			module = this.get_next_module();
		}

		// If load_only is true, only load, don't go
		if ( load_only == true ) {
			this.load(module,page,null,load_only);
		} else
			this.go(module,page);
	}
	
	this.page_scroll_set = function()
	{
		if ( pager.dom.current_page == undefined )
			return;

		height = pager.dom.current_page.height();
		scrollHeight = pager.dom.current_page.attr('scrollHeight');
		scrollTop = pager.dom.current_page.attr('scrollTop');

		if ( scrollHeight-height > 0 )
			pager.dom.scrollbar.slider('value',100-parseInt(scrollTop/(scrollHeight-height) * 100));

		if ( scrollHeight > height )
			pager.dom.scrollbar.show();
		else
			pager.dom.scrollbar.hide();
	}

	this.page_scroll_to = function(value)
	{
		height = this.dom.current_page.height();
		scrollHeight = this.dom.current_page.attr('scrollHeight');
		scrollTop = this.dom.current_page.attr('scrollTop');

		if ( scrollHeight > height )
			this.dom.current_page.attr('scrollTop',(scrollHeight-height)*(100-value)/100);
	}

	// Navigates to the previous page
	this.prev = function(load_only)
	{
		// Default the target module and page 
		module = this.current_module;
		page = this.current_page - 1;

		// Prev module if it's the target is before the first page
		if ( page < 0 )
		{
			module = this.get_prev_module();

			if ( module !== undefined )
				page = this.modules[module].num_pages - 1;
		}

		// If load_only is true, only load, don't go
		if ( load_only == true ) {
			this.load(module,page,null,load_only);
		} else
			this.go(module,page);
	}

	// Shows the full-width "unpaged"
	this.unpaged_show = function(link,data,full)
	{
		stopAllFlash();

		// Don't do anything if the unpaged view is already showing 
		// and the requested link is what's been loaded
		//if ( this.unpaged > 0 && this.unpaged_path == link )
		//	return;
		
		// Mark the unpaged as being down
		this.unpaged = 1;

		this.dom.selector.hide();

		if ( link != undefined ) {
			load(link,'#unpaged',data);
			this.unpaged_path = link;
		}

		if ( typeof(full) != 'undefined' && full ) {
			this.unpaged = 2;
			$J('#content_bg')
				.css('z-index',10)
				.animate({
					'width': window.innerWidth,
					'height': window.innerHeight,
					'marginLeft': 0,
					'marginTop': 0,
					'left': 0,
					'top': 0,
				},500);
			$J('#pager').fadeOut(500);

			$J('#unpaged').width(window.innerWidth-80).height(window.innerHeight-60).fadeIn(500);
		} else {
			$J('#content_bg')
				.css('left','')
				.css('top','')
				.css('z-index',10)
				.animate({
					"width": 960+layout.right_shade+layout.left_shade,
					"marginLeft": -layout.left_shade
				},500);
			$J('#pager').fadeOut(500);

			$J('#unpaged').width(960+layout.right_shade+layout.left_shade-layout.unpaged_diff).fadeIn(500);
		}

		//clear_errors();
	}	
	
	this.unpaged_hide = function()
	{
		if ( ! this.unpaged )
			return;
		
		// Only let the unpaged go if this.modules is defined
		if ( typeof(this.modules) == 'undefined' ) {
			this.unpaged_show('/users/show_login');
			return;
		}

		// Mark the unpaged as being up
		this.unpaged = 0;
		this.unpaged_path = '';

		// Scroll it up and redraw the display
		$J('#content_bg')
			.css('left','')
			.css('top','')
			.animate({
					"width": 960+layout.right_shade+layout.left_shade-layout.nav_width,
					"marginLeft": layout.nav_width-layout.left_shade,
				},
				{
					'complete': function () {
						$J('#content_bg').css('z-index', 2);
					},
					'duration': 500
				});
		$J('#pager').fadeIn(500);
		$J('#unpaged').fadeOut(500).width(960+layout.right_shade+layout.left_shade-layout.nav_width-layout.unpaged_diff).html('');
		this.show_errors();
		this.nav_scroll_set();

		//clear_errors();
	}	
	
	this.resize = function()
	{
		error_height = 0;
		if ( this.error_state > 0 )
		{
			error_height = layout.error_height * this.error_state;
		}
		if ( $J('#error').height()+5 > error_height )
			error_height = $J('#error').height()+5;
		
		size = document.documentElement.clientHeight
			-layout.banner
			-layout.utility
			-layout.spacer
			//-layout.top_shade
			//-layout.bottom_shade
			-layout.bottom_space
			-layout.bottom_bar;

		this.size = size;

		if ( error_height > size/3 )
		{
			error_height = size/3;
		}

		size -= error_height;

		// Find the scrollbar top
		display = this.dom.scrollbar.css('display');
		this.dom.scrollbar.show()
		scrollbarTop = parseInt(this.dom.scrollbar.css('top'));
		this.dom.scrollbar.css('display',display);

		$J("#error-wrapper").height(error_height-6);
		$J("#nav_bg").height(size-2*layout.nav_diff).css('marginTop',layout.nav_diff+error_height);

		// Don't chain this one because .height() will return "0px" if passed a negative number
		$J("#nav").height(
				size-
				2*layout.nav_diff-
				layout.bottom_shade-
				layout.top_shade-
				2*layout.nav_pad
			);
		$J("#nav").css('top',
				layout.banner+
				layout.utility+
				layout.spacer+
				error_height+
				layout.nav_diff+
				layout.top_shade
			)
			.css('margin-top',layout.nav_pad)
			.css('margin-bottom',layout.nav_pad);

		$J("DIV.content")
			.height(
				size-
				layout.bottom_shade-
				layout.top_shade - 
				layout.bottom_pad
			       );
		$J("DIV.page")
			.height(
				size-
				layout.bottom_shade-
				layout.top_shade - 
				layout.bottom_pad - 
				layout.page_height_adjust
			       );
		if ( this.unpaged == 2 )
		{
			$J('#content_bg')
				.css('z-index',10)
				.animate({
					'width': window.innerWidth,
					'height': window.innerHeight,
					'marginLeft': 0,
					'marginTop': 0,
					'left': 0,
					'top': 0,
				},500);
			$J('#pager').fadeOut(500);

			$J('#unpaged').width(window.innerWidth-80).height(window.innerHeight-60).fadeIn(500);
		} else {
			$J("#unpaged")
				.height(
					size-
					layout.bottom_shade-
					layout.top_shade - 
					2*layout.bottom_pad
				       );
			$J("#content_bg").height(size).css('marginTop',error_height);
		}

		$J("#pager").css('top',layout.top_shade);

		$J("DIV.scrollable").height(size-10-53);
		$J("DIV.tabs").height(size-144);
		//$J("DIV.module").height(size-10);
		$J("#scrollbar").height(
					size -
					layout.bottom_shade-
					layout.top_shade - 
					layout.bottom_pad - 
					layout.scrollbar_height_adjust -
					scrollbarTop
				);


		//scrollable_top = parseInt($J('DIV.scrollable').css('top'));
		//$J('DIV.scrollable').height(size-scrollable_top);
		
		// The following line should put the main vertical slider in its correct position.
		// However, jQuery tools scrollable wants to ignore a seekTo request if it's already seeked to
		// that index.  jQuery tools scrollable needs to be altered to fix.

		// Cut out if there are no defined modules
		if ( this.modules == undefined )
			return;

		this.dom.api.seekTo(this.current_module,0);
		if ( this.modules.num_modules != 0 )
			this.modules[this.current_module].api.seekTo(this.current_page,0);
		this.nav_scroll_set();
		this.page_scroll_set();
		resizeGrids();
	}

	this.show_errors = function ()
	{
		numErrors = $J('#error LI').length;

		if ( numErrors == 0 )
		{
			this.hide_errors();
			return;
		}

		this.error_state = numErrors;

		nav_width = 0;
		if ( this.unpaged == false )
			nav_width = layout.nav_width;

		width = 960+layout.right_shade+layout.left_shade-nav_width+layout.error_width_adjust-2*layout.error_width_diff;
		marginLeft = nav_width-layout.left_shade+layout.error_left_adjust+layout.error_width_diff;
		$J('#error_bg')
			.css({
				"width": width,
				"marginLeft": marginLeft,
				"height": this.size/2+layout.error_height_adjust,
				"marginTop": layout.error_top_adjust
			}).show();

		$J('#error LI SPAN.close').css('marginRight',layout.error_width_diff);

		$J('#error LI').height(layout.error_height);

		$J('#error-wrapper').width(width-2*$J('#error-wrapper').position().left)
			.css('overflow','hidden');

		this.resize();
	}

	this.hide_errors = function ()
	{
		this.error_state = 0;
		$J('#error_bg').hide();
		this.resize();
	}

	this.error_color = function (color)
	{
		if ( color == 'red' )
		{
			$J('#error_bg').removeClass('yellow').addClass('red');
		}
		else
		{
			$J('#error_bg').removeClass('red').addClass('yellow');
		}
	}

	this.update_utility = function ()
	{
		load('layout/utility1','#utility1',null,function () {language.init()});
		load('layout/utility2','#utility2',null,function () {language.init()});
	}

	this.init();
	//this.init_load();
	//this.go(0,0);
}
