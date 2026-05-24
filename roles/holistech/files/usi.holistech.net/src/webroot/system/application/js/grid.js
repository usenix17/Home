document.grids = new Object;
fontFactor = .7;
function resizeGrids()
{
	$J.each(document.grids, function (k,v) {
		if ( v.resize() === false )
			delete document.grids[k];
	});
}

function refreshGridFonts()
{
	$J.each(document.grids, function (k,v) {
		document.grids[k].refreshFonts();
	});
}

function increaseFont()
{
	fontFactor += .1;
	if ( fontFactor > 1.1 )
		fontFactor = 1.1;
	load('/layout/fontSize/'+fontFactor,'#HIDDEN');
	resizeGrids();
}

function decreaseFont()
{
	fontFactor -= .1;
	if ( fontFactor < .5 )
		fontFactor = .5;
	load('/layout/fontSize/'+fontFactor,'#HIDDEN');
	resizeGrids();
}

function Grid(target,hdivisions,vdivisions,pad)
{
	this.config = {
		pad:		5,
		hdivisions:	5,
		vdivisions:	10,
	};

	if ( typeof(hdivisions) != 'undefined' ) 
		this.config.hdivisions = hdivisions;
	if ( typeof(vdivisions) != 'undefined' ) 
		this.config.vdivisions = vdivisions;
	if ( typeof(pad) != 'undefined' ) 
		this.config.pad = pad;

	this.divs = [];
	this.grid = [];
	this.currentDivSet = [];
	this.target = $J(target);
	this.gridVisible = false;

	this.divs = new Object;

	this.div = function (id,sX,sY,eX,eY,padding)
	{
		div = new GridDiv(id,this.target);
		div.grid = this;

		if ( typeof(padding) != 'undefined' )
			div.padding = padding;

		div.place(sX,sY,eX,eY);

		this.divs[id] = div;
		//this.divs.push( div );

		return div;
	}

	this.resolution = function ( x, y )
	{
		this.config.hdivisions = x;
		this.config.vdivisions = y;
		this.resize();
	}

	this.showGrid = function ()
	{
		this.hideGrid();

		pad = Math.floor(window.innerHeight / 768 * this.config.pad);

		hblock = ( this.target.width() + parseInt(this.target.css('paddingRight')) + parseInt(this.target.css('paddingLeft')) - ( this.config.hdivisions + 1 ) * pad ) / this.config.hdivisions;
		vblock = ( this.target.height() + parseInt(this.target.css('paddingTop')) + parseInt(this.target.css('paddingBottom')) - ( this.config.vdivisions + 1 ) * pad ) / this.config.vdivisions;

		for ( i = 1; i <= this.config.hdivisions; i++ )
		{
			left = pad + ( i - 1 ) * ( hblock + pad );

			for ( j = 1; j <= this.config.vdivisions; j++ )
			{
				if ( typeof(this.grid[i]) == 'undefined')
					this.grid[i] = [];

				if ( typeof(this.grid[i][j]) == 'undefined' )
					this.grid[i][j] = document.createElement( 'DIV' );

				this.grid[i][j].className = 'grid';

				this.grid[i][j].style.display = 'block';
				//this.grid[i][j].style.width = hblock+'px';
				this.grid[i][j].style.left = left+"px";

				//this.grid[i][j].style.height = vblock+'px';
				this.grid[i][j].style.top = pad + ( j - 1 ) * ( vblock + pad ) + "px";
				

				this.grid[i][j].innerHTML = i + '_' + j;

				this.target.append( this.grid[i][j] );
			}
		}

		this.gridVisible = true;
	}

	this.hideGrid = function ()
	{
		$J('DIV.grid',this.target).hide();
		this.gridVisible = false;
	}


	this.resize = function ( )
	{
		if ( typeof(this.target) == 'undefined' ) {
			delete this;
			return;
		}
		var me = this;
		$J.each(this.divs, function(k,v) {
			//console.log(k,v);
			if ( v.refresh() === false )
				delete me.divs[k];
		});

		if ( this.gridVisible )
			this.showGrid();
	}

	this.refreshFonts = function ( )
	{
		if ( typeof(this.target) == 'undefined' ) {
			delete this;
			return;
		}
		$J.each(this.divs, function(k,v) {
			v.refreshFonts();
		});
	}

	this.hideAll = function ( )
	{
		document.divs.each( function(i) {
			i.hide();
		});
	}

	document.grids[this.target.attr('id')] = this;
}

