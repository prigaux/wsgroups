<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

forceCASAuthentication();
if (!isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER)) {
  header("HTTP/1.0 403 Forbidden");
  exit;
}

$login = strtolower(GET_ldapFilterSafe("login"));
$mail = strtolower(GET_ldapFilterSafe("mail"));

exec("(echo $login; echo $mail) | ssh userinfo@cas3.univ-paris1.fr", $lines);
array_shift($lines);
$audit_boundary_dates = json_decode(array_shift($lines));

$list = array();
array_shift($lines);
while ($line = array_shift($lines)) {
    if (preg_match('/^#/', $line)) break;
    $e = json_decode($line, true);
    $action = getAndUnset($e, 'action');
    if ($action === 'AUTHENTICATION_FAILED') {
        $e['error'] = $action;
    }
    $list[] = $e;

}
# all remaining $lines are "similar login failures"
$fuzzy_failed = array_map('json_decode', $lines);

$since = [ $audit_boundary_dates[0] ];


# sort by date (needed since it is an aggregation of login logs + mail logs)
usort($list, function ($a, $b) { return strcmp($a["when"], $b["when"]); });


echoJson(array("since" => $since, "list" => $list, "fuzzy_failed" => $fuzzy_failed));


?>
