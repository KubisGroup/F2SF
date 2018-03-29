<?php
require __DIR__.'/Fler_API_REST_Driver.php';
$flerapi = new Fler_API_REST_Driver();

$public_key = "o9al8ow7mosazrhclsi6";
$secret_key = "yG3XMnJC2A2G3u0x6Z458DN8PW1266xZvKhi03bU";

// váš veřejný klíč + soukromý klíč
// viz: www.fler.cz/prodejce/nastroje/api?view=keys
$flerapi->setKeys($public_key, $secret_key);
$flerapi->setHost('http://www.fler.cz');
?>
