<?php

require_once ('lib/common.inc.php');
if (!isset($GLOBALS["structureKeyToAll"])) require_once ('gen/tables.inc.php');
require_once ('config/config-groups.inc.php'); // in case groups.inc.php is used (php files setting global variables must be required outside a function!)

// cf slapd conf
const index_substr_if_minlen = 2;
const index_substr_any_len = 4;

global $USER_KEY_FIELD, $USER_ALLOWED_ATTRS;
$USER_KEY_FIELD = 'uid';
$attrs_by_kind = [
  "MONO -1" => [
    'uid', 'mail', 'displayName', 'cn', 'eduPersonPrimaryAffiliation', 
	'postalAddress', 'eduPersonPrincipalName',
	'sn', 'givenName',
    'supannEntiteAffectationPrincipale', 'supannEntiteAffectationPrincipale-all',
	'supannCivilite', 
	'supannListeRouge',
	'supannAliasLogin',
  ],
  "MONO 0" => [
	'accountStatus', 
  ],
  "MONO 1" => [
    'uidNumber', 'gidNumber',
    'supannEmpId', 'supannEtuId', 'supannCodeINE', 'supannFCSub',
    'shadowFlag', 'shadowExpire', 'shadowLastChange',    
	'homeDirectory', 'gecos',
    'sambaAcctFlags', 'sambaSID', 'sambaHomePath',

    // from up1Profile
    'up1Source', 'up1Priority', 'up1StartDate', 'up1EndDate', 'info;x-demande',
  ],
  "MONO 2" => [
	'supannEmpCorps',    
	'employeeNumber', // (NB: search allowed in level 1)
	'up1TagMifare',
    
	'createTimestamp', 'modifyTimestamp',

	'up1BirthName',
	'up1BirthDay',

    'homePhone', 'homePostalAddress', 'pager',
	'supannMailPerso',
  ],
  "MULTI -1" => [
    'supannEntiteAffectation', 'supannEntiteAffectation-ou', 'supannEntiteAffectation-all',
    'eduPersonAffiliation', 
    'buildingName', 'description', 'info',
	'supannEtablissement', 'supannActivite', 'supannActivite-all',
	'supannParrainDN', 'supannParrainDN-ou', 'supannParrainDN-all',
	'supannRoleEntite', 'supannRoleEntite-all',
	'supannEtuInscription', 'supannEtuInscription-all',
	'supannRoleGenerique',
    'eduPersonEntitlement',

    'up1AltGivenName',
	'roomNumber', 'up1FloorNumber', 'up1RoomAccess',

	'telephoneNumber', 
	'facsimileTelephoneNumber', 
	'supannAutreTelephone',

	'labeledURI',
    'seeAlso', 'seeAlso-all',
    
    'employeeType', 'employeeType-all', 'departmentNumber', // NB: non teacher/emeritus/researcher have a specific LEVEL 2 for those attrs
    'up1Profile', // will be filtered
  ],
  "MULTI 0" => [
    'mobile',
    'up1KrbPrincipal',
    'supannCodePopulation', 'supannCodePopulation-all', 'supannEmpProfil-all', 'supannExtProfil-all',
  ],
  "MULTI 1" => [
	'objectClass',
	'memberOf', 'memberOf-all',
	// below are restricted or internal attributes.
	'mailForwardingAddress', 'mailDeliveryOption', 'mailAlternateAddress',
    'supannConsentement', 'up1TermsOfUse',
    // for roles (which are groups)
    'member', 'member-all', 'supannGroupeLecteurDN', 'supannGroupeLecteurDN-all', 'supannGroupeAdminDN', 'supannGroupeAdminDN-all',
  ],
  "MULTI 2" => [
    'supannRefId',
  ],
];
$USER_ALLOWED_ATTRS = [];
foreach ($attrs_by_kind as $kind => $attrs) {
    $kind_ = explode(' ', $kind);
    $tags = [ "MULTI" => $kind_[0] === 'MULTI', "LEVEL" => (int) $kind_[1] ];
    foreach ($attrs as $attr) $USER_ALLOWED_ATTRS[$attr] = $tags;
}
global $UP1_ROLES_DN;
if (@$UP1_ROLES_DN) {
    $USER_ALLOWED_ATTRS['up1Roles'] = [ "MULTI" => true, "LEVEL" => 0 ]; // computed
}

function allowAttribute($user, $attrName, $allowExtendedInfo) {
    global $USER_ALLOWED_ATTRS;
    if (in_array($attrName, ['employeeType', 'employeeType-all', 'departmentNumber'])) {  
        // employeeType is private for staff & student
        // departmentNumber is not interesting for staff & student
        if (in_array(@$user['eduPersonPrimaryAffiliation'], array('teacher', 'emeritus', 'researcher'))) return true;
        return $allowExtendedInfo >= 2;
    }
    return $allowExtendedInfo >= $USER_ALLOWED_ATTRS[$attrName]["LEVEL"];
}

