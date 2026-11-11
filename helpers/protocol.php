<?php

function protocol()
{
    return stripos($_SERVER['SERVER_PROTOCOL'] ?? '', 'https') !== false ? 'https://' : 'http://';
}

function currentDomain()
{
    return protocol() . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
