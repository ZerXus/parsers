<?php
function parseParentCategories($crawler)
{
    return $crawler->filter('.section_item_inner')->each(function ($node) {
        $title = $node->filter('li.name a')->text();
        $url = $node->filter('li.name a')->link()->getUri();
        if ($title === 'Тренажеры и фитнес' || $title === 'Зимний спорт') {
            return [
                'title' => $title,
                'url' => $url,
                'description' => ''
            ];
        }
        return false;
    });
}

function saveParentCategories($categories)
{
    global $pdo;
    foreach ($categories as $category) {
        if ($category !== false) {
            if (isParentCategoryExist($category)) {
                continue;
            }

            $stmt = $pdo->prepare("INSERT INTO categories (title, description, url) VALUES (?, ?, ?)");
            $stmt->execute([
                $category['title'],
                $category['description'],
                $category['url']
            ]);
            $id = $pdo->lastInsertId();

            $relations = $pdo->prepare("INSERT INTO categories_relation (parent_id, child_id) VALUES (?, ?)");
            $relations->execute([0, $id]);

            echo 'Спарсил категорию - ' . $category['title'] . PHP_EOL;
        }
    }
}

function getParentCategories()
{
    global $pdo;
    $sql = "SELECT categories.id, categories.description, categories.title, categories.url 
            FROM categories
            INNER JOIN categories_relation ON categories_relation.child_id = categories.id
            WHERE categories_relation.parent_id = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function isParentCategoryExist($category)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT count(*) FROM categories WHERE url = ?");
    $countStmt = $stmt->execute([$category['url']]);

    if ($countStmt) {
        $count = $stmt->fetch(PDO::FETCH_NUM)[0];
        return !($count === 0);
    }
    throw new Exception('Failed check if parent category exists');
}