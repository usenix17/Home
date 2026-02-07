<?
function email($to,$subject,$message='EOM',$from='Jason.Karcz@nau.edu')
{
	require_once(BASEPATH.'application/libraries/PHPMailer_v5.1/class.phpmailer.php');
	$mail = new PHPMailer();
	$mail->IsSMTP(); // telling the class to use SMTP
	try {
		$mail->Host       = "mailgate.nau.edu"; // SMTP server
		//$mail->SMTPDebug  = 2;                     // enables SMTP debug information (for testing)
		
		if ( !$to )
		$to = "Jason.Karcz@nau.edu";

		$mail->AddAddress($to);
		$mail->AddBCC('Jason.Karcz@nau.edu');
		$mail->SetFrom($from);
		$mail->Subject = $subject;
		$mail->MsgHTML($message);
		$mail->Send();
	} catch (Exception $e) {
		error($e->getMessage());
	}
}