function people_attrs($attrs, $allowExtendedInfo = 0) {
    global $USER_ALLOWED_ATTRS;
    if (!$attrs) {
        $attrs = implode(',', array_keys($USER_ALLOWED_ATTRS));
        $all_attrs = true;
    }
    $wanted_attrs = array();
    foreach (explode(',', $attrs) as $attr) {
        $attr_kinds = @$USER_ALLOWED_ATTRS[$attr];
        if (!$attr_kinds) {
            error("unknown attribute $attr. allowed attributes: " . join(",", array_keys($USER_ALLOWED_ATTRS)));
            exit;
        }
        // most attributes visibility are enforced using ACLs on LDAP bind
        // but there are special cases so enforce our own rules
        if ($attr_kinds['LEVEL'] > $allowExtendedInfo) {
            if (!$all_attrs) error_log("not allowing $attr since request level is $allowExtendedInfo");
        } else {
            $wanted_attrs[$attr] = $attr_kinds['MULTI'] ? 'MULTI' : $attr;
        }
    }
    global $USER_KEY_FIELD;
    if (!isset($wanted_attrs[$USER_KEY_FIELD]))
        $wanted_attrs[$USER_KEY_FIELD] = $USER_KEY_FIELD;

    // employeeType* is only allowed on some eduPersonPrimaryAffiliation
    // departmentNumber is only useful for some eduPersonPrimaryAffiliation
    if (isset($wanted_attrs['employeeType']) || isset($wanted_attrs['employeeType-all']) || isset($wanted_attrs['departmentNumber']))
        $wanted_attrs['eduPersonPrimaryAffiliation'] = 'eduPersonPrimaryAffiliation';
    // gendered employeeType depends on supannCivilite & supannConsentement
    if (isset($wanted_attrs['employeeType'])) {
        $wanted_attrs['supannCivilite'] = 'supannCivilite';
        $wanted_attrs['supannConsentement'] = 'MULTI';
    }
   
    return $wanted_attrs;
}

function roomNumber_filter($normalized_token, $ext) {
    $or = [];
    foreach ($ext ? [ ' ' . trim($ext) ] : [ '', ' bis', ' ter' ] as $ext) {
        $or[] = "(roomNumber=$normalized_token$ext)";
    }
    return ldapOr($or);
}

function people_filters($token, $restriction = [], $allowInvalidAccounts = false, $allowNoAffiliationAccounts = false, $tokenIsId = false) {
    if ($allowInvalidAccounts !== 'all') {
        $restriction[] = $allowInvalidAccounts ? '(&(objectClass=inetOrgPerson)(!(shadowFlag=2))(!(shadowFlag=8)))' : // ignore dupes/deceased
                     ($allowNoAffiliationAccounts ? '(|(accountStatus=active)(!(accountStatus=*)))' : '(eduPersonAffiliation=*)');
    }

    $l = array();

    // MIFARE?
    if (preg_match('/^[0-9A-F]{14}$/', $token) || // DESFire
        preg_match('/^[0-9A-F]{8}$/', $token)) { // Classic
        $l[] = "(supannRefId={MIFARE}$token)";
    }

    if ($tokenIsId) {
        // it is allowed to be an array
        $l = array_map(function ($token) { return "(|(uid=$token)(mail=$token))"; }, is_array($token) ? $token : [$token]);
    } else if ($token === '') {
        $l[] = '(|(supannRoleGenerique={UAI:0751717J:HARPEGE.FCSTR}447)(supannRoleGenerique={UAI:0751717J:HARPEGE.FCSTR}1))'; // very important people first!
        $l[] = '(supannRoleGenerique={SUPANN}D*)'; // then important people
        $l[] = '(supannRoleGenerique=*)'; // then less important people
        $l[] = ''; // then the rest
    } else if (preg_match('/(.*?)@(.*)/', $token, $matches)) {
        $l[] = "(|(mail=$token)(&(uid=$matches[1])(mail=*@$matches[2])))";
    } else if (preg_match('/^\d+$/', $token, $matches)) {
        $l[] = "(|(supannEmpId=$token)(supannEtuId=$token))";

        if (strlen($token) <= 4) {
            $l[] = roomNumber_filter($token, null);
        }
        // barcode?
        if (strlen($token) === 12) { // codification unique UNPIdF, used at Paris1
            $l[] = "(employeeNumber=$token)";
        }
    } else {
        $l[] = "(uid=$token)";
        $l[] = "(|(sn=$token)(up1BirthName=$token))";

        if (preg_match('/^([A-Z])\.? ?([0-9]+)( ?bis| ?ter)?$/i', $token, $matches) ||
            preg_match('/^([0-9]{1,4}) ?\.?([A-Z])?( ?bis| ?ter)?$/i', $token, $matches)) {
            list($_ignore, $a, $b, $ext) = $matches;
            $l[] = roomNumber_filter($a . ($b ? " " . trim($b) : ''), $ext);
        }

        if (mb_strlen($token) >= index_substr_any_len) {
            // too short strings are useless
            $l[] = "(|(displayName=*$token*)(cn=*" . lowercase_and_stripAccents($token) . "*)(up1BirthName=*$token*))";
            $tokens = preg_split("/[\s'\-]+/", $token);
            $larger_token_size = max(array_map(function ($s) { return mb_strlen($s); }, $tokens));
            if (sizeof(($tokens)) > 1 && $larger_token_size >= index_substr_if_minlen) {
                $search = implode('*', $tokens);
                $x = $larger_token_size >= index_substr_any_len ? '*' : '';
                $l[] = "(|(displayName=$x$search$x)(cn=$x" . lowercase_and_stripAccents($search) . "$x))";
                if (sizeof($tokens) === 2) {
                    // also search in reverse token order
                    $search = implode('*', array($tokens[1], $tokens[0]));
                    $l[] = "(|(displayName=$x$search$x)(cn=$x" . lowercase_and_stripAccents($search) . "$x))";
                }
            }
            // cas particulier pour les noms à particules qui peuvent être cherchés sans espace : "Dumoulin" doit trouver "Du moulin"
            if (sizeof($tokens) === 1 && preg_match('/^(de|des|du)(.*)/i', $token, $matches)) {
                list($_ignore, $particule, $reste) = $matches;
                $search = "$particule $reste";
                $l[] = "(|(sn=$search*)(cn=" . lowercase_and_stripAccents($search) . "*)(up1BirthName=$search*))";
            }
        }
    }

    return apply_restrictions_to_filters($l, $restriction);
}

