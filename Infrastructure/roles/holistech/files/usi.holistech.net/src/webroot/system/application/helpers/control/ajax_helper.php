<?
//////////////////////////////////////////////////////////////////////
//
//	ajax.php
//	Jason Karcz
//	Terminal for ajax command interface 
//
//////////////////////////////////////////////////////////////////////
//
//	3 September 2008 - Created
//
//////////////////////////////////////////////////////////////////////

// Create a new instance to get the ball rolling
new ajaxCPL();

class ajaxCPL extends ControlPanelApplet
{
	// Instance Variables
	var $title     = array( 'su'       => array( 'EN' => 'AJAX Terminal',	 'ES' => 'AJAX Terminal',		'ZH' => 'AJAX Terminal' )
		      	      );
	var $name      = 'ajax';
	var $userLevel = 'su';

	function display( $stage )
	{
		if ( !$GLOBALS['user']->hasType( $this->userLevel ) )
		{
			error( "You do not have permission to use this function (nice try, though.)" );
			return;
		}
			
		return <<<HTMLFIN
<script>
function submitForm()
{ 
    var xhr; 
    try {  xhr = new ActiveXObject('Msxml2.XMLHTTP');   }
    catch (e) 
    {
        try {   xhr = new ActiveXObject('Microsoft.XMLHTTP');    }
        catch (e2) 
        {
          try {  xhr = new XMLHttpRequest();     }
          catch (e3) {  xhr = false;   }
        }
     }
  
    xhr.onreadystatechange  = function()
    { 
         if(xhr.readyState  == 4)
         {
              if(xhr.status  == 200) 
                  document.getElementById('ajaxreturn').innerHTML = xhr.responseText; 
         }
    }; 

   url = "index.php?action=ajax&command=" + document.forms[0].command.value;
   xhr.open("GET", url,  true); 
   xhr.send(null); 
} 
</script>

<FORM method="POST" name="ajax" action="">                  
 <INPUT type="text" name="command" size=150 value=""><P>
 <INPUT type="BUTTON" value="Execute"  ONCLICK="submitForm()">
</FORM>

<DIV ID="ajaxreturn"></DIV>

HTMLFIN;

	}
}
?>
