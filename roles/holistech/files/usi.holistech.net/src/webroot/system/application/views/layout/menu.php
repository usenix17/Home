<?
	$this->load->helper('form');
	$menu = array(
		'Programs' => $programs,
		'Views' => array(
			'Student Search' => '/students/search/',
			'Vendors\' Severities' => '/vendors/severities/',
			'Resource Prices' => '/resources/prices/',
			'Unsent Welcome Letters' => '/offerings/unsent_welcome_letters/'
			),
		'People' => $people,
		'Vendors' => $vendors,
		'Locales' => $locales,
		'New' => array(
			'Person' => '/people/add/0',
			'Vendor' => '/vendors/add/0',
			'Locale' => '/locales/add/0'
			),
		'Utilities' => array(
			'Log Out' => '/people/logout',
			'Test Code' => '/test/code',
			'Import CSV' => '/import/csv',
			'Edit Global Welcome Letter' => '/welcome_letters/edit/global',
			'Remove Locks' => '/locks/show',
			//'1. Reset DB' => '/import/reset',
			//'2. Import Vendors' => '/import/vendors',
			//'3. Import Hotel Info' => '/import/hotel_data',
			//'4. Import Meeting Rooms' => '/import/meetingrooms',
			//'5. Import Lecturers' => '/import/lecturers',
			//'6. Import Employees' => '/import/employees'
			//'Import Student Optout' => '/import/opt_out_student_data'
			//'Import Coordinators/Drivers' => '/import/coorditators_drivers'
			//'Import Vehicles' => '/import/vehicles'
		)
		);
?>
<DIV ID="accordion">
	<? foreach( $menu as $sub => $items ): ?>
		<DIV>
			<H3><A HREF="#"><?=$sub?></A>

				<TABLE WIDTH="100%" ALIGN=CENTER CLASS=search>
				<TR><TD>Find: </TD>
				<TD WIDTH="100%"><INPUT NAME="<?=$sub?>" ONKEYUP="searchMenu('<?=$sub?>');" 
					TYPE=TEXT STYLE="width:100%"></TD>
				</TR>
				<? if ( $sub == 'Vendors' ): ;?>
				<TR><TD>Filter: </TD>
				<TD WIDTH="100%">
					<?= form_dropdown("{$sub}_filter", $resource_types, ',', 
						"ONCHANGE=\"searchMenu('{$sub}');\" STYLE='width:100%'")?>
				</TD>
				</TR>
				<? endif; ?>
				</TABLE>

			</H3>
			<DIV>
				<UL NAME="<?=$sub?>">
				<? if ( is_array( $items ) ): foreach( $items as $name => $url ): ?>
				<?
				$types = '';
				$extra = '';
				if ( is_array($url) )
				{
					$types = $url[1];
					if ( isset($url[2]) )
						$extra = $url[2];
					$url = $url[0];
				}
				?>
				<? if ( $name ): ?>
				<LI NAME="<?=md5($url);?>" URL="<?=$url?>" 
					ONCLICK='javascript:menuChoose("<?=md5($url);?>")'>
					<SPAN STYLE="display: none" CLASS="search"><?=strtoupper($name.$extra);?></SPAN>
					<SPAN STYLE="display: none" CLASS="filter"><?=$types;?></SPAN>
					<?=$name?>
				</LI>
				<? else: ?>
				<HR>
				<? endif; ?>
				<? endforeach; endif; ?>
			</UL></DIV>
		</DIV>
	<? endforeach; ?>
</DIV>
