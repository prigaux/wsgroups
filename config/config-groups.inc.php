<?php

// specific Paris1:
$ANNEE = 2024;
$ANNEE_PREV = 2023;
$ALT_STRUCTURES_DN = "ou=structures,o=Paris1,".$BASE_DN;
$DIPLOMA_DN = "ou=$ANNEE,ou=diploma,o=Paris1,".$BASE_DN;
$DIPLOMA_PREV_DN = "ou=$ANNEE_PREV,ou=diploma,o=Paris1,".$BASE_DN;
$DIPLOMA_ATTRS = array("ou" => "key", "description" => "description", "modifyTimestamp" => "modifyTimestamp");

// supann:
$GROUPS_DN = "ou=groups,".$BASE_DN;
$STRUCTURES_DN = "ou=structures,".$BASE_DN;
$ROLE_GENERIQUE_DN = "ou=supannRoleGenerique,ou=tables,".$BASE_DN;
$ETABLISSEMENT_TABLE_DN = "ou=supannEtablissement,ou=tables,".$BASE_DN;
$ACTIVITE_TABLE_DN = "ou=supannActivite,ou=tables,".$BASE_DN;

// NB: "ou" needed for groups-roles for econvention
$GROUPS_ATTRS = array("cn" => "key", "ou" => "ou", "description" => "name", "modifyTimestamp" => "modifyTimestamp", "seeAlso" => "MULTI");
$STRUCTURES_ATTRS = array("supannCodeEntite" => "key", "ou" => "name", "o" => "name", "description" => "description", "businessCategory" => "businessCategory", "labeledURI" => "labeledURI", "modifyTimestamp" => "modifyTimestamp", "telephoneNumber" => "telephoneNumber", "postalAddress" => "postalAddress", "up1Flags" => "MULTI");
$ROLE_GENERIQUE_ATTRS = array("up1TableKey" => "key", "displayName" => "name", 
    'displayName;x-gender-m' => "name-gender-m", 'displayName;x-gender-f' => "name-gender-f", 
    "displayName;x-short" => "name-short",
    'displayName;x-gender-m;x-short' => "name-gender-m-short", 'displayName;x-gender-f;x-short' => "name-gender-f-short", 
    "up1Flags" => "weight");
$ETABLISSEMENT_TABLE_ATTRS = array("up1TableKey" => "key", "displayName" => "name");
$ACTIVITE_TABLE_ATTRS = array("up1TableKey" => "key", "displayName" => "name",
    'displayName;x-gender-m' => "name-gender-m", 'displayName;x-gender-f' => "name-gender-f", 
);

$AFFILIATION2TEXT = array("faculty" => "enseignants-chercheurs", 
			  "teacher" => "enseignants et chargés d'enseignement", 
			  "student" => "étudiants", 
			  "staff" => "Biatss", 
			  "researcher" => "chercheurs", 
			  "emeritus" => "professeurs émérites", 
			  "affiliate" => "invités", 
			  );

$BUSINESSCATEGORY2TEXT = array("research" => "Laboratoires de recherche", 
			       "library" => "Bibliothèques", 
			       "doctoralSchool" => "Écoles doctorales", 
			       "administration" => "Services", 
			       "pedagogy" => "Composantes personnels", 
			       );

$MAX_PARENTS_IN_DESCRIPTION = 4;

?>
