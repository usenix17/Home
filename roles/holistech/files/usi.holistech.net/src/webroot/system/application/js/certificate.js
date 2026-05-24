function certificate() {}
certificate.certify = function () {
	load('/certificate/certify/','#HIDDEN',$J('#certificate_verify_name INPUT').serialize());
}
