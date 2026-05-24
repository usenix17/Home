Real Name, Username, Email, Enrollment Date, Certification Date, Has Certified
<?  foreach ( $result as $row ) {
    print implode(',', array(
        '"'.$row['realName'].'"',
        '"'.(substr($row['username'],0,5) == 'CAS::' ? substr($row['username'],5) : $row['username']).'"',
        '"'.$row['email'].'"',
        '"'.date('jMy G:i',strtotime($row['date'])).'"',
        $row['certification_time'] ? '"'.date('jMy G:i',strtotime($row['certification_time'])).'"' : '', 
        $row['certification_time'] ? 1 : 0,
    ))."\n";
}
