function Control_Reports() {}

Control_Reports.show = function(report)
{
	load('/control_reports/show/'+escape(report),'#report');
}

Control_Reports.run = function(report)
{
	data = $J('#report_parameters').serialize();
	load('/control_reports/run/'+escape(report),'#report_view',data);
}

Control_Reports.csv_switch = function ()
{
}

Control_Reports.submit_form = function ()
{
    if ( $J('#csv_output').attr('checked') )
        return true;

    return false;
}   
