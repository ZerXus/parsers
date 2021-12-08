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
const SOURCE_NAME = 'tdsportal';

function sendProducts()
{
    $count = getProductsCount();
    for ($i = 0; $i < $count; $i += 100) {
        $products = getNewProducts($i);

        foreach ($products as $product) {
            if (!isProductRelevant($product)) {
                switchProductStatus($product, 0);
                echo "Выключаю товар - $product[title]" . PHP_EOL;
                continue;
            }
            if (isProductExists($product)) {
                $echo = "Уже существующий продукт - $product[title]";
                if (!isProductPriceRelevant($product)) {
                    updateProductPrice($product);
                    $echo = "Обновил цену у товара - $product[title]";
                }
                if (isProductDisabled($product)) {
                    switchProductStatus($product, 1);
                    $echo = "Включаю товар - $product[title]";
                }
                echo $echo . PHP_EOL;
                continue;
            }

            $categories = convertCategory($product);
            addProduct($product);

            addCategories($product, $categories);
            addProductDescription($product);
            addProductToStore($product);
            addProductRelative($product);
            addProductImage($product);
        }
    }
}

function isProductRelevant($product)
{
    return (bool)$product['is_parsed'];
}

function switchProductStatus($product, $status)
{
    global $targetPDO;

    $sql = "UPDATE oc_product SET status = $status 
            WHERE source_name = ? AND source_id = ?";
    $disableQuery = $targetPDO->prepare($sql);
    $disableQuery->execute([SOURCE_NAME, $product['id']]);
}

function getProductsCount()
{
    global $sourcePDO;

    $countQuery = $sourcePDO->prepare("SELECT count(*) FROM product");
    $countQuery->execute();

    return $countQuery->fetchColumn();
}

function isProductExists($product)
{
    global $targetPDO;

    $sql = 'SELECT count(*) FROM oc_product WHERE source_name = ? AND source_id = ?';

    $isExistsQuery = $targetPDO->prepare($sql);
    $isExistsQuery->execute([SOURCE_NAME, $product['id']]);

    return (bool)$isExistsQuery->fetchColumn();
}

function isProductDisabled($product)
{
    global $targetPDO;

    $sql = "SELECT status FROM oc_product WHERE source_name = ? AND source_id = ?";
    $checkQuery = $targetPDO->prepare($sql);
    $checkQuery->execute([SOURCE_NAME, $product['id']]);

    return !$checkQuery->fetchColumn();
}

function getNewProducts($counter)
{
    global $sourcePDO;

    $sql = "SELECT id, title, description, price, link, image, is_parsed 
                FROM product 
                WHERE price > 0 AND image != '' 
                LIMIT 100 OFFSET $counter";

    $productsQuery = $sourcePDO->prepare($sql);
    $productsQuery->execute();

    return $productsQuery->fetchAll(PDO::FETCH_ASSOC);
}

function isProductPriceRelevant($product)
{
    $oldPrice = getOldPrice($product);
    $newPrice = $product['price'];

    return $oldPrice == $newPrice;
}

function updateProductPrice($product)
{
    global $targetPDO;

    $updatePriceQuery = $targetPDO->prepare("UPDATE oc_product SET price = :price WHERE source_name = :shop AND source_id = :id");
    $updatePriceQuery->execute([
        'price' => $product['price'],
        'shop' => SOURCE_NAME,
        'id' => $product['id']
    ]);
}

function getOldPrice($product)
{
    global $targetPDO;

    $sql = 'SELECT price FROM oc_product WHERE source_name = ? AND source_id = ?';

    $isExistsQuery = $targetPDO->prepare($sql);
    $isExistsQuery->execute([SOURCE_NAME, $product['id']]);

    return $isExistsQuery->fetchColumn();
}

function convertCategory($product)
{
    $newCategories = getCategories($product);
    $mainCategory = 329;

    $categories = [];
    $categories[] = $mainCategory;

    foreach ($newCategories as $category) {
        $currentCategory = $category['category_id'];
        $categories[] = convertCurrentCategory($currentCategory);
    }
    $categories = array_unique($categories);
    return array_filter($categories);
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

function getCategories($product)
{
    global $sourcePDO;
    $categoriesQuery = $sourcePDO->prepare("SELECT * FROM product_category WHERE product_id = $product[id]");
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

function addProduct(&$product)
{
    global $targetPDO;
    echo PHP_EOL . 'Добавление товара с ID #' . $product['id'];

    $sql = "INSERT INTO oc_product SET
			user_id = " . USER . ",
			model = " . MODEL . ",
			sku = '', upc = '', ean = '', jan = '', isbn = '', mpn = '', location = '',
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
			source_name = '" . SOURCE_NAME . "',
			source_id = ?,
		    image = ?,
		    price = ?";
    $addedProductQuery = $targetPDO->prepare($sql);
    $addedProductQuery->execute([$product['id'], $product['image'], $product['price']]);

    $product['product_id'] = $targetPDO->lastInsertId();
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