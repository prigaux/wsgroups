<?php // -*-PHP-*-

require_once ('lib/common.inc.php');

$r = [];
if (GET_bool("CAS")) {
    initPhpCAS();
    if (phpCAS::checkAuthentication()) {
        $r['USER'] = phpCAS::getUser();
    }    
    $r['LOGIN_URL'] = "https://$CAS_HOST$CAS_CONTEXT/login";
}

echoJson($r);

?>

