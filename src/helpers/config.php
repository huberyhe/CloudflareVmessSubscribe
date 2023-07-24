<?php
$config = array();

function read_config(string $file): array
{
    $config = parse_ini_file($file);
    return $config;
}