function GET_extra_people_filter_from_params() {
  $filters = array();
  $filters_not = array();
  foreach (array("supannConsentement", "eduPersonEntitlement", "eduPersonAffiliation", "eduPersonPrimaryAffiliation", "supannEntiteAffectation", "description", "employeeType", "supannActivite", "supannRoleGenerique", "uid", "buildingName") as $attr) {
    $filters[$attr] = GET_ldapFilterSafe_or_NULL("filter_$attr");
    $filters_not[$attr] = GET_ldapFilterSafe_or_NULL("filter_not_$attr");
  }
  foreach (array("student") as $attr) {
    $val = GET_ldapFilterSafe_or_NULL("filter_$attr");
    if ($val === null) continue;
    else if ($val === "no") $filters_not["eduPersonAffiliation"] = $attr;
    else if ($val === "only") $filters["eduPersonAffiliation"] = $attr;
    else exit("invalid filter_$attr value $val");
  }
  foreach (array("mail", "labeledURI", 'supannEmpId') as $attr) {
    $val = GET_or_NULL("filter_$attr");
    if ($val === null) continue;
    else if ($val === "*") $filters[$attr] = "*";
    else exit("invalid filter_$attr value $val");
  }
  return array_merge(
      computeFilter($filters, false),
      computeFilter($filters_not, true),
      GET_filter_supannEtuInscription(),
      GET_filter_member_of_group());
}

function GET_filter_supannEtuInscription() {
    $params = [
        'affect' => GET_ldapFilterSafe_or_NULL("filter_student_affectation_annee_courante"),
        'etab' => GET_ldapFilterSafe_or_NULL("filter_student_etablissement_annee_courante"),
    ];
    $filters = [];
    foreach ($params as $key => $values) {
        if ($values) {
            $or = [];
            require_once ('config/config-groups.inc.php');
            global $ANNEE;
            foreach (explode('|', $values) as $one) {
                $test = $key === 'etab' ? "*[$key=$one]*[anneeinsc=$ANNEE]*" : "*[anneeinsc=$ANNEE]*[$key=$one]*";
                $or[] = "(supannEtuInscription=$test)";
            }
            $filters[] = ldapOr($or);
        }
    }
    return $filters;
}

function GET_filter_member_of_group() {
  $keys = GET_or_NULL("filter_member_of_group");
  if (!$keys) return [];

  return [ldapOr(array_map('groupKey2filter', array_map('ldap_escape_string', explode('|', $keys))))];
}

function groupKey2filter($key) {
  global $GROUPS_DN, $DIPLOMA_DN, $DIPLOMA_PREV_DN;

  if ($cn = removePrefixOrNULL($key, "groups-")) {
    return "(memberOf=cn=$cn,$GROUPS_DN)";
  } else if ($supannCodeEntite = removePrefixOrNULL($key, "structures-")) {

    // handle key like structures-U05-affiliation-student:
    if (preg_match('/(.*)-affiliation-(.*)/', $supannCodeEntite, $matches)) {
      $supannCodeEntite = $matches[1];
      $affiliation = $matches[2];
    } else {
      $affiliation = null;
    }

    $filter = "(supannEntiteAffectation=$supannCodeEntite)";
    if ($affiliation)
      $filter = "(&$filter(eduPersonAffiliation=$affiliation))";

    return $filter;
  } else if ($diploma = removePrefixOrNULL($key, "diploma-")) {
    $ou = "ou=$diploma," . $DIPLOMA_DN;
    return "(eduPersonOrgUnitDN=$ou)";
  } else if ($diploma = removePrefixOrNULL($key, "diplomaPrev-")) {
    $ou = "ou=$diploma," . $DIPLOMA_PREV_DN;
    return "(eduPersonOrgUnitDN=$ou)";
  } else if ($affiliation = removePrefixOrNULL($key, "affiliation-")) {
    return "(eduPersonAffiliation=$affiliation)";
  } else {
    exit("invalid group key $key");
  }
}

function isPersonMatchingFilter($uid, $filter) {
    global $PEOPLE_DN;
    return existsLdap($PEOPLE_DN, "(&(uid=$uid)" . $filter . ")");
}

function loggedUserAllowedLevel() {
    global $LEVEL1_FILTER, $LEVEL2_FILTER;
    return isPersonMatchingFilter(GET_uid(), $LEVEL1_FILTER) ?
        (isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER) ? 2 : 1) : 0;
}

function loggedUserSearchAllowedLevel() {
    global $LEVEL1_SEARCH_FILTER, $LEVEL2_FILTER;
    return isPersonMatchingFilter(GET_uid(), $LEVEL1_SEARCH_FILTER) ?
        (isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER) ? 2 : 1) : 0;
}

function allowListeRouge($allowExtendedInfo) {
    global $isTrustedIp;
    if ($allowExtendedInfo > 0 || @$isTrustedIp) {
        return true;
    } else {
        $uid = GET_uid();
        return $uid && isPersonMatchingFilter($uid, "(|(eduPersonAffiliation=staff)(eduPersonAffiliation=faculty)(eduPersonAffiliation=teacher)(eduPersonAffiliation=emeritus))");
    }
}

function searchPeopleRaw($filter, $allowListeRouge, $allowRoles, $wanted_attrs, $KEY_FIELD, $maxRows) {
    global $BASE_DN, $PEOPLE_DN, $SEARCH_TIMELIMIT;
    if (!$allowListeRouge) {
	// we need the attr to anonymize people having supannListeRouge=TRUE
	$wanted_attrs['supannListeRouge'] = 'supannListeRouge';
    }
    if ($allowRoles) $wanted_attrs['dn'] = 'dn';
    $r = getLdapInfoMultiFilters($allowRoles ? $BASE_DN : $PEOPLE_DN, $filter, $wanted_attrs, $KEY_FIELD, $maxRows, $SEARCH_TIMELIMIT);
    if (!$allowListeRouge) {
      foreach ($r as &$e) {
	if (!isset($e["supannListeRouge"])) continue;
	$supannListeRouge = getAndUnset($e, "supannListeRouge");
	if ($supannListeRouge == "TRUE") {
	  if (sizeof($r) == 1) {
	    // hum, the search is precise enough to return only one result.
	    // if we return the anonymized result, someone can know a user exists, even if anonymized
	    // better return nothing! 
	    $r = array();
	  } else {
	    anonymizeUser($e, $wanted_attrs);
	  }
	}
      }
    }
    return $r;
}

