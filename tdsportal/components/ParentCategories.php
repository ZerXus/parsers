<?php
function saveParentCategories($categories)
{
    global $pdo;
    foreach ($categories as $category) {
        if ($category !== false) {
            $title = $category['title'];
            $url = $category['url'];
            $description = $category['description'];
            $rootId = 0;

            $sql = "INSERT INTO categories (title, description, url) 
                                VALUES (:title, :description, :url)";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':url', $url);
            $stmt->execute();
            $id = $pdo->lastInsertId();
            $relations = $pdo->prepare("INSERT INTO categories_relation (parent_id, child_id) 
                                VALUES (?, ?)");
            $relations->execute([0, $id]);

            echo 'Спарсил категорию - ' . $title . PHP_EOL;
        }
    }
}

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