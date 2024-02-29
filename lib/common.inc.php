<?php

require_once ('config/config-auth.inc.php');
require_once ('config/config.inc.php');
require_once ('lib/MyLdap.inc.php');

function GET_ldapFilterSafe($name) {
    return ldap_escape_string($_GET[$name]);
}
function GET_ldapFilterSafe_or($name, $default_value) {
    return isset($_GET[$name]) ? ldap_escape_string($_GET[$name]) : $default_value;
}
function GET_or($name, $default) {
    return isset($_GET[$name]) ? $_GET[$name] : $default;
}
function GET_ldapFilterSafe_or_NULL($name) {
    return GET_ldapFilterSafe_or($name, NULL);
}
function GET_or_NULL($name) {
    return GET_or($name, NULL);
}

function GET_bool($name) {
    $v = GET_or($name, "false");
    return $v && $v !== "false";
}

function GET_uid() {
  return isset($_SERVER["HTTP_CAS_USER"]) ? $_SERVER["HTTP_CAS_USER"] : ''; // CAS-User
}


function computeOneFilter($attr, $valsS) {
    $vals = explode('|', $valsS);
    $orFilter = [];
    foreach ($vals as $val)
      $orFilter[] = "($attr=$val)";
    return ldapOr($orFilter);
}
function computeFilter($filters, $not) {
   $r = [];
  foreach ($filters as $attr => $vals) {
    if (!$vals && $vals !== '') continue;
    $one = computeOneFilter($attr, $vals);
    $r[] = $not ? "(!$one)" : $one;
  }
  return $r;
}

function ldapAnd($l) {
  $r = implode('', $l);
  return count($l) > 1 ? "(&$r)" : $r;
}
function ldapOr($l) {
  $r = implode('', $l);
  return count($l) > 1 ? "(|$r)" : $r;
}

function apply_restrictions_to_filters($filters, $restrictions) {
  $r = array();
  foreach ($filters as $filter)
    $r[] = ldapAnd(array_merge([$filter], $restrictions));    
  return $r;
}

function wordsFilterRaw($searchedAttrs, $token) {
  $and = array();
  $words = preg_split("/[\s,]+/", $token, -1, PREG_SPLIT_NO_EMPTY);
  foreach ($words as $tok) {
    $or = array();
    foreach ($searchedAttrs as $attr => $prefix) $or[] = "($attr=" . ($prefix ? $prefix : '') . "*$tok*)";
    $and[] = ldapOr($or);
  }
  return ldapAnd($and);
}


function wordsFilter($searchedAttrs, $token) {
  $searchedAttrsRaw = array();
  foreach ($searchedAttrs as $attr) $searchedAttrsRaw[$attr] = null;
  return wordsFilterRaw($searchedAttrsRaw, $token);
}

function getLdapInfoMultiFilters($base, $filters, $attributes_map, $uniqueField, $sizelimit = 0, $timelimit = 0) {
  $rr = array();
  foreach ($filters as $filter) {
    $rr[] = getLdapInfo($base, $filter, $attributes_map, $sizelimit, $timelimit);
  }
  $r = mergeArraysNoDuplicateKeys($rr, $uniqueField);
  if ($sizelimit > 0)
      $r = array_splice($r, 0, $sizelimit);
  return $r;
}

function getFirstLdapInfo($base, $filter, $attributes_map, $timelimit = 0) {
  $r = getLdapInfo($base, $filter, $attributes_map, 1, $timelimit);
  return $r ? $r[0] : NULL;
}

function getLdapDN($dn, $attributes_map, $timelimit = 0) {
  $r = getLdapInfo($dn, null, $attributes_map, 1, $timelimit);
  return $r ? $r[0] : NULL;
}

function getLdapDN_with_DN_as_key($dn, $attributes_map, $timelimit = 0) {
    $r = getLdapDN($dn, $attributes_map, $timelimit);
    return $r ? array_merge(["key" => $dn], $r) : NULL;
}
  
function existsLdap($base, $filter) {
  $r = getLdapInfo($base, $filter, array(), 1);
  return (bool) $r;
}

