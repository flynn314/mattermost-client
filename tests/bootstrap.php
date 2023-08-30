<?php
use Dotenv\Dotenv;

defined('ROOT') or define('ROOT', __DIR__.'/../');

$dotenv = Dotenv::createImmutable(ROOT);
$dotenv->load();
