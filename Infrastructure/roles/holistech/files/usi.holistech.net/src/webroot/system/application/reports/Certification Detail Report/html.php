<H2>Certification Detail Report</H2>

<TABLE CLASS='ticTacToe'>
	<TR>
        <TH>Real Name</TH>
        <TH>Username</TH>
        <TH>Email</TH>
        <TH>Enrollment Date</TH>
        <TH>Certification Date</TH>
    </TR>
    <?  foreach ( $result as $row ): ?>
        <TR>
            <TD><?=$row['realName']?></TD>
            <TD><?=substr($row['username'],0,5) == 'CAS::' ? substr($row['username'],5) : $row['username']?></TD>
            <TD><?=$row['email']?></TD>
            <TD ALIGN=CENTER><?=date('jMy G:i',strtotime($row['date']))?></TD>
            <TD ALIGN=CENTER><?=$row['certification_time'] ? date('jMy G:i',strtotime($row['certification_time'])) : ''?></TD>
        </TR>
    <? endforeach; ?>
</TABLE></CENTER>
