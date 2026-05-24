<?
//////////////////////////////////////////////////////////////////////
//
//	replaceTags.php
//	Jason Karcz
//	Replaces sqgare tags
//
//////////////////////////////////////////////////////////////////////
//
//	20 October 2003 - Created
//
//////////////////////////////////////////////////////////////////////

debug_log('Loading replaceTags.php');
$GLOBALS['tagRegEx'] = "/[\\\\]?\[(.+?)( ([^\]]+?))?\](([\w\W]+?)\[\/\\1\])?/sme";
$GLOBALS['argsRegEx'] = "/([^=\s]+)(=(\"?)(.+?)\\3\s)?/";
$GLOBALS['openTags'] = '|SAY|IMG|FLASH|USERNAME|DISPLAYNAME|TESTQUESTION|F|CERTIFICATE|INDEX|';
$GLOBALS['closedTags'] = '|QUESTION|QUOTE|NOTE|ITSTRUE|TIP|BOX|TELLMEMORE|SCRIPT|JAVASCRIPT|IMGTABLE|PRE|ITEM|CAMPUSCLUE|CASESTUDY|TERM|';

$ci =& get_instance();
$ci->load->helper('file');

function filterTags( $text, $scope, $lang )
{
	debug_log('filterTags');

	// Determine whether we're parsing for db storage or filtering before display
	$parse = 1;
	
	if ( $lang == "default" ) 
	{
		error('Default language passed to filterTags');
	}

//	return $text;

	$len = strlen( $text );

	$out = '';

	$readingTag = false;
	$currentTag = '';

	$readName = false;
	$currentName = '';

	$readingArgs = false;
	$currentArgs = '';

	$readingContents = false;
	$readContents = false;
	$currentContents = '';

	$lastChr = '';
	$x = '';
	//$chars = str_split($text);

	for ( $i = 0; $i < $len; $i++ )
	{
		$lastChr = $x;
		$x = substr( $text, $i, 1 );
		//$x = $chars[$i];
		
		if ( $x == '[' && $lastChr != "\\" )
		{
			$readingTag = true;
			if ( $readingContents && substr( $text, $i, 3 + strlen( $currentName ) ) == "[/{$currentName}]" )
			{
				$currentTag .= "[/{$currentName}";
				$i += 2 + strlen( $currentName ); // Make the loop skip ahead to the end of the closing tag
				$x = substr( $text, $i, 1 ); // Set the current character to the cursor position.
				$readingContents = false;
				$readContents = true;
			}
		}

		if ( $readingTag )
		{
			$currentTag .= $x;

			if ( $x == ']' && $lastChr != "\\" )
			{
				$readingArgs = false;
				$readingName = false;
				$readName = true;

				// If this is an open tag (i.e. to closing tag requirement)
				if ( strpos( $GLOBALS['closedTags'], "|{$currentName}|" ) === false )
				{
					$out .= replaceTags( $currentTag, $currentName, $currentArgs, $currentContents, $scope, $lang, $parse );

					$readingTag = false;
					$currentTag = '';

					$readName = false;
					$currentName = '';

					$readingArgs = false;
					$currentArgs = '';
					continue;
				}
				// This tag requires a closing, so we're either at the end of the opening tag, or the end of the closing tag
				else
				{
					// If we're just getting to the contents, or in the middle of reading the contents
					if ( !$readContents )
					{
						if ( !$readingContents )
						{
							$readingContents = true;
							continue;
						}
					}
					else
					{
						$out .= replaceTags( $currentTag, $currentName, $currentArgs, $currentContents, $scope, $lang, $parse );
						$readingTag = false;
						$currentTag = '';

						$readName = false;
						$currentName = '';

						$readingArgs = false;
						$currentArgs = '';

						$readingContents = false;
						$readContents = false;
						$currentContents = '';
						continue;
					}
				}
			}

			if ( !$readName )
			{
				// If I'm reading what I think is the tag name and I come across a non alpha, non space
				// backtrack and get out.  Something's not right...
				if ( !preg_match( "/[A-Za-z ]/", $x ) && strlen( $currentTag ) > 1 )
				{
						$out .= $currentTag;
						
						$readingTag = false;
						$currentTag = '';

						$readName = false;
						$currentName = '';

						$readingArgs = false;
						$currentArgs = '';

						$readingContents = false;
						$readContents = false;
						$currentContents = '';
						continue;
				}
				
				if ( preg_match( "/[A-Za-z]/", $x ) )
				{
					$currentName .= $x;
					continue;
				}
				
				if ( $x == ' ' )
				{
					$readName = true;
					$readingArgs = true;
					continue;
				}
			}

			if ( $readingArgs )
			{
				$currentArgs .= $x;
				continue;
			}

			if ( $readingContents )
			{
				$currentContents .= $x;
				continue;
			}
		}
		else
			$out .= $x;
	}

	if ( $readingTag )
		$out = "I'm still reading the tag: <B>'$currentName'</B>.  Perhaps you forgot a closing tag?" . $text;

	return $out;
}	

