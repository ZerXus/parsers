<?php
function saveChildCategories()
{
    $childCategories = getChildCategories();
    foreach ($childCategories as $key => $category) {
        $rootId = -1;
        if ($key === 0) $rootId = 1;
        if ($key === 1) $rootId = 2;
        storeChildCategories($category, [$rootId]);
    }
}

function storeChildCategories($categories, $rootIdList)
{
    foreach ($categories as $category) {
        if (isChildCategoryExist($category)) {
            $categoryID = getExistCategoryID($category);
        } else {
            $categoryID = saveChildCategory($category);
            saveChildRelations($rootIdList, $categoryID);
        }

        $parentIDs = $rootIdList;
        $parentIDs[] = $categoryID;
        storeChildCategories($category['childs'], $parentIDs);
        echo 'Спарсил подкатегорию - ' . $category['title'] . PHP_EOL;
    }
}

function saveChildCategory($category)
{
    global $pdo;
    $categories = $pdo->prepare("INSERT INTO categories (title, url, description) 
                                            VALUES (:title, :url, '')");
    $categories->bindParam(':title', $category['title']);
    $categories->bindParam(':url', $category['url']);

    $categories->execute();
    return $pdo->lastInsertId();
}

function saveChildRelations($rootIdList, $id)
{
    global $pdo;
    $relations = $pdo->prepare("INSERT INTO categories_relation (parent_id, child_id) 
                                            VALUES (?, ?)");
    foreach ($rootIdList as $parentId) {
        $relations->execute([$parentId, $id]);
    }
}


function getChildCategories()
{
    $parentCategories = getParentCategories();

    $childCategories = [];
    foreach ($parentCategories as $parentCategory) {
        $childCategories[] = parseCategories($parentCategory['url']);
    }
    return $childCategories;
}

function parseCategories($url)
{
    return getCategory($url, function ($node) {
        $title = getCategoryTitle($node);
        $url = getCategoryUrl($node);

        if ($title && $url) {
            $childs = parseCategories($url);
            return [
                'title' => $title,
                'url' => $url,
                'childs' => $childs
            ];
        }
        return false;
    });
}

function getCategory($url, $callback)
{
    return crawler($url)
        ->filter('.section_block .item')
        ->each(function ($node) use ($callback) {
            return $callback($node);
        });
}

function getCategoryTitle($node)
{
    return $node->filter('div.name a')->text();
}

function getCategoryUrl($node)
{
    return $node->filter('div.name a')->link()->getUri();
}


function isChildCategoryExist($category)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT count(*) FROM categories WHERE url = ?");
    $stmt->execute([$category['url']]);

    $count = $stmt->fetch(PDO::FETCH_NUM)[0];
    return !($count === 0);
}

function getExistCategoryID($category)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT id FROM categories WHERE title = ? AND url = ?");
    $stmt->execute([
        $category['title'],
        $category['url']
    ]);
    return $stmt->fetch(PDO::FETCH_NUM)[0];
}