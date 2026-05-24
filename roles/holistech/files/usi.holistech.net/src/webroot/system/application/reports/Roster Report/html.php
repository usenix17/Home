<H2>Roster Report</H2>
<?
    $course = getCourse(COURSENAME);
    $roster_file = $course->roster_file;
    $roster_username_field = $course->roster_username_field;
    $roster_report_restrict_fields = explode(',', $course->roster_report_restrict_fields);

    try {
        if ( $roster_file == '' ) {
           throw new Exception("No 'Roster File' specified for course.");
        } elseif ( ! file_exists($roster_file) ) {
            throw new Exception('Roster File specified for course does not exist.');
        } else {
            $out = "<CENTER><TABLE CLASS='ticTacToe'>";

            if (($handle = fopen($roster_file, "r")) !== FALSE) {
                // Create result lookup table to find enrollment and certification dates
                $result_lookup = Array();
                foreach ( $result as $row ) {
                    $row['username'] = substr($row['username'],0,5) == 'CAS::' ? substr($row['username'],5) : $row['username'];
                    $result_lookup[$row['username']] = $row; // There may be multiple rows per username.  Last one wins.  Ascending date order means last is newest.
                }

                // Get header from CSV and echo header row
                $header_row = fgetcsv($handle);
                $header_row[] = 'Enrollment Date';
                $header_row[] = 'Certification Date';
                $out .= "<TR><TH>".implode('</TH><TH>',$header_row)."</TH></TR>";
                $roster_username_field_index = array_search($roster_username_field,$header_row);

                // Only proceed if we know how to correlate our usernames with the fields in the roster file
                if ( $roster_username_field_index === FALSE ) {
                    throw new Exception("Could not find field '".$roster_username_field."' in first row of roster file.");
                } else {

                    // Initialize filter fields
                    $filter_fields = Array();

                    // Find the names and indicies of all fields to filter on
                    if ( trim($course->roster_report_filter_fields) ) {
                        $i = 0;
                        foreach ( explode(',', $course->roster_report_filter_fields) as $field_name ) {
                            $field_name = trim($field_name);
                            $field_index = array_search($field_name,$header_row);

                            if ( $field_index === FALSE ) 
                                throw new Exception("Filter field '{$field_name}' not found in roster file.");

                            $value = $this->input->post('filter_'.$i);
                            if ( $value ) {
                                $filter_fields[] = Array(   
                                    'index' => $field_index,
                                    'name' => $field_name, 
                                    'value' => base64_decode($value),
                                );
                            }
                            $i++;
                        }
                    }

                    // Report each row of the roster file, trying to fill in enrollment and certification dates
                    while (($data = fgetcsv($handle)) !== FALSE) {

                        // Filter out if necessary
                        $filter_out = FALSE;
                        foreach ( $filter_fields  as $field ) {
                            if ( $data[$field['index']] != $field['value'] )
                                $filter_out = TRUE;
                        }
                        
                        # Restrict if necessary
                        foreach ( $roster_report_restrict_fields as $field_name ) {
                            $field_name = trim($field_name);
                            $field_index = array_search($field_name,$header_row);
                            if ( ! $GLOBALS['user']->has(new Token('roster_auth',"{$field_name}={$data[$field_index]}",COURSENAME)) ) {
                                $filter_out = TRUE;
                            }
                        }

                        if ( $filter_out )
                            continue;

                        # Calculate enrollment and certification dates
                        $username = $data[$roster_username_field_index];
                        if ( array_key_exists($username, $result_lookup) ) {
                            $user_data = $result_lookup[$username];
                            $data[] = date('jMy G:i',strtotime($user_data['date']));
                            $data[] = $user_data['certification_time'] ? date('jMy G:i',strtotime($user_data['certification_time'])) : '';
                        } else {
                            $data[] = '';
                            $data[] = '';
                        }

                        $out .= "<TR><TD>".implode('</TD><TD>',$data)."</TD></TR>";
                    }
                }

                fclose($handle);
            } else {
                throw new Exception('Could not oper roster file.');
            }

            $out .= '</TABLE></CENTER>';

            echo $out;
        }
    } catch ( Exception $e ) {
        echo '<H1>Error: '.$e->getMessage().'</H1>';
    }
?>