function replaceTags( $all, $name, $args, $contents, $scope, $lang, $parse )
{
	$ci =& get_instance();
	switch ( strtoupper( $scope ) )
	{
		case "PAGE":
		// PAGE is the highest tag

			debug_log('replaceTags switch = '.$name);
			switch ( strtoupper( $name ) )
			{
				case "QUESTION":
					$contents = filterTags( $contents, 'QUESTION', $lang );
					$look = say( "Look for the answer here.", $lang );
					return <<<FIN
						<TABLE CLASS="practice-question">
						{$contents}
						<TR><TD COLSPAN=2 CLASS="pq-ans">{$look}</TD></TR>
						</TABLE>
FIN;
					break; // QUESTION
					
				case "SAY":
					$args = parseArgs( $args );
					return say( join( ' ', $args['args'] ), $lang );
					break; // SAY
					
				case "IMG":
					return rt_img( parseArgs( $args ), stripslashes( stripslashes( $all ) ), $lang );
					break; // IMG

				case "FLASH":
					$args = parseArgs( $args );
					
					if ( substr( $args['args'][0], 0, 4 ) == 'http' )
					{
						$file = $args['args'][0];
					}
					else
					{
						$flash = new File( "flash/" . $args['args'][0] );

						if ( !$flash->exists() )
						$flash = new File( "flash/" . strtoupper( $lang ) . '/' . $args['args'][0] );

						$file = base_url().'layout/'.$flash->path;
					}
						
					if ( isset( $flash ) && !$flash->exists() )
					{
						warn( "Flash file '{$flash->path}' does not exist." );
						return ( $GLOBALS['user']->has(new Token('edit','content',COURSENAME)) ) ? "<SPAN CLASS=note>" . stripslashes( $all ) .  "</SPAN>" : "";
					}
					else
					{
						// Log the media file
						$GLOBALS['media'][] = $file;

						$width = ! isset($args['options']['WIDTH']) ? 1 : $args['options']['WIDTH'];
						$height = ! isset($args['options']['HEIGHT']) ? 1 : $args['options']['HEIGHT'];
						$align = ! isset($args['options']['ALIGN']) ? '' : $args['options']['ALIGN'];

						if ( $width > 480 ) {
							$height = 480/$width * $height;
							$width = 480;
						}

						$uniq = uniqid();						
$code = <<<FIN
<P ALIGN={$align}>
<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=5,0,0,0" width="{$width}" height="{$height}" id="{$uniq}">
        <param name=movie value="{$file}">
        <param name=quality value=high><param name="LOOP" value="false">
        <embed src="{$file}" quality=high pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" type="application/x-shockwave-flash" width="{$width}" height="{$height}" loop="false" swliveconnect="true"></embed>
</object>
</P>
FIN;
						$code = json_encode($code);
						$clicktoplay = say('Click To Play');

						return <<<FIN
<A HREF="javascript:stopFlash('{$uniq}');" ID="stop_{$uniq}" STYLE="display: none;">Stop</A>
<DIV ID="{$uniq}" ALIGN=CENTER><TABLE CLASS=clicktoplay WIDTH={$width} HEIGHT={$height}><TR><TD>{$clicktoplay}</TD></TR></TABLE><SPAN CLASS=flash></SPAN></DIV>
<SCRIPT>
	flash['{$uniq}'] = {$code};
	\$J('#{$uniq} TABLE').click(function () { playFlash('{$uniq}'); });
</SCRIPT>
FIN;
					}
					break; // FLASH

				case "QUOTE":
					return "<P CLASS=quote>&ldquo;" . trim( stripslashes( $contents ), '"' ) . "&rdquo;"
					. ( $args ? "<BR><I>&mdash;" . $args . "</I></P>" : "</P>" );
					break; // QUOTE

				case "NOTE":
					// This is a display-specific tag, so we don't filter it when parsing.
					//if ( $parse )
					//{
					//	return '[NOTE]' . stripslashes( $contents ) . '[/NOTE]';
					//}
					//else
					//{
						// Return the development note only if the user is an admin or a superuser.
						return ( $GLOBALS['user']->has(new Token('edit','content',COURSENAME)) ) ? "<SPAN CLASS=note>" . stripslashes( $contents ) .  "</SPAN>" : "";
					//}
					break; // NOTE
				
				case 'TITLECARD':
					return "<TABLE STYLE='height:100%; width: 100%; margin-top: 10em;'><TR><TD STYLE='vertical-align: middle;'><H1 STYLE='text-align: center;'>{$args}</H1></TD></TR></TABLE>";
					break; // TITLECARD

				case 'TERM':
					return "<A CLASS='cluetip' title='{$args}|"
                            .addslashes(stripslashes( $contents ))
                            ."'>{$args}</A>";
					break; // TERM

				case 'CAMPUSCLUE':
					$args = parseArgs( $args );
                    $align = 'right';
                    if ( array_key_exists('ALIGN', $args['options']) )  $align = $args['options']['ALIGN'];
                    $align = strtolower($align);
                    
					return "<DIV CLASS='campusclue {$align}'><H2>Campus Clue</H2><P>" . stripslashes( $contents ) . "</P></DIV>";
					break; // CAMPUSCLUE

				case 'CASESTUDY':
					return "<DIV CLASS=casestudy><H2>Case Study</H2><P>{$args}</P><P ONCLICK='\$J(this).hide().next().slideDown();'><SPAN STYLE='cursor: pointer; padding: 1px; border-bottom: 1px solid blue; color: blue;'>Show Answer</SPAN></P><DIV STYLE='display:none'><HR>" . stripslashes( $contents ) . "</DIV></DIV>";
					break; // CASESTUDY

				case 'ITSTRUE':
					return "<P><DIV CLASS=script><B>It's True!</B><BR><BR>" . stripslashes( $contents ) . "</DIV></P>";
					break; // ITSTRUE

				case 'TIP':
					return "<P><DIV CLASS=script><B>Tip:</B><BR><BR>" . stripslashes( $contents ) . "</DIV></P>";
					break; // SCRIPT

				case 'BOX':
					return "<P><DIV CLASS=script>" . stripslashes( $contents ) . "</DIV></P>";
					break; // BOX

				case 'TELLMEMORE':
					return rt_tellmemore( stripslashes( $contents ), $lang );
					break; // SCRIPT

				case 'SCRIPT':
					return rt_script( stripslashes( $contents ), $lang );
					break; // SCRIPT

				case 'JAVASCRIPT':
					return "<SCRIPT LANGUAGE=\"JavaScript\">\n" . stripslashes( $contents ) . "\n</SCRIPT>";
					break; // JAVASCRIPT

				case 'IMGTABLE':
					$args = parseArgs( $args );
					$args['options']['ALIGN'] = 'RIGHT';
					$args['options']['WIDTH'] = intval($args['options']['WIDTH']).'%';
					$args['options']['CLASS'] = 'imgTable';
					$img = rt_img($args,'',$lang);

					$contents = filterTags(stripslashes( stripslashes( $contents ) ), 'PAGE',$lang);
					
					return $img.$contents;
					break; // IMGTABLE

				case 'USERNAME':
					if ( $parse )
					{
						return '[USERNAME]';
					}
					else
					{
						return $GLOBALS['user']->realName;
					}
					break;

				case 'DISPLAYNAME':
					return $GLOBALS['course']->displayName;
					break;

				//case 'ERRORS':
				//	return displayErrors( '' );
				//	break;

				case 'PRE':
					return stripslashes( $contents );
					break;

				case 'F':
					$args = parseArgs( $args );
					$f = $args['args'][0];
					$c = sprintf( "%2.1f", ( $f - 32 ) * 5 / 9 );
					if ( isset($args['args'][1]) )
					{
						$f2 = $args['args'][1];
						$c2 = sprintf( "%2.1f", ( $f2 - 32 ) * 5 / 9 );
						return "$f&deg;F&ndash;$f2&deg;F ($c&deg;C&ndash;$c2&deg;C)";
					}
					else
						return "$f&deg;F ($c&deg;C)";
					break;					

					
				case 'INDEX':
				// INDEX gets handled by the skill module due to knowledge that only Skill has.
				break;

				case 'ERRORS':
				default:
					return stripslashes( stripslashes( $all ) );
					break;

				case 'NEXT':
					return $ci->sayings->input('Next','TYPE=BUTTON ONCLICK="pager.next()"');
					break;

				case 'LOGOUT':
					return $ci->sayings->input('Log Out','TYPE=BUTTON ONCLICK="login.logout()"');
					break;
					
			}
			break;
			
		case "QUESTION":
		// We're in a QUESTION environment
			switch ( strtoupper( $name ) )
			{
				case "ITEM":
					$args = parseArgs( $args );
					$ans = nl2br($args['options']['ANS']);
					$contents = stripslashes( stripslashes( $contents ) );
					$uniq = uniqid();

					return <<<FIN
						<TR>
							<TD WIDTH="3em" VALIGN="TOP">
								<INPUT TYPE="RADIO" NAME="1" onClick="pq_ans(this)" ID="{$uniq}">
								<SPAN STYLE="display: none;" ID="ans_{$uniq}">{$ans}</SPAN>
							</TD>
							<TD><LABEL FOR="{$uniq}">{$contents}</LABEL></TD>
						</TR>
FIN;
				break;
			}
			break;
	}
}

