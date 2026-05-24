<? 
	$i = 0;
	$selected_index = 0;
	$divs = '';
?>
<DIV ID='control_tabs' WIDTH="100%" HEIGHT="100%" >
	<UL>
		<? foreach ( $tabs as $name => $url ):
			$id = "control_tab_".str_replace(' ','_',$name);
			$divs .= "<DIV ID='{$id}' URL='{$url}' />"
		?>
			<LI><A HREF="#<?=$id?>"><?=$name?></A></LI>
		<?
			if ( $url == $selected )
				$selected_index = $i;
			$i++; 
			endforeach; 
		?>
	</UL>
	<?=$divs?>
</DIV>

<SCRIPT>
	div = $J("#control_tabs").tabs({
		select: function(ev,ui) {
			div=$J('#'+ui.panel.id);
			clear_errors();
			load(div.attr('url'),div);
		},
		selected: 0,
	}).children('DIV:first');
	load(div.attr('url'),div);
</SCRIPT>
