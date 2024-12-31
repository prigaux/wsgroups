<?php

require_once ('lib/common.inc.php');

function echo_vcard($users) {

    function _e($value) {
        return str_replace("\n", '\n', str_replace(",", '\,', str_replace(";", '\;', $value)));
    }

    function e($value) {
        return is_array($value) ? array_map('_e', $value) : _e($value);
    }

    function _format($vals) {
        $formattedVCardString = '';
        foreach ($vals as $key => $v) {
            if (!$v) continue;
            $vl = is_array($v) ? $v : [$v];

            $key_ = str_replace('_', ';TYPE=', $key);
            foreach ($vl as $v) {
                $encodingPrefix = isASCII($v) ? '' : ';CHARSET=UTF-8';
                $formattedVCardString .= "$key_$encodingPrefix:$v\r\n";
            }
        }
        return $formattedVCardString;

    }

    function _formatPostalAddress($s) {
        if (!$s) return '';
        $rest = explode("\n", $s);
        $l1 = array_shift($rest);
        $l2 = array_shift($rest);
        preg_match('/^(\d{5}) (.*)/', $l2, $m);
        $middle = $m ? [ $m[2], '', $m[1]] : [ $l2 ];
        return implode(';', array_map('e', array_merge(['', '', $l1], $middle, $rest)));
    }

    function _to_vcard($user) {
        return _format([
            'BEGIN' => 'VCARD',
            'VERSION' => '3.0',
            'FN' => e($user['displayName']),
            'N' => e($user['sn']) . ';' . e($user['givenName']) . ';;;',
            'TEL_WORK,VOICE' => e($user['telephoneNumber']),
            'TEL_WORK,FAX' => e($user['facsimileTelephoneNumber']),
            'EMAIL_WORK,INTERNET' => e($user['mail']),
            'ADR_WORK' => _formatPostalAddress($user['postalAddress']), // no LABEL otherwise Android displays address twice
            'ORG' => 'Université Paris Panthéon-Sorbonne',
            'TITLE' => e(safe_array_map(function ($e) { return $e['name']; }, $user['supannActivite-all'])),
            'ROLE' => e($user['info']),
            'URL' => $user['labeledURI'],
            'END' => 'VCARD',
        ]);
    }

    $users = array_filter($users, function ($user) { return $user['uid'] !== 'supannListeRouge'; });

    $filename_prefix = '';
    if (count($users) === 1) {
        $filename_prefix = $users[0]["mail"];
        if (!$filename_prefix) $filename_prefix = $users[0]["uid"];
    }
    if (!$filename_prefix) $filename_prefix = "contacts";

    ensure_ldap_close();
    header('Content-type: text/vcard; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename_prefix . '.vcf"');
    foreach ($users as $user) {
        echo _to_vcard($user);
    }
}
