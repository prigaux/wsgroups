<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

forceCASAuthentication();
if (!isPersonMatchingFilter(GET_uid(), $LEVEL1_FILTER) &&
    !isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER)) {
  header("HTTP/1.0 403 Forbidden");
  exit;
}

$ids = implode(' ', explode(',', GET_ldapFilterSafe("ids")));
$info = GET_ldapFilterSafe_or("info", '*');

exec("ssh wsgroups@marmite.univ-paris1.fr getinfo group '$info' $ids", $lines);

echoJson(json_decode(implode('', $lines)));

?>
