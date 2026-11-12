<?php

function protocol()
{
    if (!empty($_SERVER["HTTP_X_FORWARDED_PROTO"]))
        return "https://";
    else
        return stripos($_SERVER['SERVER_PROTOCOL'] ?? '', 'https') !== false ? 'https://' : 'http://';
}

function currentDomain()
{
    if (!empty($_SERVER["HTTP_X_FORWARDED_HOST"]))
        return protocol() . $_SERVER["HTTP_X_FORWARDED_HOST"];
    else
        return protocol() . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
