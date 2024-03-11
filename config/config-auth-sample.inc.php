<?php

$LEVEL1_FILTER = "(supannEntiteAffectation=XXXX*)";
$LEVEL2_FILTER = "(supannEntiteAffectation=XXXXX)";
$LEVEL1_SEARCH_FILTER = "(|(supannEntiteAffectation=YYYY)$LEVEL1_FILTER)";


$LDAP_CONNECT = 
  array(
	'HOST' =>  'ldap://ldap',
	'BIND_DN' => 'cn=xxx,ou=admin,dc=univ,dc=fr',
	'BIND_PASSWORD' => 'xxx'
	);
$LDAP_CONNECT_LEVEL1 = 
  array(
	'HOST' =>  'ldap://ldap',
	'BIND_DN' => 'cn=xxx,ou=admin,dc=univ,dc=fr',
	'BIND_PASSWORD' => 'xxx'
	);
$LDAP_CONNECT_LEVEL2 = 
  array(
	'HOST' =>  'ldap://ldap',
	'BIND_DN' => 'cn=xxx,ou=admin,dc=univ,dc=fr',
	'BIND_PASSWORD' => 'xxx'
	);