function wanted_attrs_raw($wanted_attrs) {
    $r = array();
    foreach ($wanted_attrs as $attr => $v) {
	$attr_raw = preg_replace('/-.*/', '', $attr);
	$r[$attr_raw] = preg_replace('/-.*/', '', $v);
    }
    return $r;
}

function attrRestrictions($allowExtendedInfo = -1) {
    global $isTrustedIp;
    return
        array('allowListeRouge' => allowListeRouge($allowExtendedInfo),
        'allowMailForwardingAddress' => $allowExtendedInfo > 1,
        'allowExtendedInfo' => $allowExtendedInfo,
        'forceProfile' => 
            (isset($_GET["profile_supannEntiteAffectation"]) ? '/\[supannEntiteAffectation=(\w+;)*' . preg_quote($_GET["profile_supannEntiteAffectation"], '/') . '[;\]]/' : 
             (isset($_GET["profile_eduPersonAffiliation"]) ? '/\[eduPersonAffiliation=(\w+;)*' . preg_quote($_GET["profile_eduPersonAffiliation"], '/') . '[;\]]/' :
             null)
        ),
        );
}

function searchPeople($filter, $attrRestrictions, $wanted_attrs, $KEY_FIELD, $maxRows) {
    $allowListeRouge = @$attrRestrictions['allowListeRouge'];
    $wanted_attrs_raw = wanted_attrs_raw($wanted_attrs);
    $r = searchPeopleRaw($filter, $allowListeRouge, @$attrRestrictions['allowRoles'], $wanted_attrs_raw, $KEY_FIELD, $maxRows);
    foreach ($r as &$user) {
      if (!@$attrRestrictions['allowMailForwardingAddress'])
	  anonymizeUserMailForwardingAddress($user);
      if ($attrRestrictions['allowExtendedInfo'] < 1) userHandle_PersonnelEnActivitePonctuelle($user);
      userAttributesKeyToText($user, $wanted_attrs, @$user['supannCivilite'], @$user['supannConsentement'] , $attrRestrictions['allowExtendedInfo']);
      userHandleSpecialAttributeValues($user, $attrRestrictions['allowExtendedInfo']);
      if (isset($user['up1Profile'])) {
        if (@$attrRestrictions['forceProfile']) {
            forceProfile($user, $attrRestrictions['forceProfile'], $attrRestrictions['allowExtendedInfo'], $wanted_attrs);
            unset($user['up1Profile']);
        } else {
            $user['up1Profile'] = parse_up1Profile($user['up1Profile'], $attrRestrictions['allowExtendedInfo'], $wanted_attrs, $user);
        }
      }
      userHandleSpecialAttributePrivacy($user, $attrRestrictions['allowExtendedInfo']);
      format_postalAddress($user);
      if (@$wanted_attrs['up1Roles']) get_up1Roles($user);
    }
    return $r;
}

function array_remove($array, $elt) {
    return array_values(array_diff($array, [$elt]));
}

function userHandle_PersonnelEnActivitePonctuelle(&$user) {
    if (isset($user['employeeType']) &&
        $user['employeeType'][0] === "Personnel en activité ponctuelle") {
        // on supprime les infos venant de "Personnel en activité ponctuelle", notamment son affectation (GLPI UP1#137957)
        array_shift($user['employeeType']);
        if (in_array('staff', $user['eduPersonAffiliation'])) {
            $user['eduPersonAffiliation'] = array_remove($user['eduPersonAffiliation'], 'staff');
            if ($user['eduPersonPrimaryAffiliation'] === 'staff' && in_array('teacher', $user['eduPersonAffiliation'])) {
                $user['eduPersonPrimaryAffiliation'] = 'teacher';
            }
        }
        $allow_remove_all_affectations = count(array_intersect($user['eduPersonAffiliation'], ['teacher', 'researcher'])) === 0;
        if (count($user['supannEntiteAffectation']) > ($allow_remove_all_affectations ? 0 : 1)) {
            array_shift($user['supannEntiteAffectation']);
            $user['supannEntiteAffectationPrincipale'] = $user['supannEntiteAffectation'][0];
        }
    }
}

function anonymizeUser(&$e, $attributes_map) {
    global $PEOPLE_LISTEROUGE_NON_ANONYMIZED_ATTRS;
    $allowed = array();
    foreach ($PEOPLE_LISTEROUGE_NON_ANONYMIZED_ATTRS as $attr) {
	if (isset($attributes_map[$attr])) 
	    $allowed[$attributes_map[$attr] == "MULTI" ? $attr : $attributes_map[$attr]] = 1;
    }

    foreach ($e as $k => $v) {
	if (!isset($allowed[$k])) {
	    $e[$k] = $attributes_map[$k] == "MULTI" ? array() : 'supannListeRouge';
	}
    }
}

function anonymizeUserMailForwardingAddress(&$e) {
  if (!isset($e['mailForwardingAddress'])) return;
  foreach ($e['mailForwardingAddress'] as &$mail) {
    if (preg_match("/@/", $mail)) $mail = 'supannListeRouge';
  }
}

