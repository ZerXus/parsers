<?php
function saveProduct()
{
    $products = getProduct();

    foreach ($products as $productCategories) {
        foreach ($productCategories as $product) {
            if ($product === null) {
                continue;
            }
            if (isProductExists($product)) {
                $existProductEcho = 'Товар уже добавлен - ' . $product['title'];

                if (!isPriceRelevant($product)) {
                    updateProductPrice($product);
                    $existProductEcho = 'Обновлена цена - ' . $product['title'];
                } else {
                    setParsed($product);
                }

                echo $existProductEcho . PHP_EOL;
                continue;
            }

            downloadPhoto($product);
            saveProductToDB($product);
            saveProductCategory($product);

            echo 'Спарсил товар - ' . $product['title'] . PHP_EOL;
        }
    }
}

function setParsed($product)
{
    global $pdo;

    $setQuery = $pdo->prepare("UPDATE product set is_parsed = 1 WHERE link = ?");
    $setQuery->execute([$product['url']]);
}

function updateProductPrice($product)
{
    global $pdo;

    $updatePriceQuery = $pdo->prepare("UPDATE product SET price = ?, is_parsed = 1 WHERE link = ?");
    $updatePriceQuery->execute([$product['price'], $product['url']]);
}

function isPriceRelevant($product)
{
    $lastPrice = getLastPrice($product);
    $newPrice = $product['price'];

    return $newPrice === $lastPrice;
}

function getLastPrice($product)
{
    global $pdo;

    $priceQuery = $pdo->prepare("SELECT price FROM product WHERE link = ?");
    $priceQuery->execute([$product['url']]);

    return $priceQuery->fetch(PDO::FETCH_NUM)[0];
}

function saveProductCategory($product)
{
    global $pdo;

    $sql = "INSERT INTO product_category (product_id, category_id) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product['id'], $product['category']]);

    $parents = explode(',', $product['root_categories']);
    foreach ($parents as $parentId) {
        if ($parentId == 0) continue;
        $stmt->execute([$product['id'], $parentId]);
    }
}

function saveProductToDB(&$product)
{
    global $pdo;
    $sql = "INSERT INTO product 
                (title, image, link, description, price, is_parsed) 
		VALUES (:title, :image, :link, '', :price, 1)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':title', $product['title']);
    $stmt->bindParam(':link', $product['url']);
    $stmt->bindParam(':image', $product['image_db']);
    $stmt->bindParam(':price', $product['price']);

    $stmt->execute();

    $product['id'] = $pdo->lastInsertId();
}

function downloadPhoto(&$product)
{
    if ($product['image']) {
        $url = $product['image'];
        $noPhoto = 'https://tdsportal.ru/bitrix/templates/aspro_next/images/no_photo_medium.png';
        if ($url !== $noPhoto) {
            $filepathArray = explode('/', $url);
            $fileName = end($filepathArray);

            $dir = 'img/';
            $photoName = getPhotoFileName($fileName, $dir);

            $filepath = $dir . $photoName;
            $fileForDB = 'catalog/tdsportal/' . $photoName;

            if (!file_exists($filepath)) {
                $photo = file_get_contents($url);
                file_put_contents($filepath, $photo);
            }
        } else {
            $fileForDB = '';
        }
        $product['image_db'] = $fileForDB;
    }
}

function getPhotoFileName($file, $directory)
{
    $filepathArray = explode('.', $file);
    $extension = end($filepathArray);

    $name = md5($file);
    return $name . '.' . $extension;
}


function getProduct()
{
    $categories = getAllCategories();

    $uniqueIds = [];
    $product = [];
    foreach ($categories as $category) {
        walkThroughPages($category, function ($category, $counter) use (&$product, &$uniqueIds) {
            $product[] = parseProduct($category, $counter, $uniqueIds);
        });
    }
    return $product;
}

function getAllCategories()
{
    global $pdo;

    $sql = "SELECT C.id, C.title, C.url,   (SELECT GROUP_CONCAT(parent_id separator ',') 
                                            FROM categories_relation CR 
                                            where CR.child_id = C.id) AS 'root_id'
            FROM categories C 
            ORDER BY id desc";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function walkThroughPages($category, $callback)
{
    $pages = getPagesCount($category['url']);
    if ($pages > 0) {
        for ($i = 0; $i < $pages; $i++) {
            echo 'Получил страницу ' . $i . ' от категории - ' . $category['title'] . PHP_EOL;
            $callback($category, $i);
        }
    } else {
        echo 'У категории ' . $category['title'] . ' нет дополнительных страниц' . PHP_EOL;
    }
}

function getPagesCount($url)
{
    $paginator = crawler($url)->filter('div.nums a');

    if ($paginator->count() > 0) {
        return $paginator->last()->text();
    }
    return 0;
}

function parseProduct($category, $counter, &$uniqueIds)
{
    return crawler(getMorePageLink($category['url'], $counter))
        ->filter('.catalog_item.main_item_wrapper')
        ->each(function ($node) use ($category, &$uniqueIds) {
            $id = $node->attr('id');

            if (!isDublicate($id, $uniqueIds)) {
                $price = getProductPrice($node);
                $url = getProductUrl($node);

                echo 'Цена продукта - ' . $price . PHP_EOL;
                return [
                    'id' => $id,
                    'category' => $category['id'],
                    'root_categories' => $category['root_id'],
                    'title' => getProductTitle($node),
                    'image' => getProductImage($url),
                    'url' => $url,
                    'price' => $price
                ];
            }
            return null;
        });
}

function getMorePageLink($url, $count)
{
    $morePagesLink = '?PAGEN_1=' . $count;
    return $url . $morePagesLink;
}

function isDublicate($id, &$uniqueKeys)
{
    $search = in_array($id, $uniqueKeys);
    if ($search === false) {
        $uniqueKeys[] = $id;
        return false;
    }
    return true;
}

function getProductTitle($node)
{
    return $node->filter('.item-title a')->text();
}

function getProductUrl($node)
{
    return $node->filter('.item-title a')->link()->getUri();
}

function getProductPrice($node)
{
    $price = $node->filter('.cost.prices.clearfix .values_wrapper')->last()->text();
    $price = preg_replace('/\D+/', '', $price);
    return intval($price);
}

function getProductImage($url)
{
    $imageArr = crawler($url)->filter('.item_slider img')->each(function ($node) {
        return 'https://tdsportal.ru' . $node->attr('data-src');
    });

    return $imageArr[0] ?? '';
}


function isProductExists($product)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT count(*) FROM product WHERE link = ?");
    $stmt->execute([$product['url']]);

    $count = $stmt->fetch(PDO::FETCH_NUM)[0];
    return !($count === 0);
}