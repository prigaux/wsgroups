<?php

$service = $_GET["service"];
$ticket = $_GET["ticket"];

session_id($ticket);
session_start();
$id = $_SESSION['id'];
$saved_service = $_SESSION['service'];
error_log("serviceValidate $ticket: id=$id saved_service=$saved_service");

# ticket can be validated only once. So destroy session
session_unset();
session_destroy();

if ($saved_service !== $service) {
    error_log("expected $saved_service , got $service");
    exit("service not allowed");
}

echo 
"<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
    <cas:authenticationSuccess>
        <cas:user>$id</cas:user>
    </cas:authenticationSuccess>
</cas:serviceResponse>";


