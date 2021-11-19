<?php
declare(strict_types=1);
require 'Pdo.php';

$sourcePDO = connectToDatabase(DB_TEMP_DATABASE, DB_USERNAME, DB_PASSWORD);
$targetPDO = connectToDatabase(DB_DATABASE, DB_USERNAME, DB_PASSWORD);

const USER = '101';
const MODEL = '0';
const QUANTITY = '10000';
const STOCK_STATUS_ID = '6';
const MANUFACTURER_ID = '0';
const SHIPPING = '0';
const TAX_CLASS_ID = '0';
const WEIGHT_CLASS_ID = '1';
const LENGTH_CLASS_ID = '1';
const SORT_ORDER = '1';
const STATUS = '1';
const RENTER = '1';
const LANGUAGE_ID = '1';

function sendProducts($sourcePDO, $targetPDO)
{
    $countQuery = $sourcePDO->prepare("SELECT count(*) FROM product");
    $countQuery->execute();

    $count = $countQuery->fetchColumn();

    for ($i = 0; $i < $count; $i += 100) {
        $sql = "SELECT * FROM product WHERE price > 0 AND image is not null LIMIT 100 OFFSET $i";

        $productsQuery = $sourcePDO->prepare($sql);
        $productsQuery->execute();

        $products = $productsQuery->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $product) {
            $sql = 'SELECT EXISTS(SELECT * FROM oc_product WHERE parse_id = :parse_id)';

            $isExistsQuery = $targetPDO->prepare($sql);
            $isExistsQuery->bindParam(':parse_id', $product['id']);

            $isExistsQuery->execute();

            $isExists = $isExistsQuery->fetch(PDO::FETCH_NUM);
            if ($isExists[0] != 0) {
                continue;
            }

            $categories = convertCategory($sourcePDO, $product['id']);
            $addedProduct = addProduct($targetPDO, $product);
            $product['product_id'] = $addedProduct;

            addCategories($targetPDO, $product, $categories);
            addProductDescription($targetPDO, $product);
            addProductToStore($targetPDO, $product);
            addProductRelative($targetPDO, $product);
            addProductImage($targetPDO, $product);
        }
    }
}

function convertCategory($sourcePDO, $productId)
{
    $categories = getCategories($sourcePDO, $productId);
    $mainCategory = 679;

    $convertedCategories = [];
    $convertedCategories[] = $mainCategory;

    foreach ($categories as $category) {
        $currentCategory = $category['category_id'];
        $convertedCategories[] = convertCurrentCategory($currentCategory);
    }
    return array_unique($convertedCategories);
}

function convertCurrentCategory($category)
{
    $currentToConvertedCategories = [
        1 => 679,
        2 => 680,
        3 => 681,
        4 => 682,
        5 => 683,
        6 => 684,
        7 => 478,
        8 => 483,
        9 => 466,
        10 => 486,
        11 => 685,
        12 => 686,
        13 => 687,
        14 => 688,
        15 => 689,
        16 => 467,
        18 => 718,
        19 => 690,
        20 => 691,
        21 => 692,
        22 => 693,
        23 => 694,
        24 => 695,
        25 => 488,
        26 => 696,
        27 => 697,
        28 => 698,
        29 => 699,
        30 => 700,
        31 => 701,
        32 => 702,
        33 => 703,
        34 => 704,
        35 => 470,
        36 => 705,
        37 => 706,
        38 => 707,
        39 => 708,
        40 => 709,
        41 => 710,
        42 => 711,
        43 => 712,
        44 => 713,
        45 => 714,
        46 => 715,
        47 => 716,
        48 => 483,
        49 => 483,
        50 => 483,
        51 => 483,
        52 => 483,
        53 => 483,
        54 => 483,
        55 => 470,
        56 => 490,
        57 => 488,
        58 => 482,
        59 => 483,
        60 => 717,
        61 => 694,
        62 => 494,
        63 => 686,
        64 => 686,
    ];
    return $currentToConvertedCategories[$category];
}

function getCategories($sourcePDO, $productId)
{
    $categoriesQuery = $sourcePDO->prepare("SELECT * FROM product_category WHERE product_id = $productId");
    $categoriesQuery->execute();

    return $categoriesQuery->fetchAll();
}

function addCategories($targetPDO, $product, $categories)
{
    foreach ($categories as $category) {
        $sql = "INSERT INTO oc_product_to_category SET product_id = $product[product_id], category_id = $category";

        $query = $targetPDO->prepare($sql);
        $query->execute();
    }
}

function addProduct($targetPDO, $product)
{
    echo PHP_EOL.'Добавление товара с ID #'.$product['id'];

    $sql = "INSERT INTO oc_product SET
			user_id = " . USER . ",
			model = " . MODEL . ",
			sku = '',
			upc = '',
			ean = '',
			jan = '',
			isbn = '',
			mpn = '',
			location = '',
			quantity = " . QUANTITY . ",
			stock_status_id = " . STOCK_STATUS_ID . ",
			manufacturer_id = " . MANUFACTURER_ID . ",
			shipping = " . SHIPPING . ",
			tax_class_id = " . TAX_CLASS_ID . ",
			weight_class_id = " . WEIGHT_CLASS_ID . ",
			length_class_id = " . LENGTH_CLASS_ID . ",
			sort_order = " . SORT_ORDER . ",
			status = " . STATUS . ",
			date_available = NOW(),
			date_added = NOW(),
			date_modified = NOW(),
			is_parsed = '1',
			parse_id = $product[id],
		    image = '$product[image]',
		    price = '$product[price]'";
    $addedProductQuery = $targetPDO->prepare($sql);
    $addedProductQuery->execute();
    return $targetPDO->lastInsertId();
}

function addProductDescription($targetPDO, $product)
{
    $productMetaName = "$product[title] купить в Челябинске - ТЦ Орбита";
    $productMetaDescription = "Торговый Центр Орбита город Челябинск. Свердловский тракт 8. Онлайн каталог. Тысячи товаров, сотни продавцов. Доставка по Челябинску.";

    $sql = "INSERT INTO oc_product_description SET 
                                       tag = '',
                                       meta_keyword = '',
                                       product_id = :product_id,
                                       language_id = :language_id,
                                       name = :name,
                                       description = :description,
                                       meta_title = :metaName,
                                       meta_description = :metaDescription";

    $query = $targetPDO->prepare($sql);

    $query->bindParam(':product_id', $product['product_id']);
    $query->bindValue(':language_id', LANGUAGE_ID);
    $query->bindParam(':name', $product['title']);
    $query->bindParam(':description', $product['description']);
    $query->bindParam(':metaName', $productMetaName);
    $query->bindParam(':metaDescription', $productMetaDescription);

    $query->execute();
}

function addProductToStore($targetPDO, $product)
{
    $sql = "INSERT INTO oc_product_to_store SET product_id = $product[product_id], store_id = 0";

    $query = $targetPDO->prepare($sql);
    $query->execute();
}

function addProductRelative($targetPDO, $product)
{
    $sql = "INSERT INTO oc_product_to_renter SET product_id = $product[product_id], renter_id = " . RENTER;

    $query = $targetPDO->prepare($sql);
    $query->execute();
}

function addProductImage($targetPDO, $product)
{
    $sql = "INSERT INTO oc_product_image SET
                                 product_id = $product[product_id],
                                 image = '$product[image]',
                                 sort_order = 0";

    $query = $targetPDO->prepare($sql);
    $query->execute();
}

sendProducts($sourcePDO, $targetPDO);