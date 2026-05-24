<H2>Completed Course but No Certificate</H2>
<TABLE CLASS='ticTacToe'>
	<TR>
        <TH>Real Name</TH>
        <TH>Username</TH>
        <TH>Email</TH>
        <TH>Enrollment Date</TH>
    </TR>
    <? foreach ( $result as $row ): ?>
        <? 
            $user = getUser('user_id',$row['user_id'],FALSE);
            if ( ! $user->can_certify($row['enrollment_id']) )
                continue;
        ?>
        <TR>
            <TD><?=$row['realName']?></TD>
            <TD><?=substr($row['username'],0,5) == 'CAS::' ? substr($row['username'],5) : $row['username']?></TD>
            <TD><?=$row['email']?></TD>
            <TD ALIGN=CENTER><?=date('jMy G:i',strtotime($row['date']))?></TD>
        </TR>
    <? endforeach; ?>
</TABLE></CENTER>
