<FORM ID="report_parameters" ONSUBMIT="return Control_Reports.submit_form();" TARGET="_blank"  METHOD=POST ACTION="<?=base_url();?>/control_reports/run/<?=$report;?>.csv">
<TABLE WIDTH="100%">
	<? for ( $i = 0; $i < ceil(count($parameters)/$columns)*$columns; $i++ ): ?>
		<? if ( $i % $columns == 0 ): ?>
		<TR>
		<? endif; ?>

		<TD WIDTH="<?=100/$columns?>%"><?=isset($parameters[$i]) ? $parameters[$i] : ''?></TD>

		<? if ( ($i+1) % $columns == 0 ): ?>
		</TR>
		<? endif; ?>
	<? endfor; ?>
</TABLE>
<P ALIGN=RIGHT ID=run_report_html><INPUT TYPE=BUTTON ONCLICK="javascript:Control_Reports.run('<?=$report?>');" VALUE="Run Report"></P>
<P ALIGN=RIGHT ID=run_report_csv><INPUT TYPE=SUBMIT  VALUE="Run CSV Report"></P>
</FORM>
<HR>
<DIV ID="report_view"></DIV>
<SCRIPT>
    $J('#run_report_csv').hide();
    $J('#csv_output').click(function () { 
        if ( $J(this).attr('checked') ) {
            $J('#run_report_html').hide();
            $J('#run_report_csv').show();
        } else {
            $J('#run_report_html').show();
            $J('#run_report_csv').hide();
        }
    }).change();
</SCRIPT>