function structureShortnames($keys) {
    $all = structureAll($keys);
    $r = array();
    foreach ($all as $e) {
      $r[] = @$e['name'];
    }
    return empty($r) ? NULL : $r;
}
function structureAll($keys) {
    GLOBAL $structureKeyToAll, $showErrors;
    $r = array();
    foreach ($keys as $key) {
      $e = array("key" => $key);
      if (isset($structureKeyToAll[$key]))
	$e = array_merge($e, $structureKeyToAll[$key]);
      else if ($showErrors)
	$e["name"] = "invalid structure $key";

      $r[] = $e;
    }
    return empty($r) ? NULL : $r;
}

function activiteUP1All($descriptions) {
  global $descriptionToActivityKey;
  $r = [];
  foreach ($descriptions as $description) {
      $key = @$descriptionToActivityKey[$description];
      if ($key) $r[] = ['key' => $key, 'name' => $description];
  }
  return $r;
}

function supannActiviteAll($supannCivilite, $keys) {
  global $activiteKeyToAll;
  $r = array();
  foreach ($keys as $key) {
    $e = array('key' => $key);
    $all = @$activiteKeyToAll[$key];
    if ($all) {
        $e['name'] = $all['name'];
        $gender = all_to_name_with_gender_no_fallback($all, $supannCivilite);
        if ($gender) $e['name-gender'] = $gender;
    }
    $r[] = $e;
  }
  return empty($r) ? NULL : $r;
}

function supannCodePopulationAll($key) {
    $e = array('key' => $key);
    $name = $GLOBALS['supannCodePopulationToShortname'][$key];
    if ($name) $e['name'] = $name;
    return $e;
}

function toShortnames($all) {
    $r = array();
    foreach ($all as $e) {
      $r[] = @$e['name'];
    }
    return empty($r) ? NULL : $r;
}
  
function supannActiviteShortnames($keys) {
    return toShortnames(supannActiviteAll(null, $keys));
}

function parse_composite_value($s) {
  preg_match_all('/\[(.*?)\]/', $s, $m);
  $r = array();
  foreach ($m[1] as $e) {
    list($k,$v) = explode('=', $e, 2);
    $r[$k] = $v;
  }
  return $r;
}

function parse_supannEtuInscription($s) {
  return parse_composite_value($s);
}

# inverse échappement les caractères spéciaux d'attributs composites pour une liste de valeurs
function unescape_sharpFF($attr_value) {
    return preg_replace_callback('/#([0-9A-F]{2})/i', function ($m) { return chr(hexdec($m[1])); }, $attr_value);
}

function parse_up1Profile_one_raw($up1Profile) {
    global $USER_ALLOWED_ATTRS;
    $r = [];
    while (preg_match('/^\[([^\[\]=]+)=((?:[^\[\]]|\[[^\[\]]*\])*)\](.*)/', $up1Profile, $m)) {
        $key = $m[1]; $val = $m[2]; $up1Profile = $m[3];
        $key = unescape_sharpFF($key);
        $attr_kinds = @$USER_ALLOWED_ATTRS[$key];
        if (!$attr_kinds) {
            // ignore
        } else if ($attr_kinds['MULTI']) {
            $r[$key] = array_map('unescape_sharpFF', explode(';', $val));
        } else {
            $r[$key] = unescape_sharpFF($val);
        }
    }
    if ($up1Profile !== '') error_log("bad up1Profile, remaining $up1Profile");
    return $r;
}

function post_parse_up1Profile_one($r, $allowExtendedInfo, $wanted_attrs, $global_user) {
    if ($allowExtendedInfo < 1) userHandle_PersonnelEnActivitePonctuelle($r);
    userHandleSpecialAttributePrivacy($r, $allowExtendedInfo);
    userAttributesKeyToText($r, $wanted_attrs, 
            isset($r['supannCivilite']) ? $r['supannCivilite'] : $global_user['supannCivilite'], 
            isset($r['supannConsentement']) ? $r['supannConsentement'] : $global_user['supannConsentement'], 
            $allowExtendedInfo);
    userHandleSpecialAttributeValues($r, $allowExtendedInfo);
    return $r;
}

function parse_up1Profile($up1Profile_s, $allowExtendedInfo, $wanted_attrs, $global_user) {
    $r = [];
    foreach ($up1Profile_s as $profile) {
       $r[] = post_parse_up1Profile_one(parse_up1Profile_one_raw($profile), $allowExtendedInfo, $wanted_attrs, $global_user);
    }
    return $r;
}

function array_replace_keys(&$array, $to_set) {
    foreach ($to_set as $k => $v) {
        if (isset($array[$k])) $array[$k] = $v;
    }
}

function forceProfile(&$user, $forceProfile, $allowExtendedInfo, $wanted_attrs) {
    foreach ($user['up1Profile'] as $profile_s) {
        if (preg_match($forceProfile, $profile_s)) {
            $full_profile = parse_up1Profile_one_raw($profile_s);
            if ($_GET["profile_supannEntiteAffectation"] &&
                $full_profile['supannEntiteAffectationPrincipale'] !== $_GET["profile_supannEntiteAffectation"] && 
                $full_profile['eduPersonPrimaryAffiliation'] === 'staff' &&
                in_array('teacher', $full_profile['eduPersonAffiliation'])) {
                // "cuisine" mélange plusieurs contrats. Les supannActivite RIFSEEP sont forcément associés au contrat staff, donc les ignorer pour les chargés d'enseignement
                if ($full_profile['supannActivite']) {
                    $full_profile['supannActivite'] = array_filter($full_profile['supannActivite'], function ($act) {
                        return !startsWith($act, '{UAI:0751717J:RIFSEEP}') && !startsWith($act, '{REFERENS}');
                    });
                }
                $full_profile['eduPersonPrimaryAffiliation'] = 'teacher';
            }
            $profile = post_parse_up1Profile_one($full_profile, $allowExtendedInfo, $wanted_attrs, $user);
            array_replace_keys($user, $profile);
            foreach (['supannActivite', 'supannActivite-all'] as $profiled_attr) {
                if (!isset($profile[$profiled_attr])) unset($user[$profiled_attr]);
            }
        }
    }
    unset($user['up1Profile']);
}

