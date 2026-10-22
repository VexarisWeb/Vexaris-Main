<?php
$dst = 'https://demo.vexaris.net/demo';
if (!empty($_SERVER['QUERY_STRING'])) $dst .= '?' . $_SERVER['QUERY_STRING'];
header('Location: ' . $dst, true, 301);
exit;