function parseArgs( $args )
{
	$args = stripslashes( trim ( $args ) );
	// Add a space so the parser knows the end of the last argument
	$args .= " ";

	$ret = array( 'args' => array(), 'options' => array() );

	$state = 'top';
	$symbol = '';
	$key = '';
	$delimiter = '';

	$letters = preg_split( '//', $args );

	for ( $i = 0; $i < count( $letters ); $i++ )
	{
		if ( $letters[$i] == "\\" )
		{
			$i++;
			$symbol .= $letters[$i];
			continue;
		}

		switch ( $state )
		{
		case 'top':
			if ( $letters[$i] == ' ' || $letters[$i] == "\t" ) 
				continue;
			else
			{
				$symbol .= $letters[$i];
				$state = 'sym';
			}
			break;

		case 'sym':
			if ( $letters[$i] == ' ' || $letters[$i] == "\t" )
			{
				$ret['args'][] = $symbol;
				$symbol = '';
				$state = 'top';
			}
			elseif ( $letters[$i] == '=' )
			{
				$key = $symbol;
				$symbol = '';
				$state = 'key';
			}
			else
				$symbol .= $letters[$i];
			break;

		case 'key':
			if ( ( $letters[$i] == '"' || $letters[$i] == "'" ) && $symbol == '' && !$delimiter )
			{
				$delimiter = $letters[$i];
			}
			elseif ( ( $delimiter && $letters[$i] == $delimiter ) || ( !$delimiter && ( $letters[$i] == ' ' || $letters[$i] == "\t" ) ) )
			{
				$ret['options'][ strtoupper( $key ) ] = $symbol;
				$state = 'top';
				$key = '';
				$delimiter = '';
				$symbol = '';
			}
			else
				$symbol .= $letters[$i];
			break;
		}
	}

	return $ret;
}



