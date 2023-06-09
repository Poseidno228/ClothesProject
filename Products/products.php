<?php

declare(strict_types=1);
include('../Models/product.php');

function getAllProducts()
{
    $sql = "SELECT * FROM products";
    return getProductsByQuery($sql);
}

function addProduct(Product $product)
{
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=clothes; charset=utf8', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $insertSql = "INSERT INTO PRODUCTS(cost, TypeId, name, image_url, description, Amount)
         VALUES(:cost, :TypeId, :name, :image_url, :description, :amount)";
        $resultInsert = $pdo->prepare($insertSql);
        $resultInsert->bindValue(':cost', $product->getCost());
        $resultInsert->bindValue(':TypeId', $product->getTypeId());
        $resultInsert->bindValue(':name', $product->getName());
        $resultInsert->bindValue(':image_url', $product->getImage());
        $resultInsert->bindValue(':description', $product->getDescription());
        $resultInsert->bindValue(':amount', $product->getAmount());

        $resultInsert->execute();
        return true;
    } catch (PDOException) {
        return false;
    }
}

function getProductById(int $id)
{
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=clothes; charset=utf8', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "SELECT * FROM products WHERE product_id=" . $id;
        $result = $pdo->query($sql);
        if ($result->rowCount() == 1) {
            $row = $result->fetch();
            $product = new Product(
                (int)$row['product_id'],
                $row['name'],
                $row['description'],
                (float)$row['cost'],
                $row['image_url'],
                (int)$row['TypeId'],
                (int) $row['amount']
            );
            return $product;
        }
        return null;
    } catch (PDOException) {
        return null;
    }
}

function updateProduct(int $id, Product $product)
{
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=clothes; charset=utf8', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = 'update products set name=:name, description = :description, cost = :cost, image_url = :img_url, amount = :amount WHERE product_id = :id';
        $result = $pdo->prepare($sql);
        $result->bindValue(':id', $id);
        $result->bindValue(':name', $product->getName());
        $result->bindValue(':description', $product->getDescription());
        $result->bindValue(':cost', $product->getCost());
        $result->bindValue(':img_url', $product->getImage());
        $result->bindValue(':amount', $product->getAmount());
        $result->execute();

        $count = $result->rowCount();
        if ($count > 0) {
            return true;
        }
        return false;
    } catch (PDOException) {
        echo '<p class="error">Connection error</p>';
    }
}

function deleteProduct(int $id)
{
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=clothes; charset=utf8', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        //deleting purchase items first
        $deletePurchases = 'DELETE FROM purchase_items WHERE product_id = :id';
        $resultPurchases = $pdo->prepare($deletePurchases);
        $resultPurchases->bindValue(':id', $id);
        $resultPurchases->execute();

        //then delete a product
        $sql = 'DELETE FROM products WHERE product_id = :id';
        $result = $pdo->prepare($sql);
        $result->bindValue(':id', $id);
        $result->execute();
        if ($result->rowCount() == 1) {
            return true;
        }
        return false;
    } catch (PDOException) {
        return false;
    }
}

function validateProduct(Product $product)
{
    if (strlen($product->getName()) < 4 || strlen($product->getName()) > 30) {
        return "Invalid name";
    }
    if (strlen($product->getDescription()) > 100) {
        return "Invalid description";
    }

    if (strlen($product->getImage()) > 120) {
        return "Invalid imageUrl";
    }

    if ($product->getCost() <= 0 || $product->getCost() > 10000) {
        return "Invalid product cost";
    }

    if ($product->getAmount() < 0) {
        return "Invalid products amount";
    }
    return "valid";
}

function getProductsByQuery(string $query)
{
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=clothes; charset=utf8', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $result = $pdo->query($query);
        $prod_array = array();
        while ($row = $result->fetch()) {
            array_push($prod_array, new Product(
                $row['product_id'],
                $row['name'],
                $row['description'],
                (float)$row['cost'],
                $row['image_url'],
                $row['TypeId'],
                $row['amount']
            ));
        }
        return $prod_array;
    } catch (PDOException) {
        return array();
    }
}

function buyProduct(int $id, int $user_id, int $amount)
{
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=clothes; charset=utf8', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        //check is there is enough products in the stock 
        $product = getProductById($id);
        if ($product == null) {
            return null;
        }

        if ($product->getAmount() < $amount) {
            throw new InvalidArgumentException('There are not enough ' . $product->getName() . ' in the stock');
        }

        //buy product (insert product purchase details in the Purchase_item Table)
        $insertSql = "INSERT INTO purchase_items(user_id, product_id, purchase_date, amount)
    VALUES(:user_id, :product_id, :purchase_date, :amount)";
        $currentDate = date('Y-m-d H:i:s');
        $resultInsert = $pdo->prepare($insertSql);
        $resultInsert->bindValue(':user_id', $user_id);
        $resultInsert->bindValue(':product_id', $id);
        $resultInsert->bindValue(':purchase_date', $currentDate);
        $resultInsert->bindValue(':amount', $amount);
        $resultInsert->execute();

        //update product
        $newAmount = $product->getAmount() - $amount;
        $product->setAmount($newAmount);
        updateProduct($id, $product);
    } catch (PDOException) {
        echo '<p class="error">Connection error</p>';
    }
}

function getBoughtProducts(int $userId)
{
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=clothes; charset=utf8', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $getProductsQuery = 'SELECT * FROM purchase_items LEFT JOIN products on 
    purchase_items.product_id=products.product_id WHERE user_id=' . $userId;

        $result = $pdo->query($getProductsQuery);
        if ($result->rowCount() == 0) {
            return null;
        }

        $result_array = array();
        while ($row = $result->fetch()) {
            $cost = $row['cost'];
            settype($cost, 'float');
            $product = new Product($row['name'], $row['description'], $cost, $row['image_url'], $row['TypeId'], $row['amount']);

            $amount = $row[4];
            $purchase_date = $row['purchase_date'];
            array_push($result_array, array($product, $amount, $purchase_date));
        }
        return $result_array;
    } catch (PDOException) {
        return array();
    }
}
