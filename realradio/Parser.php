<?php
use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/../vendor/autoload.php';
require 'Pdo.php';
set_time_limit(24000);

$pdo = connectToDatabase(DB_REALRADIO_DATABASE, DB_USERNAME, DB_PASSWORD);

$fromUrl = 'https://www.realradio.su/catalog/';
$html = getHtml($fromUrl);
$crawler = new Crawler($html);

$siteParentCategories = $crawler->filter('.root_category_item')->each(function (Crawler $node) {
    return [
        'title' => $node->filter('a span')->last()->text(),
        'href' => $node->filter('a')->first()->attr('href'),
        'description' => $node->filter('span')->last()->text()
    ];
});

saveParentCategories($siteParentCategories, $pdo);
saveChildCategories($pdo);

saveProduct($pdo);

function getHtml($url)
{
    $context = null;
    if (gettype($url) === "array") {
        $link = $url['url'];
        $context = stream_context_create($url['context']);
    } else {
        $link = $url;
    }
    $file = __DIR__ . '/cache/' . md5($link);
    if (file_exists($file)) {
        return unserialize(file_get_contents($file));
    } else {
        $html = file_get_contents($link, false, $context);
        file_put_contents($file, serialize($html));
        return $html;
    }
}//done

function crawler($url)
{
    return new Crawler(getHtml($url));
}//done

function saveParentCategories($categories, $pdo)
{
    if ($categories[0]) {
        foreach ($categories as $category) {
            $title = $category['title'];
            $rootId = 0;
            $url = normalizeUrl($category['href']);
            $description = $category['description'];

            $sql = "INSERT INTO categories (title, description, url, root_id) 
                                VALUES (:title, :description, :url, :root_id)";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':root_id', $rootId);
            $stmt->bindParam(':url', $url);
            $stmt->execute();

            echo 'Спарсил категорию - ' . $title . PHP_EOL;
        }
    }
}//done

