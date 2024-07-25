<?php

# https://glpi-front.univ-paris1.fr/front/ticket.form.php?id=115582
#
global $employeeTypes; $employeeTypes = [
    "Professeur émérite" => [ "weight" => "01", "name-gender-f" => "Professeure émérite" ],

    "Professeur des universités" => [ "weight" => "02", "name-gender-f" => "Professeure des universités" ],
    "Directeur de recherche" => [ "weight" => "02", "name-gender-f" => "Directrice de recherche" ],
    "Cpj emploi tit. corps professeur" => [ "weight" => "02", "name" => "Professeur des universités junior", "name-gender-f" => "Professeure des universités junior" ],

    "Maître de conférences" => [ "weight" => "03", "name-gender-f" => "Maîtresse de conférences" ],
    "Chargé de recherche" => [ "weight" => "03", "name-gender-f" => "Chargée de recherche" ],

    "Professeur agrégé" => [ "weight" => "04", "name-gender-f" => "Professeure agrégée" ],

    "Professeur d'eps" => [ "weight" => "05", "name-gender-f" => "Professeure d'eps" ],
    "Professeur certifié" => [ "weight" => "05", "name-gender-f" => "Professeure certifiée" ],
    "Professeur des lycées professionnels" => [ "weight" => "05", "name-gender-f" => "Professeure des lycées professionnels" ],

    "Professeur invité" => [ "weight" => "06", "name-gender-f" => "Professeure invitée" ],
    "Associé professeur mi-tps" => [ "weight" => "06", "name" => "Associé professeur", "name-gender-f" => "Associée professeure" ],
    "Chercheur associé" => [ "weight" => "06", "name-gender-f" => "Chercheuse associée" ],
    "Associé mcf mi-tps" => [ "weight" => "06", "name" => "Associé mcf", "name-gender-f" => "Associée mcf" ],
    "Associé mcf" => [ "weight" => "06", "name-gender-f" => "Associée mcf" ],

    "Contractuel chercheur lru" => [ "weight" => "07", "name-gender-f" => "Contractuelle chercheuse lru" ],
    "Contractuel enseignant chercheur lru" => [ "weight" => "07", "name" => "Enseignant-Chercheur contractuel", "name-gender-f" => "Enseignante-Chercheure contractuelle" ],
    "Contrat post-doctoral droit public code rech" => [ "weight" => "07", "name" => "Chercheur post-doctoral", "name-gender-f" => "Chercheure post-doctoral" ],

    "Ater" => [ "weight" => "08" ],
    "Ater mi-temps" => [ "weight" => "08", "name" => "Ater" ],

    "Doctorant epes/ep recherche sans enseignement" => [ "weight" => "09", "name-gender-f" => "Doctorante epes/ep recherche sans enseignement" ],
    "Doctorant epes ou ep recherche" => [ "weight" => "09", "name-gender-f" => "Doctorante epes ou ep recherche" ],
    "Doctorant sous convention" => [ "weight" => "09", "name-gender-f" => "Doctorante sous convention" ],
    "Hébergé doctorant sans recherche" => [ "weight" => "09", "name-gender-f" => "Hébergée doctorante sans recherche" ],

    "Hébergé administratif catégorie a" => [ "weight" => "11", "name-gender-f" => "Hébergée administratif catégorie a" ],

    "Chargé d'enseignement" => [ "weight" => "12", "name-gender-f" => "Chargée d'enseignement" ],
];
