<?php
declare(strict_types=1);
require '../PDO.php';

$sourcePDO = connectToDatabase(DB_TDSPORTAL_DATABASE, DB_USERNAME, DB_PASSWORD);
$targetPDO = connectToDatabase(DB_DATABASE, DB_USERNAME, DB_PASSWORD);

const USER = '108';
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
const RENTER = '123';
const LANGUAGE_ID = '1';

function sendProducts()
{
    global $sourcePDO, $targetPDO;

    $countQuery = $sourcePDO->prepare("SELECT count(*) FROM product");
    $countQuery->execute();

    $count = $countQuery->fetchColumn();

    for ($i = 0; $i < $count; $i += 100) {
        $sql = "SELECT * FROM product WHERE price > 0 AND image is not null LIMIT 100 OFFSET $i";

        $productsQuery = $sourcePDO->prepare($sql);
        $productsQuery->execute();

        $products = $productsQuery->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $product) {
            $sql = 'SELECT EXISTS(SELECT * FROM oc_product WHERE parce_id = :parse_id)';

            $isExistsQuery = $targetPDO->prepare($sql);
            $isExistsQuery->bindParam(':parse_id', $product['id']);

            $isExistsQuery->execute();

            $isExists = $isExistsQuery->fetch(PDO::FETCH_NUM);
            if ($isExists[0] != 0) {
                continue;
            }

            $categories = convertCategory($product['id']);
            $product['product_id'] = addProduct($product);

            addCategories($product, $categories);
            addProductDescription($product);
            addProductToStore($product);
            addProductRelative($product);
            addProductImage($product);
        }
    }
}

function convertCategory($productId)
{
    global $sourcePDO;
    $categories = getCategories($productId);
    $mainCategory = 329;

    $convertedCategories = [];
    $convertedCategories[] = $mainCategory;

    foreach ($categories as $category) {
        $currentCategory = $category['category_id'];
        $convertedCategories[] = convertCurrentCategory($currentCategory);
    }
    return array_filter(array_unique($convertedCategories));
}

function convertCurrentCategory($category)
{
    $currentToConvertedCategories = [
        0 => null,
        1 => 229,
        2 => 413,
        3 => 726,
        4 => 727,
        5 => 728,
        6 => 729,
        7 => 730,
        8 => 731,
        9 => 732,
        10 => 733,
        11 => 734,
        12 => 735,
        13 => 736,
        14 => 737,
        15 => 738,
        16 => 739,
        17 => 740,
        18 => 741,
        19 => 742,
        20 => 743,
        21 => 744,
        22 => 745,
        23 => 746,
        24 => 747,
        25 => 748,
        26 => 749,
        27 => 750,
        28 => 751,
        29 => 752,
        30 => 753,
        31 => 754,
        32 => 755,
        33 => 756,
        34 => 757,
        35 => 758,
        36 => 759,
        37 => 760,
        38 => 761,
        39 => 762,
        40 => 763,
        41 => 764,
        42 => 765,
        43 => 766,
        44 => 767,
        45 => 768,
        46 => 769,
        47 => 770,
        48 => 771,
        49 => 772,
        50 => 773,
        51 => 774,
        52 => 775,
        53 => 776,
        54 => 777,
        55 => 778,
        56 => 779,
        57 => 780,
        58 => 781,
        59 => 782,
        60 => 415,
        61 => 783,
        62 => 784,
        63 => 785,
        64 => 786,
        65 => 787,
        66 => 788,
        67 => 789,
        68 => 790,
        69 => 791,
        70 => 792,
        71 => 793,
        72 => 794,
        73 => 795,
        74 => 418,
        75 => 796,
        76 => 797,
        77 => 418,
        78 => 418,
        79 => 798,
        80 => 799,
        81 => 800,
        82 => 414,
        83 => 801,
        84 => 802,
        85 => 803
    ];
    return $currentToConvertedCategories[$category];
}

function getCategories($productId)
{
    global $sourcePDO;
    $categoriesQuery = $sourcePDO->prepare("SELECT * FROM product_category WHERE product_id = $productId");
    $categoriesQuery->execute();

    return $categoriesQuery->fetchAll();
}

function addCategories($product, $categories)
{
    global $targetPDO;
    foreach ($categories as $category) {
        $sql = "INSERT INTO oc_product_to_category SET product_id = $product[product_id], category_id = $category";

        $query = $targetPDO->prepare($sql);
        $query->execute();
    }
}

function addProduct($product)
{
    global $targetPDO;
    echo PHP_EOL . 'Добавление товара с ID #' . $product['id'];

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
			is_parced = '1',
			parce_id = $product[id],
		    image = '$product[image]',
		    price = '$product[price]'";
    $addedProductQuery = $targetPDO->prepare($sql);
    $addedProductQuery->execute();
    return $targetPDO->lastInsertId();
}

function addProductDescription($product)
{
    global $targetPDO;
    $productMetaName = "$product[title] купить в Челябинске - ТЦ Орбита";
    $productMetaDescription = "Торговый Центр Орбита город Челябинск. Свердловский тракт 8. Онлайн каталог. Тысячи товаров, сотни продавцов. Доставка по Челябинску.";

    $sql = "INSERT INTO oc_product_description SET 
                                       tag = '',
                                       meta_keyword = '',
                                       product_id = :product_id,
                                       language_id = :language_id,
                                       name = :name,
                                       description = '',
                                       meta_title = :metaName,
                                       meta_description = :metaDescription";

    $query = $targetPDO->prepare($sql);

    $query->bindParam(':product_id', $product['product_id']);
    $query->bindValue(':language_id', LANGUAGE_ID);
    $query->bindParam(':name', $product['title']);
    $query->bindParam(':metaName', $productMetaName);
    $query->bindParam(':metaDescription', $productMetaDescription);

    $query->execute();
}

function addProductToStore($product)
{
    global $targetPDO;
    $sql = "INSERT INTO oc_product_to_store SET product_id = $product[product_id], store_id = 0";

    $query = $targetPDO->prepare($sql);
    $query->execute();
}

function addProductRelative($product)
{
    global $targetPDO;
    $sql = "INSERT INTO oc_product_to_renter SET product_id = $product[product_id], renter_id = " . RENTER;

    $query = $targetPDO->prepare($sql);
    $query->execute();
}

function addProductImage($product)
{
    global $targetPDO;
    $sql = "INSERT INTO oc_product_image SET
                                 product_id = $product[product_id],
                                 image = '$product[image]',
                                 sort_order = 0";

    $query = $targetPDO->prepare($sql);
    $query->execute();
}

sendProducts();