function saveChildCategories($pdo)
{
    $getChildCategories = getChildCategories($pdo);

    foreach ($getChildCategories as $category) {
        foreach ($category as $item) {
            $title = $item['title'];
            $url = normalizeUrl($item['url']);
            $rootId = $item['root_id'];
            $description = $item['description'];

            $stmt = $pdo->prepare("INSERT INTO categories (title, url, description, root_id) 
                                            VALUES (:title, :url, :description, :root_id)");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':url', $url);
            $stmt->bindParam(':root_id', $rootId);

            $stmt->execute();

            echo 'Спарсил подкатегорию - ' . $item['title'] . PHP_EOL;
        }
    }
}//done

function getChildCategories($pdo)
{
    $getParentCategories = getParentCategories($pdo);

    $childCategories = [];

    if ($getParentCategories[0]) {
        foreach ($getParentCategories as $category) {
            $childCategories[] = crawler($category['url'])
                ->filter('.root_category_item')
                ->each(function (Crawler $node) use ($category) {

                    $getTitle = $node->filter('a span')->last()->text();
                    $getHref = $node->filter('a')->first()->attr('href');
                    $description = $node->filter('span')->last()->text();

                    if ($getTitle && $getHref) {
                        return [
                            'root_id' => $category['id'],
                            'title' => $getTitle,
                            'url' => $getHref,
                            'description' => $description
                        ];
                    }
                });
        }
    }

    return $childCategories;
}//done

function getParentCategories($pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE root_id = 0");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}//done

function getProductsPage($url)
{
    $context = [
        'http' => [
            'header' => 'Cookie: COUNTS: 1000\r\n'
        ]
    ];
    return [
        'url' => $url,
        'context' => $context
    ];
}//done

function downloadPhoto($url)
{
    if ($url) {
        $url = normalizeUrl($url);

        $photo = file_get_contents($url);
        $fileName = explode('/', $url);
        $fileName = end($fileName);
        $dir = '/var/www/u681963/data/www/dev.orbita74.ru/public/image/catalog/realradio/';
        $photoName = getRandomName($fileName, $dir);

        $file = 'img/' . $photoName;
        $fileForDB = 'catalog/realradio/' . $photoName;
        file_put_contents($file, $photo);

        return $fileForDB;
    }
}//done

function getRandomName($file, $directory)
{
    do {
        $extension = explode('.', $file);
        $extension = end($extension);
        $name = md5(microtime() . rand(0, 9999));
        $fileName = $name . '.' . $extension;
        $fileLocation = $directory . $fileName;
    } while (file_exists($fileLocation));

    return $fileName;
}//done

function saveProduct($pdo)
{
    $productsCategory = getProduct($pdo);

    foreach ($productsCategory as $category) {
        foreach ($category as $product) {
            $product['image_db'] = downloadPhoto($product['product_image']);
            saveProductToDB($product, $pdo);
            $product['id'] = $pdo->lastInsertId();
            saveProductCategory($product, $pdo);

            echo 'Спарсил товар - ' . $product['product_title'] . PHP_EOL;
        }
    }
}//done

function getProduct($pdo)
{
    $categories = getAllCategories($pdo);

    $product = [];
    foreach ($categories as $category) {
        if ($category['id'] === 9 || $category['id'] === 17) continue;

        $product[] = crawler(getProductsPage($category['url']))
            ->filter('.category_item')
            ->each(function (Crawler $node, $i) use ($category) {
                $getTitle = preg_replace('/\*/', '', $node->filter('div a')->attr('title'));
                $getHref = $node->filter('div a')->attr('href');
                $price = preg_replace('# руб.#', '', ($node->filter('span.price')->text()));
                $getPrice = intval(preg_replace('# #', '', $price));

                $getMore = crawler(normalizeUrl($getHref))->each(function (Crawler $node) use ($getTitle) {
                    $descriptionArr = $node->filter('#tab1')->each(function (Crawler $node) {
                        return $node->text();
                    });

                    $description = $descriptionArr[0] ? $descriptionArr[0] : '';

                    $imageArr = $node->filter('.item_images  a  img')->each(function (Crawler $node) {
                        return $node->attr('src');
                    });

                    $image = $imageArr[0] ? preg_replace('#\&w=.{3}#s', '', $imageArr[0]) : '';

                    return [
                        'description' => $description,
                        'image' => $image
                    ];
                });


                $getDescription = $getMore[0]['description'] ? $getMore[0]['description'] : '';
                $getImage = $getMore[0]['image'] ? $getMore[0]['image'] : '';

                return [
                    'category' => $category['id'],
                    'root_category' => $category['root_id'],
                    'product_title' => $getTitle,
                    'product_href' => $getHref,
                    'product_price' => $getPrice,
                    'product_description' => $getDescription,
                    'product_image' => $getImage
                ];

            });
    }

    return $product;

}//done

function getAllCategories($pdo)
{
    $sql = "SELECT * FROM categories";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}//done

function saveProductCategory($product, $pdo)
{
    $sql = "INSERT INTO product_category (product_id, category_id) VALUES (:product_id, :category_id)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':product_id', $product['id']);
    $stmt->bindParam(':category_id', $product['category']);
    $stmt->execute();

    if ($product['root_category']) {
        $sql = "INSERT INTO product_category (product_id, category_id) VALUES (:product_id, :category_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':product_id', $product['id']);
        $stmt->bindParam(':category_id', $product['root_category']);
        $stmt->execute();
    }
}

function saveProductToDB($product, $pdo)
{
    $sql = "INSERT INTO product 
        (title, image, link, description, price, is_parsed) 
		VALUES (:title, :image, :link, :description, :price, 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':title', $product['product_title']);
    $stmt->bindParam(':link', $product['product_href']);
    $stmt->bindParam(':image', $product['image_db']);
    $stmt->bindParam(':description', $product['product_description']);
    $stmt->bindParam(':price', $product['product_price']);
    $stmt->execute();
}

function normalizeUrl($url)
{
    return 'https://www.realradio.su' . $url;
}