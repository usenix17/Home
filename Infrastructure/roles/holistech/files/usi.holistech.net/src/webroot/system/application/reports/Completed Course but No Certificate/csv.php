Real Name, Username, Email, Enrollment Date
<? foreach ( $result as $row ):
        $user = getUser('user_id',$row['user_id'],FALSE);
        if ( ! $user->can_certify($row['enrollment_id']) )
            continue;
?><?=$row['realName']?>,<?=substr($row['username'],0,5) == 'CAS::' ? substr($row['username'],5) : $row['username']?>,<?=$row['email']?>,<?=date('jMy G:i',strtotime($row['date']))?>

<? endforeach;?>