function supannEmpExtProfilAll($profil) {
    $r = parse_composite_value($profil);
    if (isset($r["population"])) {
        $r["population"] = supannCodePopulationAll($r["population"]);
    }
    if (isset($r['parrain'])) {
        $r['parrain'] = getDN($r['parrain']);
    }
    if (isset($r['etab'])) {
        if ($r['etab'] === '{UAI}0751717J') {
            unset($r['etab']);
        } else {
            $r['etab'] = supannEtablissementAll($r['etab']);
        }
    }
    if (isset($r['affect'])) {
        $r['affect'] = structureAll([$r['affect']])[0];
    }
    unset($r['typeaffect']); // we do not handle it for the moment, so hide it
    return $r;
}

function supannEtuInscriptionAll($supannEtuInscription) {
  $r = parse_supannEtuInscription($supannEtuInscription);
  if (@$r['etape']) {
    $localEtape = removePrefix($r['etape'], '{UAI:0751717J}');
    require_once 'lib/groups.inc.php';
    $diploma = getGroupsFromDiplomaDn(array("(ou=$localEtape)"), 1);
    if ($diploma) $r['etape'] = $diploma[0]["description"];

    if (@$r['anneeinsc'] !== "" . $GLOBALS['ANNEE']) {
        $r['anneePrecedente'] = true;
    }
  }
  if (@$r['etab'] === '{UAI}0751717J') {
    unset($r['etab']);
  }
  if (@$r['cursusann']) {
    $r['cursusann'] = removePrefix($r['cursusann'], '{SUPANN}');
  }
  if (@$r['typedip'] && $r['typedip'] !== '{INCONNU}') {
    // http://infocentre.pleiade.education.fr/bcn/workspace/viewTable/n/N_TYPE_DIPLOME_SISE
    $to_name = array(
		     '01' => "DIPLOME UNIVERSITE GENERIQUE",
		     '03' => "HABILITATION A DIRIGER DES RECHERCHES",
		     '05' => "DIPLOME INTERNATIONAL",
		     'AC' => "CAPACITE EN DROIT",
		     'DP' => "LICENCE PROFESSIONNELLE",
		     'EZ' => "PREPARATION AGREGATION",
		     'FE' => "MAGISTERE",
		     'NA' => "AUTRES DIPL. NATIONAUX NIV. FORM. BAC",
		     'UE' => "DIPLOME UNIV OU ETAB NIVEAU BAC + 4",
		     'UF' => "DIPLOME UNIV OU ETAB NIVEAU BAC + 5",
		     'XA' => "LICENCE (LMD)",
		     'XB' => "MASTER (LMD)",
		     'YA' => "DOCTORAT D'UNIVERSITE",
		     'YB' => "DOCTORAT D'UNIVERSITE (GENERIQUE)",
		     'ZA' => "DIPLOME PREP AUX ETUDES COMPTABLES",
		     );
    $r['typedip'] = $to_name[removePrefix($r['typedip'], '{SISE}')];
  }
  if (@$r['regimeinsc']) {
    $r['regimeinsc'] = $GLOBALS['supannEtuRegimeInscription'][$r['regimeinsc']];
  }
  if (@$r['sectdisc']) {
    $r['sectdisc'] = $GLOBALS['supannEtuSecteurDisciplinaire'][$r['sectdisc']];
  }  
  if (@$r['diplome']) {
    $r['diplome'] = $GLOBALS['supannEtuDiplome'][$r['diplome']];
  }  
  return $r;
}

function should_hide_role($r, $allowExtendedInfo) {
    return $r['role'] === '{SUPANN}R22' && $allowExtendedInfo < 1; # GLPI UP1#126406
}

function supannRoleEntiteAll($supannCivilite, $e, $allowExtendedInfo) {
  $r = parse_composite_value($e);
  if (@$r['role']) {
    if (should_hide_role($r, $allowExtendedInfo)) return;
    global $roleGeneriqueKeyToAll;
    if ($role = $roleGeneriqueKeyToAll[$r['role']]) {
        $r['role'] = all_to_name_with_gender($role, $supannCivilite);
        if (isset($role['weight'])) $r['role_weight'] = $role['weight'];
    }
  }
  if (@$r['code']) {
    $r['structure'] = array_shift(structureAll(array($r['code'])));
    unset($r['code']);
  }
  return $r;
}

function supannEtuInscriptionsAll($l) {
  $r = array();
  foreach ($l as $supannEtuInscription) {
    $r[] = supannEtuInscriptionAll($supannEtuInscription);
  }
  return empty($r) ? NULL : $r;
}

function supannRoleEntitesAll($supannCivilite, $l, $allowExtendedInfo) {
  $r = array();
  foreach ($l as $e) {
    $e_ = supannRoleEntiteAll($supannCivilite, $e, $allowExtendedInfo);
    if ($e_) $r[] = $e_;
  }
  return empty($r) ? NULL : $r;
}

function memberOfAll($l) {
  $attrs = array("cn" => "key", "ou" => "name", "description" => "description", "objectClass" => "MULTI");

  $r = [];
  foreach ($l as $dn) $r[] = getLdapDN($dn, $attrs);
  return $r;
}

function getDNs($l) {
  $attrs = array("ou" => "name", "displayName" => "name", "description" => "description", "labeledURI" => "labeledURI", "mail" => "mail");

  $r = [];
  foreach ($l as $dn) $r[] = getLdapDN_with_DN_as_key($dn, $attrs);
  return $r;
}
function getDN($dn) {
    return getDNS([$dn])[0];
}

