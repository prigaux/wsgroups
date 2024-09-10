<?php

// simple wrapper to real CAS /login

set_include_path("..:" . get_include_path());
require_once ('lib/supannPerson.inc.php');

if (isset($_GET["id"])) {
    $id = $_GET["id"];

    session_start();
    $ticket = session_id();
    $ids = $_SESSION['ids'];
    $service = $_SESSION['service'];

    // validating $id:
    if (!in_array($id, $ids)) exit("invalid id");
    // NB: service is already validated

    $_SESSION['id'] = $id;

    $redirect = $service . (strpos($service, '?') !== false ? '&' : '?') . "ticket=$ticket";
    header("Location: $redirect");
} else if (isset($_GET["ticket"])) {
    $ticket = $_GET["ticket"];
    $service = $_GET["service"];

    if (!$CAS_BIU_BIS_WRAPPER_AUTHORIZED_SERVICES || !preg_match($CAS_BIU_BIS_WRAPPER_AUTHORIZED_SERVICES, $service)) exit("service not allowed");

    //ini_set('display_errors', 1);
    //error_reporting(E_ALL);     

    $uid = ticket_to_uid($service, $ticket);
    $ids = get_barcodes($uid);

    // on crée la session avec comme id le ticket. C'est nécessaire pour le serviceValidate qui prend en session service & id
    session_id($ticket);
    session_start();
    $_SESSION['service'] = $service;
    if (count($ids) === 0) {
        echo "Vous n'avez pas de profil compte lecteur BIS";
    } else if (count($ids) === 1) {
        // on prend le premier id, pas de choix à demander
        $_SESSION['id'] = array_values($ids)[0];
        $redirect = $service . (strpos($service, '?') !== false ? '&' : '?') . "ticket=$ticket";
        header("Location: $redirect");
    } else {
        $_SESSION['ids'] = array_values($ids);
        // on laisse l'utilisateur choisir quel id utiliser
        display_choose_id_html_page($ids);
    }
} else {
    $service = $_GET["service"];
    $current_url = "$CAS_BIU_BIS_URL/login?service=" . urlencode($service);
    $redirect = "https://$CAS_HOST/cas/login?service=" . urlencode($current_url);
    header("Location: $redirect");
}

function ticket_to_uid($service, $ticket) {
    global $CAS_BIU_BIS_URL, $CAS_HOST;
    $our_service = "$CAS_BIU_BIS_URL/login?service=" . urlencode($service);

    $xml = wget("https://$CAS_HOST/cas/proxyValidate?service=" . urlencode($our_service) . "&ticket=" . urlencode($ticket));
    
    if (preg_match('!<cas:user>(.*)</cas:user>!', $xml, $m)) {
        return $m[1];
    } else {
        exit($xml);
    }
}

function wget($url) {
    $session = curl_init($url);

    // Don't return HTTP headers. Do return the contents of the call
    curl_setopt($session, CURLOPT_HEADER, false);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

    $data = curl_exec($session);
    curl_close($session);

    return $data;
}    

function find_prefix($array, $prefix) {
    foreach ($array as $s) {
        $s_ = removePrefixOrNULL($s, $prefix);
        if ($s_) return $s_;
    }
    return null;
}

function get_barcodes($uid) {
    global $PEOPLE_DN;
    $attrs = getFirstLdapInfo($PEOPLE_DN, "(uid=$uid)", [ 'up1Profile' => 'MULTI' ]);

    $barcodes = [];
    foreach ($attrs["up1Profile"] ?? [] as $profile_s) {
        $profile = parse_up1Profile_one_raw($profile_s);
        $kind = removePrefixOrNULL($profile["up1Source"], "{COMPTEX:BIS}");
        if ($kind) {
            $barcode = find_prefix($profile['supannRefId'] ?? [], '{UAI:0752705H:BARCODE}');
            if ($barcode) {
                $barcodes[$kind] = $barcode;
            }
        }
    }
    return $barcodes;
}

function display_choose_id_html_page($ids) {
    $kind_to_label = [ 'sorb' => 'Sorbonne', 'geo' => 'Géographie' ];
    ?>      
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            html {
                font-family: Arial, Helvetica, sans-serif;
            }
            section {
                text-align: center;
                font-size: 1.4rem;
                margin: 2rem;
            }
            a {
                border: 4px solid #039fae;
                border-radius: 4px;
                padding: 0.5rem 1rem;
                margin: 2rem;
                color: black;
                text-decoration: none;
                display: inline-block;
            }
        </style>
        <script>
            // remove ticket to allow easy reload of page
            try { window.history.replaceState({}, null, location.href.replace(/[?&]ticket=.*/, '')) } catch (e) {}
        </script>
    </head>
    <body>
    <header>
        <img src="bis-logo.png">
    </header>
    <section>
        Vous avez un compte lecteur Sorbonne et un compte lecteur Géographie. <p></p>Lequel voulez vous utiliser ?
        <p></p>
        <?php
            foreach ($ids as $kind => $id) {
                echo "<a href='?id=$id'>$kind_to_label[$kind]</a>\n";
            }
        ?>
    </section>
    </body>
    </html>   
    <?php   
}
