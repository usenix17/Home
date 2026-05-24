#!/bin/bash

if [[ $@ == "-c scp -t ferpa_roster.csv" ]]
then
	scp -t ferpa_roster.csv
	chmod 644 ferpa_roster.csv
fi

if [[ $@ == "-c scp -t roster_permissions.csv" ]]
then
	scp -t roster_permissions.csv
	./dept_code_csv_to_xml.py roster_permissions.csv /var/www/vhosts/usi.holistech.net/tokens.xml
fi
