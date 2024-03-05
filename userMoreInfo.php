<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

$uid = GET_ldapFilterSafe("uid");
$info = GET_ldapFilterSafe_or("info", '*');
$type = GET_or("type", "user");
if (!in_array($type, ["user", "role"])) exit("invalid user type $type");

$need_level1 = [ 'auth', 'folder', 'mailbox' ];
$wanted_level = array_diff(explode(",", $info), $need_level1) ? 2 : 1;

forceCASAuthentication();
if (GET_or_NULL('CAS') !== 'MFA' || loggedUserAllowedLevel() < $wanted_level) {
  header("HTTP/1.0 403 Forbidden");
  exit;
}

exec("ssh wsgroups@marmite.univ-paris1.fr getinfo $type '$info' $uid", $lines);

echoJson(json_decode(implode('', $lines)));

?>
