<?php if ( ! defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * Formtable helper
 *
 * @author Jason Karcz
 */
class Formtable
{

	public static function row($title,$field,$type='input',$data=NULL,$separator=' ')
	{
		$out = '';
		$error = FALSE;

		$out .= "<TH>{$title}</TH><TD>";

		list ($field,$error) = Formtable::_field($field,$type,$data,$separator);

		if ( $error )
		{
			$out = "<TR CLASS='error1'><TD COLSPAN=2>{$error}</TD></TR><TR CLASS='error2'>".$out.$field;
		}
		else
		{
			$out = "<TR>".$out.$field;
		}

		return $out.'</TD></TR>';
	}

	public static function paperForm($array)
	{
		$top = '';
		$bottom = '';

		foreach ( $array as $f )
		{
			if ( is_array($f) )
			{
				list($field,$error) = Formtable::_field(
					$f[1],
					(isset($f[2]) ? $f[2] : 'input'),
					(isset($f[3]) ? $f[3] : NULL),
					(isset($f[4]) ? $f[4] : ' '));

				if ( $error )
					$error = 'CLASS=error';
				else
					$error = '';

				$top .= "<TD {$error}>{$field}</TD>";
				$bottom .= "<TD {$error}>{$f[0]}</TD>";
			}
			else
				$top .= $f;
		}

		return "<TR>{$top}</TR><TR>{$bottom}</TR>";
	}

	public static function field($field,$type='input',$data=NULL,$separator=' ')
	{
		list($field,$error) = Formtable::_field($field,$type,$data,$separator);
		return $field;
	}

	private static function _field($field,$type,$data,$separator)
	{
		$ci =& get_instance();
		$ci->load->helper('form');
		$ci->load->helper('phone');

		$out = '';
		$error = '';

		switch( strtoupper($type) )
		{
			case 'DATE':
				$date = set_value($field);
				if ( preg_match('/[^0-9]/',$date) )
					$date = strtotime($date);
				$date = ($date ? date('n/j/Y',$date) : '' );
				$out .= form_input($field,$date,$data);
				$error = form_error($field);
			break;

			case 'TIME':
				$time = set_value($field);
				$ampm = set_value($field.'_ampm');
				if ( is_numeric(set_value($field)) )
				{
					$time = date('g:i',set_value($field));
					$ampm = date('A',set_value($field));
				}
				elseif ( preg_match('/(.*)([AP]M)$/i',$time,$match) )
				{
					$time = $match[1];
					$ampm = strtoupper($match[2]);
				}
				$out .= form_input($field,$time,$data);
				$out .= form_dropdown($field.'_ampm',array(''=>'','AM'=>'AM','PM'=>'PM'),
					$ampm);
				$error = form_error($field);
			break;

			case 'DATETIME':
				$times = Formtable::times();
				$out .= form_input($field[0],date('n/j/Y',set_value($field[0])),$data).' at ';
				$out .= form_dropdown($field[1],$times,date('g:i',set_value($field[1])));
				$out .= form_dropdown($field[1].'_ampm',array(''=>'','AM'=>'AM','PM'=>'PM'),
					date('A',set_value($field[1])));
			break;

			case 'INPUT':
				$out .= form_input($field,set_value($field),$data);
				$error = form_error($field);
			break;

			case 'CHECKBOX':
				$out .= form_checkbox(array(
					'name' => $field,
					'value' => 1,
					'checked' => set_value($field)==1
				));
				$error = form_error($field);
			break;

			case 'PHONE':
				//$types = Formtable::phone_types();
				//$out .= form_dropdown($field[0],$types,set_value($field[0])).' ';
				$out .= form_input($field,Phone::format(set_value($field)));
				//$out .= ' x'.form_input($field[2],set_value($field[2]),'size=5');
				$error = form_error($field);
			break;

			case 'PRICE':
				$val = preg_replace('/[^\d.]/','',set_value($field));
				$val = ( $val ? sprintf('$%.2f',$val) : '' );
				$out .= form_input($field,$val,$data);
				$error = form_error($field);
			break;

			case 'STATE':
				$data = Formtable::states();
			case 'DROPDOWN':
				$out .= form_dropdown($field,$data,set_value($field));
				$error = form_error($field);
			break;

			case 'SEARCHABLE_DROPDOWN':
				$uniq = uniqid();
				//$out .= "<SELECT NAME='{$field}' CLASS='{$uniq}'>";
				//foreach ( $data as $k => $v )
				//{
				//	$out .= "<OPTION VALUE='{$k}' ALT='".strtoupper($v)."'>{$v}</OPTION>";
				//}
				//$out .= "</SELECT>";
				$out .= form_dropdown($field,$data,set_value($field),'CLASS="'.$uniq.'"');
				$out .= ' <SPAN STYLE="display: none;" CLASS="'.$uniq.'"></SPAN>';
				$out .= '<INPUT ID="'.$uniq.'" TYPE=TEXT>';
				$out .= <<<HTML
					<SCRIPT>
						\$J('#{$uniq}').keyup(function () {
							if ( \$J(this).val() )
							{
								\$J('.{$uniq} OPTION').appendTo(\$J('SPAN.{$uniq}'));
								\$J('.{$uniq} OPTION:contains('+\$J(this).val()+')')
									.appendTo(\$J('SELECT.{$uniq}'));
							}
							else
								\$J('.{$uniq} OPTION').appendTo(\$J('SELECT.{$uniq}'));

						});
					</SCRIPT>
HTML;
						
				$error = form_error($field);
			break;

			case 'PASSWORD':
				$out .= form_password($field);
				$error = form_error($field);
			break;

			case 'DATE_OFFSET':
					$offset = $_POST[$field]; // offset in seconds
				$s = $offset % 60;
					$offset = (($offset-$s)/60); // offset in minutes
				$m = $offset % 60;
					$offset = (($offset-$m)/60); // offset in hours
				$h = $offset % 24;
				$d = (($offset-$h)/24);
				
				$out .= form_input($field.'_days',$d,"SIZE=2").' days, '
					.form_input($field.'_hours',$h,"SIZE=2").' hours, '
					.form_input($field.'_minutes',$m,"SIZE=2").' minutes';
				break;

			case 'RADIO':
				$value = set_value($field);
				$uniq = uniqid();

				foreach ( $data as $k => $v )
				{
					$out .= form_radio(array(
						'name' => $field,
						'id' => $uniq.'_'.$field.'_'.$k,
						'value' => $k,
						'checked' => $value == $k
					));
					$out .= " <LABEL FOR='{$uniq}_{$field}_{$k}'>$v</LABEL>".$separator;
				}
				break;
		}

		return array($out,$error);
	}

	static function states()
	{
		return array(
			'' => '',
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AS' => 'American Samoa',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District Of Columbia',
			'FM' => 'Federated States Of Micronesia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'GU' => 'Guam',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MH' => 'Marshall Islands',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'MP' => 'Northern Mariana Islands',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PW' => 'Palau',
			'PA' => 'Pennsylvania',
			'PR' => 'Puerto Rico',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VI' => 'Virgin Islands',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
			'AB' => 'Alberta',
			'BC' => 'British Columbia',
			'MB' => 'Manitoba',
			'NB' => 'New Brunswick',
			'NL' => 'Newfoundland and Labrador',
			'NT' => 'Northwest Territories',
			'NS' => 'Nova Scotia',
			'NU' => 'Nunavut',
			'ON' => 'Ontario',
			'PE' => 'Prince Edward Island',
			'QC' => 'Quebec',
			'SK' => 'Saskatchewan',
			'YT' => 'Yukon'
		);
	}

	static function phone_types()
	{
		return array(
			'Home' => 'Home',
			'Work' => 'Work',
			'Cell' => 'Cell',
			'Fax' => 'Fax',
			'Assistant' => 'Assistant',
			'Other' => 'Other'
		);
	}

	static function times()
	{
		$out = array();

		for ( $h = 0; $h < 12; $h++ )
			for ( $m = 0; $m < 60; $m += 15 )
			{
				$time = sprintf("%d:%02d", $h==0 ? 12 : $h, $m );
				$out[$time] = $time;
			}

		return $out;
	}
}