function oldparseArgs( $args )
{
	print "PARSE $args\n";
	$args = stripslashes( $args );
	$args = stripslashes( $args );

	// Add a space so the regex knows the end of the last argument
	$args .= " ";

	preg_match_all( $GLOBALS['argsRegEx'], $args, $match );

	for ( $i = 0; $i < count( $match[1] ); $i++ )
	{
		if ( $match[4][$i] )
		{
			$ret['options'][ strtoupper( $match[1][$i] ) ] = $match[4][$i];
		}
		else
		{
			$ret['args'][] = $match[1][$i];
		}
	}
	
	return $ret;
}

// Reverses parseArgs:  array( A =>1, B=>2 ) ==> ' A="1" B="2"'
function collapseOptions( $options )
{
	$out = '';
	
	if ( is_Array( $options ) )
	foreach( $options as $key => $value )
	{
		$out .= ' ' . strtoupper( $key ) . '="' . $value . '"';
	}

	return $out;
}

function rt_script( $text, $lang ) {

	$text = nl2br(trim($text));
	$text = preg_replace( "/^\s*-/m", "&mdash; ", trim($text) );

	$say = say( 'text', $lang );

	$uniq = uniqid();

	return <<<FIN
<P ALIGN=RIGHT><A HREF="javascript:script_{$uniq}();">{$say}</A></P>
<P ID={$uniq} CLASS="ui-state-highlight ui-corner-all" STYLE="display: none; padding: 10px;">$text</P>
<SCRIPT>
function script_{$uniq} () {
	\$J('#{$uniq}').show();
	pager.resize();

}
</SCRIPT>
FIN;
}