function getLdapInfo($base, $filter, $attributes_map, $sizelimit = 0, $timelimit = 0) {
  global $DEBUG;

  if (preg_match("/\(entryDN=(.*)\)/", $filter, $m)) {
      // use LDAP read when possible
      $base = $m[1];
      $filter = null;
  }
  
  $before = microtime(true);

  $ds = global_ldap_open();

  if (!$filter) {
      if ($DEBUG) error_log("getting $base");
      $all_entries = $ds->read($base, array_keys($attributes_map), $timelimit);
  } else {
      if ($DEBUG) error_log("searching $base for $filter");
      $all_entries = $ds->search($base, $filter, array_keys($attributes_map), $sizelimit, $timelimit);
  }
  if (!$all_entries) return array();
  if ($DEBUG) error_log("found " . $all_entries['count'] . " results");

  unset($all_entries["count"]);
  $r = array();  
  foreach ($all_entries as $entry) {
    $r[] = _ldap_entry_remap($entry, $attributes_map);
  }

  //echo sprintf("// Elapsed %f\t%3d answers for $filter on $base\n", $before - microtime(true), count($r));

  return $r;
}

function _ldap_entry_remap($entry, $attributes_map) {
    $map = array();
    foreach ($attributes_map as $ldap_attr => $attr) {
      $ldap_attr_ = strtolower($ldap_attr);
      if (isset($entry[$ldap_attr_])) {
	$vals = $entry[$ldap_attr_];
	if ($ldap_attr === 'dn') {
	  $map[$attr] = $vals;
	} else if ($attr == "MULTI") {
	  // no remapping, but is multi-valued attr
	  unset($vals["count"]);
	  $map[$ldap_attr] = $vals;
	} else {
	  $map[$attr] = $vals["0"];
	}
      }
    }
    return $map;
}

function global_ldap_open($reOpen = false) {
    global $ldapDS;
    if (!$ldapDS || $reOpen) {
	global $LDAP_CONNECT, $LDAP_CONNECT_TRUSTED, $isTrustedIp;
	$ldapDS = MyLdap::connect($isTrustedIp ? $LDAP_CONNECT_TRUSTED : $LDAP_CONNECT);
    }
    return $ldapDS;
}

function ensure_ldap_close() {
    global $ldapDS;
    if ($ldapDS) {
      $ldapDS->close();
      $ldapDS = NULL;
    }
}

function initPhpCAS_raw($host, $port, $context, $CA_certificate_file) {
  phpCAS::client(CAS_VERSION_2_0, $host, intval($port), $context);
  phpCAS::setNoClearTicketsFromUrl(); // not needed and breaks on cross-domain (when the cookie is not accepted, which happens often nowadays)
  if ($CA_certificate_file)
    phpCAS::setCasServerCACert($CA_certificate_file);
  else
    phpCAS::setNoCasServerValidation();
  //phpCAS::setLang(PHPCAS_LANG_FRENCH);
}

function initPhpCAS() {
  if (class_exists('phpCAS')) return;
  require_once 'CAS.php';
  global $CAS_HOST, $CAS_CONTEXT, $CA_certificate_file;
  initPhpCAS_raw($CAS_HOST, '443', $CAS_CONTEXT, $CA_certificate_file);
}

function forceCASAuthentication() {
  initPhpCAS();
  //phpCAS::setNoClearTicketsFromUrl(); // ensure things work without cookies (for safari on cross-domain)
  phpCAS::handleLogoutRequests(false);
  phpCAS::forceAuthentication();

  if (isset($_REQUEST['logout'])) {
    phpCAS::logout();
  }

  // no need to lock the session which is only used to store user identifier
  // => release the session:
  session_write_close();
  
  // will be used by function "GET_uid"
  $_SERVER["HTTP_CAS_USER"] = phpCAS::getUser();
}

function isCASAuthenticated() {
  initPhpCAS();
  if (phpCAS::isAuthenticated()) {
      // will be used by function "GET_uid"
      $_SERVER["HTTP_CAS_USER"] = phpCAS::getUser();
  }
}

function ipTrusted() {
    global $APP_AUTH_BEARER_TOKENS, $TRUSTED_IPS, $isTrustedIp;
    $bearerToken = removePrefixOrNULL(@$_SERVER['HTTP_AUTHORIZATION'], 'Bearer ');
    $isTrustedIp = $TRUSTED_IPS && in_array($_SERVER['REMOTE_ADDR'], $TRUSTED_IPS) ||
        $bearerToken && $APP_AUTH_BEARER_TOKENS && in_array($bearerToken, $APP_AUTH_BEARER_TOKENS);
    return $isTrustedIp;
}

function ipTrustedOrExit() {
    if (!ipTrusted()) {
        error_log("your IP (" . $_SERVER['REMOTE_ADDR'] . ") is not allowed");
        header('HTTP/1.0 401 Unauthorized'); 
        exit();
    }
}

function echoJson($o) {
  ensure_ldap_close();
  if (isset($_GET["callback"])) {
    header('Content-type: application/javascript; charset=UTF-8');
    echo $_GET["callback"] . "(" . json_encode($o) . ");";
  } else {
      header('Content-type: application/json; charset=UTF-8');
      echo json_encode($o);  
  }
}

