<?
    // Initialize
    $course = getCourse(COURSENAME);
    $roster_file = $course->roster_file;
    $roster_report_restrict_fields = explode(',', $course->roster_report_restrict_fields);

    if ( trim($course->roster_report_filter_fields) ) {
        $parameters_tmp_file = '/tmp/roster_tmp_'.md5($roster_file.$course->roster_report_filter_fields);

        try {
            // Ensure roster_file is sane
            if ( $roster_file == '' ) {
                throw new Exception('No "Roster File" specified for course.');
            } elseif ( ! file_exists($roster_file) ) {
                throw new Exception('Roster File specified for course does not exist.');
            } else {

                // Initialize data structure for parameters from this roster 
                $additional_parameters = Array();

                // Determine if $parameters_tmp_file exists and get modification times
                $roster_file_mtime = filemtime($roster_file);
                $parameters_tmp_file_mtime = FALSE;
                if ( file_exists($parameters_tmp_file) )
                    $parameters_tmp_file_mtime = filemtime($parameters_tmp_file);

                // Determine if parameters temp file is out-of-date
                if ( ! $parameters_tmp_file_mtime || $parameters_tmp_file_mtime < $roster_file_mtime ) {
                
                    // Update the parameters temp file
                    
                    // Initialize
                    $parameter_fields = Array();

                    // Attempt to open the roster file
                    if ( ($handle = fopen($roster_file, "r")) !== FALSE ) {

                        // Get header from CSV 
                        $header_row = fgetcsv($handle);

                        // Find the names and indicies of all fields to filter on
                        foreach ( explode(',', $course->roster_report_filter_fields) as $field_name ) {
                            $field_name = trim($field_name);
                            $field_index = array_search($field_name,$header_row);

                            if ( $field_index === FALSE ) 
                                throw new Exception("Filter field '{$field_name}' not found in roster file.");

                            $parameter_fields[] = Array(   
                                'index' => $field_index,
                                'name' => $field_name, 
                                'values' => Array()
                            );
                        }
                    
                        // Search through each row of the roster
                        while ( ($data = fgetcsv($handle)) !== FALSE ) {
                            // Add the value of each filter field to the data structure 
                            for ( $i = 0; $i < count($parameter_fields); $i++ ) {
                                $value = $data[$parameter_fields[$i]['index']];
                                $parameter_fields[$i]['values'][] = $value;
                            }
                        }

                        fclose($handle);
                        
                        // Sort values
                        for ( $i = 0; $i < count($parameter_fields); $i++ ) {
                            $parameter_fields[$i]['values'] = array_unique($parameter_fields[$i]['values']);
                            sort($parameter_fields[$i]['values']);
                        }

                        // Save to temp file
                        if ( ($handle = fopen($parameters_tmp_file, 'w')) !== FALSE ) {
                            fwrite($handle, serialize($parameter_fields));
                        }


                    } else {
                        throw new Exception('Could not open roster file.');
                    }
                } else {
                    // Use the parameters temp file since it's up-to-date

                    if ( ($handle = fopen($parameters_tmp_file, "r")) !== FALSE ) {
                        $data = fread($handle, filesize($parameters_tmp_file));
                        $parameter_fields = unserialize($data);
                    } else {
                        throw new Exception('Could not open parameters file.');
                    }
                }

                // Create parameter HTML
                $i = 0;
                foreach ( $parameter_fields as $field ) {
                    $parameter =  "<B>Filter on \"{$field['name']}\" field</B>:<BR> <SELECT NAME='filter_{$i}'><OPTION VALUE=''>--Show All--</OPTION>";
                    foreach ( $field['values'] as $value ) {
                        
                        if ( $value == '' ) 
                            continue;

                        // Restrict if necessary
                        $show_parameter = TRUE;
                        if ( in_array($field['name'], $roster_report_restrict_fields) ) {
                            if ( ! $GLOBALS['user']->has(new Token('roster_auth',"{$field['name']}={$value}",COURSENAME)) ) 
                                $show_parameter = FALSE;
                        }

                        if ( $show_parameter )
                            $parameter .= '<OPTION VALUE="'.base64_encode($value).'">'.$value.'</OPTION>';
                    }
                    $parameter .= '</SELECT>';

                    $parameters[] = $parameter;
                    $i++;
                }
            }
        } catch (Exception $e) {
           echo '<H1>Error: '.$e->getMessage().'</H1>';
        }
    }
?>