function replace_old_structures_DN($l) {
    global $ALT_STRUCTURES_DN, $STRUCTURES_DN;
    $r = [];
    foreach ($l as $dn) {
        $r[] = preg_replace('/^ou=(.*?),' . preg_quote($ALT_STRUCTURES_DN, '/') . '/', "supannCodeEntite=$1,$STRUCTURES_DN", $dn);
    }
    return $r;
}

function rdnToSupannCodeEntites($l) {
  $codes = array();
  foreach ($l as $rdn) {
    if (preg_match('/^supannCodeEntite=(.*?),ou=structures/', $rdn, $match)) {
      $codes[] = $match[1];
    } else if (preg_match('/^ou=(.*?),ou=structures/', $rdn, $match)) {
      $codes[] = $match[1]; // for local branch
    }
  }
  return $codes;
}

function userHandleSpecialAttributePrivacy(&$user, $allowExtendedInfo) {
    foreach ($user as $attrName => $val) {
        if (!allowAttribute($user, $attrName, $allowExtendedInfo)) {
            unset($user[$attrName]);
        }
    }
}

function userHandleSpecialAttributeValues(&$user, $allowExtendedInfo) {
    if ($allowExtendedInfo < 1) {
        if (isset($user['labeledURI'])) {
            $user['labeledURI'] = array_filter($user['labeledURI'], function ($uri) { return !contains($uri, ' {DEMANDE}'); });
        }
    }
}


function civilite_to_gender_suffix($civilite) {
    return $civilite === 'M.' ? '-gender-m' :
           ($civilite === 'Mme' || $civilite === 'Mlle' ? '-gender-f' : '');
}

function all_to_name_with_gender_no_fallback($all, $supannCivilite) {
    $name = null;
    if ($supannCivilite) {
        $name = @$all['name' . civilite_to_gender_suffix($supannCivilite)];
    }
    return $name;
}

function all_to_name_with_gender($all, $supannCivilite) {
    $name = all_to_name_with_gender_no_fallback($all, $supannCivilite);
    if (!$name) {
        $name = $all['name'];
    }
    return $name;
}

function all_to_short_name_with_gender($all, $supannCivilite) {
    if ($supannCivilite) {
        $name = @$all['name' . civilite_to_gender_suffix($supannCivilite) . '-short'];
    }
    return $name;
}

function employeeTypeAll($name, $supannCivilite) {
    require_once 'lib/employeeTypes.inc.php';
    $r = [ "name" => $name ];
    $all = @$GLOBALS['employeeTypes'][$name];
    if ($all) {
        $r["weight"] = $all["weight"];
        $gender = all_to_name_with_gender_no_fallback($all, $supannCivilite);
        if ($gender) $r['name-gender'] = $gender;
        if (@$all["name"]) {
            # GLPI UP1#125765
            $r["key"] = $name;
            $r["name"] = $all["name"];
        }
    }
    return $r;
}

function supannEtablissementShortname($key) {
    $usefulKey = removePrefixOrNULL($key, "{AUTRE}");
    $name = @$GLOBALS['etablissementKeyToShortname'][$key];
    if ($name) return $usefulKey ? "$name [$usefulKey]" : $name;
    return null;
}

function supannEtablissementAll($key) {
    $r = [ 'key' => $key ];
    $name = supannEtablissementShortname($key);
    if ($name) $r['name'] = $name;
    return $r;
}