function rt_tellmemore( $text, $lang ) 
{

	if ( ! isset($GLOBALS['script']) )
	{
		$script = $GLOBALS['script'] = 1;
	}
	else
	{
		$script = ++$GLOBALS['script'];
	}

	$say = "Tell me more!";
	
	return <<<FIN
<SCRIPT  LANGUAGE="JavaScript">
function script{$script}() {
	document.getElementById('SCRIPT{$script}').className='script';
}
</SCRIPT>
<P><A HREF="javascript:script{$script}()">{$say}</A></P>
<DIV ID=SCRIPT{$script} CLASS="hide">$text</DIV>
FIN;
}

function rt_img( $args, $orig, $lang )
{
	$error = '';

	// These are what to prefix or suffix to the image name if the raw name isn't found
	$prefixes = array( '', strtoupper( $lang )  . '/' );
	$suffixes = array( '', '.JPG', '.jpg', '.gif', '.png' );

	$caption = '';
	if ( isset($args['options']['CAPTION']) ) {
		$caption = $args['options']['CAPTION'];
		unset( $args['options']['CAPTION'] );
	}

	$align = '';
	if ( isset($args['options']['ALIGN'])) {
		$align = $args['options']['ALIGN'];
		unset( $args['options']['ALIGN'] );
	}

	$width = '';
	if ( isset($args['options']['WIDTH'])) {
		$width = $args['options']['WIDTH'];
		unset( $args['options']['WIDTH'] );
	}

	$class = '';
	if ( isset($args['options']['CLASS'])) {
		$class = $args['options']['CLASS'];
		unset( $args['options']['CLASS'] );
	}


	$images = array();
	foreach ( $args['args'] as $img )
	{	
		$error .= "$img ";
		
		// The filename to try	
		$file = new File( $img );

		// Try each of the prefixes and suffixes to find the image
		foreach ( $prefixes as $prefix )
		foreach ( $suffixes as $suffix )
		{
			// Stop trying if we found one
			if ( $file->exists() ) break;
			
			$error .= 'images/' . $prefix . $img . $suffix . ' ';
			
			$file = new File( 'images/' . $prefix . $img . $suffix );
		}
			
		if ( !$file->exists() )
		{
			warn( "Image '$img' does not exist." );
			return isset($args['options']['ALT']) ? $args['options']['ALT'] : "[NOTE]{$img}[/NOTE]";
		}
		else
		{
			// Log the media file
			$GLOBALS['media'][] = $file->path;
			
			$images[] = "<IMG SRC='{$file->url()}' BORDER=0 WIDTH='100%' "
				. collapseOptions( $args['options'] )
				. ">";
		}
	}

	return '<SPAN STYLE="display: block; '.($width==''?'':'width: '.$width.'; ').'" '.($align==''?'':'ALIGN="'.$align.'" ').($class==''?'':'CLASS="'.$class.'" ').'>'.implode('<BR>',$images) . ( $caption ? '<BR><CENTER>' . $caption . '</CENTER>' : '' ).'</SPAN>';
}

?>
