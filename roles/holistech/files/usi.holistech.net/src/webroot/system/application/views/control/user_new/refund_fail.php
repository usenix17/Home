<P>This refund cannot be completed for the following reason<?=count($reasons)>1?'s':''?>:</P>

<UL>
	<? foreach ( $reasons as $r ): ?>
	<LI><?=$r?></LI>
	<? endforeach; ?>
</UL>
