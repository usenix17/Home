<?
/**
* is_ajax_call
*
* Determines if the current page request is done through an AJAX call
*
* @access    public
* @param    void
* @return    boolean
*/
if ( ! function_exists('is_ajax_call'))
{
    function is_ajax_call()
    {
        if ( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest")
        {
            // we should do more checks here, for now this is it...
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }
} 
/**
* Session Class Extension
*/
class MY_Session extends CI_Session
{
    /**
     * Do not update an existing session on ajax calls
     *
     * @access    public
     * @return    void
     */
    function sess_update()
    {
                if ( ! is_ajax_call() )
                {
                        parent::sess_update();
                }
    }
} 
