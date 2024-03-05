<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

$id = GET_ldapFilterSafe_or_NULL("id");
if ($id !== NULL) {
    // NB: "id" can be an array, that's ok & useful!
    $token = $id;
    $tokenIsId = true;
} else {
    $token = GET_ldapFilterSafe_or("token", '');
    $tokenIsId = false;
}
$attrs = GET_or_NULL("attrs");
$format = GET_or_NULL("format");
$anonymous = !(@$isTrustedIp || GET_uid());
$maxRows = GET_or("maxRows", 0); // but see restrictions if !$isTrustedIp
$showErrors = GET_or_NULL("showErrors");
$showExtendedInfo = GET_or_NULL("showExtendedInfo");
$allowInvalidAccounts = GET_or_NULL("allowInvalidAccounts");
$allowNoAffiliationAccounts = GET_or("allowNoAffiliationAccounts", $allowInvalidAccounts);
$allowRoles = GET_or_NULL("allowRoles"); # allow searching non-people entries

$extendedInfo = $allowExtendedInfo = $anonymous ? -1 : (@$isTrustedIp ? 2 : 0);
if (isset($showExtendedInfo) && GET_uid()) {
  if (GET_or_NULL('CAS') !== 'MFA') fatal("showExtendedInfo requires CAS=MFA");
  $extendedInfo = $allowExtendedInfo = loggedUserAllowedLevel();
}
if (isset($allowInvalidAccounts) && GET_uid()) {    
  $allowExtendedInfo = loggedUserAllowedLevel();
}

if ($allowExtendedInfo >= 1) {
  if (is_numeric($showExtendedInfo)) {
      $extendedInfo = min($extendedInfo, $showExtendedInfo);
  }
  if (!@$isTrustedIp) {
      global $LDAP_CONNECT_LEVEL1, $LDAP_CONNECT_LEVEL2;
      $LDAP_CONNECT = $extendedInfo == 2 ? $LDAP_CONNECT_LEVEL2 : $LDAP_CONNECT_LEVEL1;
      global_ldap_open('reOpen');
  }
}

$restriction = GET_extra_people_filter_from_params();
$wanted_attrs = people_attrs($attrs, $extendedInfo);

$attrRestrictions = attrRestrictions($extendedInfo);
$attrRestrictions['allowRoles'] = $allowRoles;

if ($attrRestrictions['forceProfile']) {
    $wanted_attrs['up1Profile'] = 'MULTI';
}

if (!@$isTrustedIp) {
    # NB: even for "allowListeRouge", limit the number of results
    $max_maxRows = GET_uid() && $attrRestrictions['allowListeRouge'] ? 1000 : 10;
    $maxRows = $maxRows > 1 ? min($maxRows, $max_maxRows) : $max_maxRows;
}

global $USER_KEY_FIELD;
$users = searchPeople(people_filters($token, $restriction, $allowInvalidAccounts, $allowNoAffiliationAccounts, $tokenIsId), $attrRestrictions, $wanted_attrs, $USER_KEY_FIELD, $maxRows);

if (count($users) > 0 && GET_uid()) {
    $users[0]['globalInfo'] = [ "maxRows" => $maxRows, "allowExtendedInfo" => $allowExtendedInfo ];
}

if ($format === 'vcard') {
    require_once 'lib/vcard.inc.php';
    echo_vcard($users);
} else {
    echoJson($users);
}

?>
