	<SELECT ID="language_toggle" <?=( count($languages) > 1 ? '' : 'STYLE="display:none"')?>>
	<? foreach ( $languages as $lang => $text ): ?>
		<OPTION VALUE="<?=$lang?>"><?=$text?></OPTION>
	<? endforeach; ?>
	</SELECT> 
<? if ( count($languages) > 1 ): ?>
	|
<? endif; ?>

<? if ( $logged_in ): ?>
	<A HREF="javascript:pager.unpaged_show('/control/show_tabs')">Settings</A> |
	<? if ( $GLOBALS['user']->can_certify() ): ?>
		<A HREF="javascript:load('/certificate/','#HIDDEN')">Certificate</A> |
	<? endif; ?>
	<A HREF="javascript:login.logout()">Log Out</A>
<? endif; ?>
