<?php
ini_set('display_errors', '1');

const DB_USERNAME = 'sanya9_23';
const DB_PASSWORD = 'sanya9_23';
const DB_DATABASE = 'test';
const DB_REALRADIO_DATABASE = 'realradio';
const DB_TDSPORTAL_DATABASE = 'tdsportal';

//const DB_USERNAME = 'oc_dbuser';
//const DB_PASSWORD = 'use_Base_HereJ!38';
//const DB_DATABASE = 'orbita74';
//const DB_REALRADIO_DATABASE = 'realradio';
//const DB_TDSPORTAL_DATABASE = 'tdsportal';

function connectToDatabase ($db, $user, $password)
{
    $host = 'localhost';
    $charset = 'utf8';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}