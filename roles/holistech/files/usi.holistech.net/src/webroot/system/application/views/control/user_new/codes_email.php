<? $base = preg_replace("#/".COURSENAME."/$#",'/',base_url()); ?>

<P><?=$user->realName?>,</P>

<P>Below is the information you requested regarding registration codes for az-hospitality.nau.edu:</P>

<TABLE BORDER=1 CELLPADDING=3>
	<TR><TH>Code</TH><TH>Website</TH><TH>Status</TH></TR>
	<? foreach ( $codes as $c ): ?>
	<TR>
		<TD><?=Codes::generate($c['code']);?></TD>
		<TD><?=$base.$c['course'].'/';?></TD>
		<TD><?=$c['code_status'].($c['code_status']=='Used'?' by '.$c['enrollment_realName']:'');?></TD>
	</TR>
	<? endforeach; ?>
</TABLE>
	
<P>If you need any further assistance, please call 928-523-3737 or email <A HREF="mailto:food@nau.edu">food@nau.edu</A>.</P>
