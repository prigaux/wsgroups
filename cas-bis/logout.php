<?php

// simple wrapper to real CAS /logout

set_include_path("..:" . get_include_path());
require_once ('config/config.inc.php');
require_once ('lib/common.inc.php');

initPhpCAS();
phpCAS::logout();