function GridDiv(id,target)
{
	this.place = function ( sX, sY, eX, eY )
	{
		pad = Math.floor(window.innerHeight / 768 * this.grid.config.pad);

		hblock = ( this.target.width() + parseInt(this.target.css('paddingRight')) + parseInt(this.target.css('paddingLeft')) - ( this.grid.config.hdivisions + 1 ) * pad ) / this.grid.config.hdivisions;
		vblock = ( this.target.height() + parseInt(this.target.css('paddingTop')) + parseInt(this.target.css('paddingBottom')) - ( this.grid.config.vdivisions + 1 ) * pad ) / this.grid.config.vdivisions;

		if ( this.vcenter )
		{
			this.div.style.paddingTop = 0;
			this.div.style.paddingBottom = 0;
		}

		this.refreshFonts();

		padding = Math.floor(window.innerHeight / 768 * this.padding);
		this.div.style.padding = padding;

		this.div.style.width =	( eX - sX + 1 ) * hblock + ( eX - sX ) * pad - ( 2 * padding )+'px';
		this.div.style.height =	( eY - sY + 1 ) * vblock + ( eY - sY ) * pad -( 2 * padding )+'px';
		this.div.style.top =	pad + ( sY - 1 ) * ( vblock + pad )+'px';
		this.div.style.left =	pad + ( sX - 1 ) * ( hblock + pad )+'px';

		if ( this.vcenter )
		{
			this.div.style.paddingTop = ( parseInt( this.div.style.height ) - fontSize ) / 2 - 2;
			this.div.style.paddingBottom = ( parseInt( this.div.style.height ) - fontSize ) / 2 + 2;
			this.div.style.height = fontSize;
		}

		this.div.style.width = Math.floor(this.div.style.width)+'px';
		this.div.style.height = Math.floor(this.div.style.height)+'px';

		this.sX = sX;
		this.sY = sY;
		this.eX = eX;
		this.eY = eY;
	}

	this.touch = function ()
	{
	}

	this.refresh = function ()
	{
		if( $J('#'+this.id,this.target).length == 0 ) {
			return false;
		}

		this.place( this.sX, this.sY, this.eX, this.eY );
		this.touch();
	}

	this.refreshFonts = function ()
	{
		//return;
		$J('*:not(.ui-tabs):not(.noautofont)',this.div).css('fontSize',Math.floor(window.innerHeight / 700 * this.fontSize * fontFactor)+'pt');
		$J('H1',this.div).css('fontSize',Math.floor(window.innerHeight / 700 * this.fontSize * 20 / 12 * fontFactor)+'pt');
		$J('H1 *',this.div).css('fontSize',Math.floor(window.innerHeight / 700 * this.fontSize * 20 / 12 * fontFactor)+'pt');
		$J('H1 SPAN.edit, H1 SPAN.edit *',this.div).css('fontSize',Math.floor(window.innerHeight / 700 * this.fontSize * fontFactor)+'pt');
		$J('H2,.big',this.div).css('fontSize',Math.floor(window.innerHeight / 700 * this.fontSize * 16 / 12 * fontFactor)+'pt');
		$J('NAV LI,.ui-accordion-header > A,UL.ui-tabs-nav > LI > A,.button',this.div).css('fontSize',Math.floor(window.innerHeight / 700 * this.fontSize * 10 / 12 * fontFactor)+'pt');
	}

	this.html = function ( text )
	{
		this.div.innerHTML = text;
		this.refresh();
		return this;
	}

	this.append = function ( text )
	{
		this.div.innerHTML += text;
		return this;
	}

	this.hide = function ()
	{
		this.div.style.display = 'none';
		return this;
	}

	this.show = function ()
	{
		this.div.style.display = 'block';
		this.refresh();
		return this;
	}

	this.addClass = function ( className )
	{
		$J(this.div).addClass(className);
		return this;
	}

	this.css = function ( k,v )
	{
		$J(this.div).css(k,v);
		return this;
	}

	this.target = target;

	this.scroll = function ( bool ) { this.div.style.overflow = ( bool ? 'auto' : 'visible' ); return this; }
	this.border = function ( bool ) { this.div.style.border = ( bool ? 1 : 0 ); return this;  }

	this.div = document.getElementById(id);
	if ( ! this.div )
	{
		this.div = document.createElement( 'DIV' );
		target.append( this.div );
	}

	this.div.id = id;
	this.id = id;
	$J(this.div).addClass('gridDiv');
	this.padding = 10;
	this.fontSize = 12;
	//this.hide();
	
}
