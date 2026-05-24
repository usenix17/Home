function Control_Purchases() {}

Control_Purchases.poll = function()
{
	now = new Date();

	// See if we've been waiting for too long
	if ( now.getTime() - Control_Purchases.start_time.getTime() > 60*1000 )
		load('/control_purchases/timeout/'+Control_Purchases.purchase_id,'#control_purchase_thank_you_DIV');
	else {
		// Wait 1 second
		wait = new Date();
		while ( wait.getTime() - now.getTime() < 1000 )
			wait = new Date();

		// See if the purchase has been completed
		load('/control_purchases/poll/'+Control_Purchases.purchase_id,'#HIDDEN');
	}
}
