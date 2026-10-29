<?php

header('Content-Type: text/html; charset=utf-8');

$app = 'http://127.0.0.1:8081/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Atrium</title>
</head>
<body>
    <p>Atrium is running on PHP 8.4 at <a href="<?= htmlspecialchars($app, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($app, ENT_QUOTES, 'UTF-8') ?></a>.</p>
    <p>XAMPP Apache on this machine is PHP 8.0, which cannot run this app.</p>
</body>
</html>
