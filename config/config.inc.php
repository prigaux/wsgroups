<?php

$DEBUG = 0;
$SEARCH_TIMELIMIT = 5; // seconds

$CAS_HOST = 'cas.univ.fr';
$CAS_CONTEXT = '/cas';
if (($_GET["CAS"] ?? '') === 'MFA') {
    $CAS_CONTEXT = '/otp';
    session_name('PHPSESSID_MFA');
}
$CA_certificate_file = '/usr/local/etc/ssl/certs/ca.crt';

$TRUSTED_IPS = array(
     // example:
     // '192.168.1.11',
);

$BASE_DN = "dc=univ-paris1,dc=fr";

// supann:
$PEOPLE_DN = "ou=people,".$BASE_DN;

$PEOPLE_ATTRS = array("uid" => "uid", "displayName" => "displayName", "supannEntiteAffectation" => "MULTI");

// someone having supannListeRouge=TRUE will be returned with all attrs anonymized except the following
$PEOPLE_LISTEROUGE_NON_ANONYMIZED_ATTRS = array('eduPersonAffiliation', 'eduPersonPrimaryAffiliation');

$AFFILIATIONS_PERSONNEL = array('staff', 'faculty', 'teacher');

// specific Paris1:
$UP1_ROLES_DN = "ou=roles,".$BASE_DN;

$CASv3_WRAPPER_AUTHORIZED_SERVICES = null; // regexp
$CASv3_WRAPPER_ALLOWED_ATTRS = [ "mail" => "mail", "displayName" => "displayName", "eduPersonPrincipalName" => "eduPersonPrincipalName" ];

$OUR_CASv3_URL = "https://casv3.univ.fr/cas";

?>
