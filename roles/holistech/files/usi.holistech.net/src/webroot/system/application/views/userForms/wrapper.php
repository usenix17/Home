<?
$this->load->helper('form'); 
$uniq = uniqid();
?>
<H1><?=$title?></H1>
<FORM ID="<?=$uniq?>" ONSUBMIT="return false;">
<?=form_hidden('module_id',$module_id);?>

<?=$userForm?>

	<P ALIGN=CENTER><?=$this->sayings->input('Submit',"TYPE=BUTTON ONCLICK='userForm.save(\"{$uniq}\")'");?></P>
</FORM>
