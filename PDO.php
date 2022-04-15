<?php
ini_set('display_errors', '1');

const DB_USERNAME = 'user';
const DB_PASSWORD = 'user';
const DB_DATABASE = 'test';
const DB_REALRADIO_DATABASE = 'user';
const DB_TDSPORTAL_DATABASE = 'user';

//const DB_USERNAME = 'user';
//const DB_PASSWORD = 'user';
//const DB_DATABASE = 'user';
//const DB_REALRADIO_DATABASE = 'user';
//const DB_TDSPORTAL_DATABASE = 'user';

function connectToDatabase ($db, $user, $password)
{
    $host = 'localhost';
    $charset = 'utf8';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
