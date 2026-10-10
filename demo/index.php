<?php
$dst = 'https://demo.vexaris.net/';
if (!empty($_SERVER['QUERY_STRING'])) $dst .= '?' . $_SERVER['QUERY_STRING'];
header('Location: ' . $dst, true, 301);
exit;
