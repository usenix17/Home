#!/usr/bin/env python

import sys

if len(sys.argv) != 3:
    sys.stderr.write('Usage: %s <csv_file> <xml_file>\n\nConverts CSV permissions file to XML.  For more details, see doc for CH0002\n' % sys.argv[0])
    sys.exit(1)
    
this_script, csv_filename, xml_filename = sys.argv
this_script = this_script.split('/')[-1]

import csv
import xml.etree.ElementTree as ET

tokens = ET.parse(xml_filename).getroot()

for element in tokens:
    if element.attrib.get('autogen') == this_script:
        tokens.remove(element)

with open(csv_filename) as csv_file:
    csv_reader = csv.reader(csv_file)

    for row in csv_reader:
        user = ET.Element('user', {'name':'CAS::%s' % row[0], 'autogen': this_script})
        user.append(ET.Element('auth', {'key':'view_reports', 'scope':'*'}))
        user.append(ET.Element('auth', {'key':'rep:Roster Report', 'scope':'*'}))

        if len(row) == 1:
            user.append(ET.Element('roster_auth', {'key':'*', 'scope':'*'}))
        else:
            for dept_code in row[1:]:
                user.append(ET.Element('roster_auth', {'key':'Dept=%s' % dept_code, 'scope':'*'}))

        tokens.append(user)

with open(xml_filename, 'w') as output_file:
    output_file.write( '<?xml version="1.0"?>' )
    output_file.write( ET.tostring(tokens) )

