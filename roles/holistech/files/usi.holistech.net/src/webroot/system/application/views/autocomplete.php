<? if (count($array) ): ?>
<UL>
<?
foreach ( $array as $name => $item )
{
	if ( isset($search) )
		$item = preg_replace('/('.$search.')/i','<B>\1</B>',$item);

	print "<LI NAME='{$name}'>{$item}</LI>";
}
?>
</UL>
<? else: ?>
<P><I>No results found.</I></P>
<? endif; ?>
