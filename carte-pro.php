<?php // -*-PHP-*-

require_once ('config/config.inc.php');
require_once ('lib/supannPerson.inc.php');


$CARTE_ETU_ALLOWED_ATTRS = [
    'sn' => 'sn', 'givenName' => 'givenName',
    'supannEmpId' => 'supannEmpId',
    'employeeType' => 'employeeType',
    'eduPersonPrimaryAffiliation' => 'eduPersonPrimaryAffiliation',
    'up1Profile' => 'MULTI',
    "supannCMSAppAffectation" => "MULTI",
];


initPhpCAS();
if (!phpCAS::checkAuthentication()) {
    echoJson([ "error" => "Unauthorized", "cas_login_url" => 'https://' . $GLOBALS['CAS_HOST'] . $GLOBALS['CAS_CONTEXT'] . '/login' ]);
    exit(0);
}
$uid = phpCAS::getUser();

// to access supannCodeINE, employeeNumber
$LDAP_CONNECT = $GLOBALS['LDAP_CONNECT_LEVEL2'];

$impersonate = GET_or_NULL("uid");
if ($impersonate) {
    if (existsLdap($GLOBALS['PEOPLE_DN'], "(&(uid=$uid)" . $GLOBALS['LEVEL1_FILTER'] . ")")) {
        $uid = $impersonate;
    } else {
        error_log("not allowing $uid to impersonate");
    }
}

$attrs = getFirstLdapInfo($PEOPLE_DN, "(&(uid=$uid)(employeeNumber=*)(supannEntiteAffectationPrincipale=*))", $CARTE_ETU_ALLOWED_ATTRS);

if (!$attrs) {
    echoJson([ "error" => "Unauthorized" ]);
    exit(0);
}

foreach (getAndUnset($attrs, 'supannCMSAppAffectation') ?? [] as $cms_s) {
    $cms = parse_composite_value($cms_s);
    if ($cms["type"] === "personnel" && $cms["domaine"] === "barcode.p1ps.fr" && $cms["valide"] === "vrai") {
        $attrs['employeeNumber'] = $cms["id"];
    }
}

$profiles = getAndUnset($attrs, 'up1Profile');
if ($profiles) {
    foreach ($profiles as $profile) {
        if (!preg_match('/^\[up1Source={HARPEGE}(\w+)/', $profile, $m)) continue;
        $pro_profile = $m[1];
        if ($pro_profile === 'carriere') {
            $attrs['statut'] = 'Fonctionnaire';
            break;
        }
        if ($pro_profile === 'contrat') {
            $attrs['statut'] = getAndUnset($attrs, 'employeeType');
        }
        if ($pro_profile === 'heberge') {
            if (in_array($attrs['employeeType'], ['Chargé de recherche', 'Directeur de recherche'])) {
                $attrs['statut'] = "Chercheur statutaire";
            } else if (in_array($attrs['employeeType'], ['Professeur émérite', 'Professeur invité', 'Personnel EPST/'])) {
                $attrs['statut'] = getAndUnset($attrs, 'employeeType');
            } else {
                getAndUnset($attrs, 'employeeType');
                $attrs['statut'] = $attrs['eduPersonPrimaryAffiliation'] === 'researcher' ? 'Chercheur' : 'Personnel hébergé';
            }
        }
    }
}

if (!$attrs || !$attrs['employeeNumber']) {
    echoJson([ "error" => "Unauthorized" ]);
    exit(0);
}

echoJson($attrs);

?>
