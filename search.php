<?php // -*-PHP-*-

require_once ('lib/groups.inc.php');
require_once ('lib/supannPerson.inc.php');

if (GET_bool("CAS")) forceCASAuthentication();

$token = GET_ldapFilterSafe("token");
$user_attrs = GET_or_NULL("user_attrs");
$group_attrs = explode(',', GET_or_NULL("group_attrs"));
$maxRows = GET_uid() ? GET_or("maxRows", 0) : min(max(1, GET_or_NULL("maxRows")), 10);
$kinds = explode(',', GET_or("kinds", "users,groups"));
$restriction = GET_extra_people_filter_from_params();
$groupRestriction = GET_extra_group_filter_from_params();

$r = [];

foreach (['supannRoleGenerique', 'supannActivite', 'eduPersonAffiliation'] as $table) {
  if (in_array($table, $kinds)) {
    $filters = ["(up1TableKey=*)"];
    if ($table === 'supannRoleGenerique') $filters[] = '(up1Flags={PRIO}*)'; // only "important" ones
    if ($table === 'supannActivite') $filters[] = '(&(|(up1TableKey={UAI:0751717J:ACT}*)(up1TableKey={UAI:0751717J:RIFSEEP}*))(!(businessCategory=management)))'; // only "important" ones. NB : on ignore les RIFSEEP en doublon des roles (GLPI UP1#163275)
    if ($token) $filters[] = ldapOr(["(displayName=*$token*)", "(cn=*$token*)"]);    
    $filter = array_merge($token ? ["(up1TableKey=$token)"] : [], [ldapAnd($filters)]);
    $r[$table] = getLdapInfoMultiFilters("ou=$table,ou=tables,$BASE_DN", $filter, array('up1TableKey' => "key", "displayName" => "name"), "key", $maxRows === 1 ? 1 : 0);
  }
}

if (in_array('users', $kinds)) {
$wanted_user_attrs = people_attrs($user_attrs);

$attrRestrictions = attrRestrictions();

global $USER_KEY_FIELD;
$r['users'] = searchPeople(people_filters($token, $restriction), $attrRestrictions, $wanted_user_attrs, $USER_KEY_FIELD, $maxRows);
}

if (in_array('groups', $kinds)) {
$r['groups'] = searchGroups($token, $maxRows, $groupRestriction, $group_attrs);
}

echoJson($r);

?>
