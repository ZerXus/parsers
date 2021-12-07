<?php
set_time_limit(24000);

use Symfony\Component\DomCrawler\Crawler;
require __DIR__ . '/../vendor/autoload.php';

require '../PDO.php';
require 'components/ParentCategories.php';
require 'components/ChildCategories.php';
require 'components/Product.php';

function run()
{
    $fromUrl = 'https://tdsportal.ru/catalog/';

    $html = getHtml($fromUrl);
    $crawler = new Crawler($html, $fromUrl);

    $siteParentCategories = parseParentCategories($crawler);
    saveParentCategories($siteParentCategories);
//    saveChildCategories();

//    saveProduct();
}

function getHtml($url)
{
    $file = __DIR__ . '/cache/' . md5($url);
    if (file_exists($file)) {
        return unserialize(file_get_contents($file));
    } else {
        $html = file_get_contents($url);
        file_put_contents($file, serialize($html));
        return $html;
    }
}

function crawler($url)
{
    return new Crawler(getHtml($url), 'https://tdsportal.ru');
}

$pdo = connectToDatabase(DB_TDSPORTAL_DATABASE, DB_USERNAME, DB_PASSWORD);
run();