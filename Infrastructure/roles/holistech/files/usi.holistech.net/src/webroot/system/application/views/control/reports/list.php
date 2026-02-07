<H1>Reports</H1>

<UL>
	<? foreach ( $reports as $r ): ?>
		<LI><A HREF="javascript:Control_Reports.show('<?=$r?>');"><?=$r?></A></LI>
	<? endforeach; ?>
</UL>
<HR>
<DIV ID="report">
</DIV>