function identiqueMap($list) {
    $map = array();
    foreach ($list as $e) $map[$e] = $e;
    return $map;
}

function mergeArraysNoDuplicateKeys($rr, $uniqueField) {
    $keys = array();
    $r = array();
    foreach ($rr as $one_array) {
	foreach ($one_array as $e) {
	    $key = $e[$uniqueField];
	    if (isset($keys[$key])) continue;
	    $keys[$key] = 1;
	    $r[] = $e;
	}
    }
    return $r;
}

function exact_match_first($r, $token) {
    $exact = array();
    $i = 0;
    while ($i < count($r)) {
	$e = $r[$i];
	if (in_array($token, array_values($e))) {
	    $exact[] = $e;
	    array_splice($r, $i, 1);
	} else {
	    $i++;
	}
    }
    return array_merge($exact, $r);
}

function remove_businessCategory($r) {
    foreach ($r as &$e) {
	unset($e["businessCategory"]);
    }
    return $r;
}

// after exact_match_first, rawKey can be safely removed: it is used for search token=matiXXXXX, "key" will contain groups-matiXXXXXX and won't match. "rawKey" will match!
function remove_rawKey(&$r) {
    foreach ($r as &$e) {
	unset($e["rawKey"]);
    }
}
// modifyTimestamp is only used by allGroups
function remove_modifyTimestamp(&$r) {
    foreach ($r as &$e) {
	unset($e["modifyTimestamp"]);
    }
}

function contains($hay, $needle) {
    return strpos($hay, $needle) !== false;
}

function startsWith($hay, $needle) {
  return substr($hay, 0, strlen($needle)) === $needle;
}

function removePrefix($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : $s;
}
function removePrefixOrNULL($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : NULL;
}

function error($msg) {
   header("HTTP/1.0 400 $msg");
   echo("// $msg\n");
}

function fatal($msg) {
   header("HTTP/1.0 400 $msg");
   echo("// $msg\n");
   exit(0);
}

// taken more mantisbt
function ldap_escape_string( $p_string ) {
  $t_find = array( '\\', '*', '(', ')', '/', "\x00" );
  $t_replace = array( '\5c', '\2a', '\28', '\29', '\2f', '\00' );

  $t_string = str_replace( $t_find, $t_replace, $p_string );

  return $t_string;
}

function ldap_escape_string_allow_subInitial($s) {
  $s_ = rtrim($s, '*');
  return ldap_escape_string($s_) . ($s_ === $s ? '' : '*');
}

function mayRemap($map, $k) {
  return isset($map[$k]) ? $map[$k] : $k;
}

function array_flatten_non_rec($r) {
    return sizeof($r) > 0 ? call_user_func_array('array_merge', $r) : array();
}

// json_encode on the result of array_unique/array_filter may serialize as object instead of array (on this subject https://externals.io/message/116097 )
function array_unique_($array) {
    return array_values(array_unique($array));
}
function array_filter_($array, $cb) {
    return array_values(array_filter($array, $cb));
}

function getAndUnset(&$a, $prop) {
  if (isset($a[$prop])) {
    $v = $a[$prop];
    unset($a[$prop]);
    return $v;
  } else {
    return null;
  }
}

function lowercase_and_stripAccents($s) {
    return str_replace([
        'à', 'â', 'ä', 'á', 'ã', 'å',
        'î', 'ï', 'ì', 'í',
        'ô', 'ö', 'ò', 'ó', 'õ', 'ø',
        'ù', 'û', 'ü', 'ú',
        'é', 'è', 'ê', 'ë',
        'ç', 'ÿ', 'ñ',
    ], [
        'a', 'a', 'a', 'a', 'a', 'a',
        'i', 'i', 'i', 'i',
        'o', 'o', 'o', 'o', 'o', 'o',
        'u', 'u', 'u', 'u',
        'e', 'e', 'e', 'e',
        'c', 'y', 'n',
    ], mb_strtolower($s, 'UTF-8'));
}

function isAscii($str) {
    return !preg_match('/[^\x00-\x7F]/', $str);
}

function groupByEtiquette($l) {
    if (!$l) return [];
    $r = [];
    foreach ($l as $s) {
        if (preg_match('/^\{([^}]*)\}(.*)/', $s, $match)) {
            $r[$match[1]] = $match[2];
        } else {
            error_log("weird non prefixed value $s");
        }
    }
    return $r;
}

function format_postalAddress(&$e) {
    if (@$e['postalAddress']) {
	$e['postalAddress'] =
	    str_replace("\\\n", '\$', str_replace('$', "\n", $e['postalAddress']));
    }
}

?>