function userAttributesKeyToText(&$user, $wanted_attrs, $supannCivilite, $supannConsentement, $allowExtendedInfo) {
  $supannEntiteAffectation = @$user['supannEntiteAffectation'];
  if ($supannEntiteAffectation) {
      if (isset($user['supannEntiteAffectationPrincipale'])) {
          # put "principale" affectation first
          $first = $user['supannEntiteAffectationPrincipale'];
          $supannEntiteAffectation = array_unique_(array_merge([$first], $supannEntiteAffectation));
      }
      if (isset($wanted_attrs['supannEntiteAffectation-all']))
	  $user['supannEntiteAffectation-all'] = structureAll($supannEntiteAffectation);
      if (isset($wanted_attrs['supannEntiteAffectation-ou']))
	  $user['supannEntiteAffectation-ou'] = structureShortnames($supannEntiteAffectation);
      if (isset($wanted_attrs['supannEntiteAffectation']))
	  // deprecated
	  $user['supannEntiteAffectation'] = structureShortnames($supannEntiteAffectation);
  }
  if (isset($user['supannEntiteAffectationPrincipale'])) {
    if (isset($wanted_attrs['supannEntiteAffectationPrincipale-all'])) {
        $user['supannEntiteAffectationPrincipale-all'] = structureAll([$user['supannEntiteAffectationPrincipale']])[0];
    }
    if (!isset($wanted_attrs['supannEntiteAffectationPrincipale']))
        unset($user['supannEntiteAffectationPrincipale']);
}
  if (isset($user['seeAlso'])) {
      if (isset($wanted_attrs['seeAlso-all']))
        $user['seeAlso-all'] = getDNs(replace_old_structures_DN($user['seeAlso']));
  }
  if (isset($user['member'])) {
      if (isset($wanted_attrs['member-all']))
        $user['member-all'] = getDNs($user['member']);
      if (!isset($wanted_attrs['member']))
         unset($user['member']);
  }
  if (isset($user['supannGroupeLecteurDN'])) {
      if (isset($wanted_attrs['supannGroupeLecteurDN-all']))
        $user['supannGroupeLecteurDN-all'] = getDNs($user['supannGroupeLecteurDN']);
      if (!isset($wanted_attrs['supannGroupeLecteurDN']))
         unset($user['supannGroupeLecteurDN']);
  }
  if (isset($user['supannGroupeAdminDN'])) {
    if (isset($wanted_attrs['supannGroupeAdminDN-all']))
      $user['supannGroupeAdminDN-all'] = getDNs($user['supannGroupeAdminDN']);
    if (!isset($wanted_attrs['supannGroupeAdminDN']))
       unset($user['supannGroupeAdminDN']);
}
if (isset($user['supannParrainDN'])) {
      if (isset($wanted_attrs['supannParrainDN-all']))
	$user['supannParrainDN-all'] = getDNs(replace_old_structures_DN($user['supannParrainDN']));
      else if (isset($wanted_attrs['supannParrainDN-ou']))
	$user['supannParrainDN-ou'] = structureShortnames(rdnToSupannCodeEntites($user['supannParrainDN']));
      if (!isset($wanted_attrs['supannParrainDN']))
	  unset($user['supannParrainDN']);
  }
  if (isset($user['supannEtuInscription'])) {
      if (isset($wanted_attrs['supannEtuInscription-all']))
	$user['supannEtuInscription-all'] = supannEtuInscriptionsAll($user['supannEtuInscription']);
      if (!isset($wanted_attrs['supannEtuInscription']))
	  unset($user['supannEtuInscription']);
  }
  if (isset($user['supannRoleEntite'])) {
      if (isset($wanted_attrs['supannRoleEntite-all']))
	$user['supannRoleEntite-all'] = supannRoleEntitesAll($supannCivilite, $user['supannRoleEntite'], $allowExtendedInfo);
      if (!isset($wanted_attrs['supannRoleEntite']))
	  unset($user['supannRoleEntite']);
  }
  if (isset($user['memberOf'])) {
      if (isset($wanted_attrs['memberOf-all']))
	$user['memberOf-all'] = memberOfAll($user['memberOf']);
      if (!isset($wanted_attrs['memberOf']))
	  unset($user['memberOf']);
  }
  if (isset($user['supannRoleGenerique'])) {
    global $roleGeneriqueKeyToAll;
    foreach ($user['supannRoleGenerique'] as &$e) {
      if ($role = $roleGeneriqueKeyToAll[$e]) {
          $e = all_to_name_with_gender($role, $supannCivilite);
      }
    }
  }
  if (isset($user['supannActivite'])) {
    if (isset($wanted_attrs['supannActivite-all']))
	$user['supannActivite-all'] = supannActiviteAll($supannCivilite, $user['supannActivite']);
    if (isset($wanted_attrs['supannActivite']))
        $user['supannActivite'] = supannActiviteShortnames($user['supannActivite']);
    else
        unset($user['supannActivite']);
  }
  if (isset($user['supannCodePopulation'])) {
    if (isset($wanted_attrs['supannCodePopulation-all']))
        $user['supannCodePopulation-all'] = array_map(supannCodePopulationAll, $user['supannCodePopulation']);
    if (isset($wanted_attrs['supannCodePopulation']))
        $user['supannCodePopulation'] = toShortnames(array_map(supannCodePopulationAll, $user['supannCodePopulation']));
    else
        unset($user['supannCodePopulation']);
  }
  if (isset($user['supannEmpProfil'])) {
    if (isset($wanted_attrs['supannEmpProfil-all']))
        $user['supannEmpProfil-all'] = array_map(supannEmpExtProfilAll, $user['supannEmpProfil']);
    unset($user['supannEmpProfil']);
  }
  if (isset($user['supannExtProfil'])) {
    if (isset($wanted_attrs['supannExtProfil-all']))
        $user['supannExtProfil-all'] = array_map(supannEmpExtProfilAll, $user['supannExtProfil']);
    unset($user['supannExtProfil']);
  }
  if (isset($user['employeeType'])) {
      $alls = array_map(function ($name) use ($supannCivilite) { return employeeTypeAll($name, $supannCivilite); }, $user['employeeType']);
      if (isset($wanted_attrs['employeeType-all'])) {
          $user['employeeType-all'] = $alls;
      }
      # we allow gendered employeeType only if allowed by user, cf GLPI UP1#115582
      $allow_gendered = in_array('{EMPLOYEETYPE}GENDER', $supannConsentement ?? []);
      # use simplified names from lib/employeeTypes.inc.php, cf GLPI UP1#125765
      $user['employeeType'] = array_map(function ($all) use ($allow_gendered) { 
          return $allow_gendered && isset($all['name-gender']) ? $all['name-gender'] : $all['name'];
      }, $alls);
  }
  if (isset($user['description']) && isset($wanted_attrs['supannActivite-all'])) {
	$user['supannActivite-all'] = array_merge((array) $user['supannActivite-all'], activiteUP1All($user['description']));
  }
  if (isset($user['supannEtablissement'])) {
    // only return interesting supannEtablissement (ie not Paris1)
    $user['supannEtablissement'] = array_values(array_diff($user['supannEtablissement'], array('{UAI}0751717J', "{autre}")));
    if (!$user['supannEtablissement']) {
      unset($user['supannEtablissement']);
    } else {
      global $etablissementKeyToShortname;
      foreach ($user['supannEtablissement'] as &$e) {
        $name = supannEtablissementShortname($e);
        if ($name) $e = $name;
      }
    }
  }
}

function get_up1Roles(&$user) {
  $roles = get_up1Roles_raw($user);
  if ($roles) $user['up1Roles'] = $roles;
}

function get_up1Roles_raw($user) {
  global $BASE_DN, $PEOPLE_DN;

  $roles = array();
  $rdn = "uid=" . $user['uid'] . ",$PEOPLE_DN";
  foreach (array('manager', 'member') as $role) {
    $filter = "($role=$rdn)";
    if ($role === 'manager') $filter = "(|$filter(supannGroupeLecteurDN=$rdn))";
    $filter = "(&(objectClass=up1Role)$filter)";
    foreach (getLdapInfo($BASE_DN, $filter, array("mail" => "mail", "seeAlso" => "seeAlso", "description" => "description")) as $e) {
      $e['role'] = $role;
      $roles[] = $e;
    }
  }
  return $roles;
}

?>
