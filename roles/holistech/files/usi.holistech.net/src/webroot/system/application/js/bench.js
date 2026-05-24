function bench(restart)
{
	if ( typeof(this.mark) == 'undefined' || restart === true ) {
		console.log('Bench start');
		this.mark = (new Date).getTime();
	} else {
		t = (new Date).getTime();
		console.log(t-this.mark);
		this.mark=t;
	}
}
