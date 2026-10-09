<?php
$dst = 'https://demo.vexaris.com.tr/';
if (!empty($_SERVER['QUERY_STRING'])) $dst .= '?' . $_SERVER['QUERY_STRING'];
header('Location: ' . $dst, true, 302);
exit;
