<?php

error_reporting(-1);
ini_set("display_errors", 1);

define('DEBUG', true);

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_- ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

include('mailias.php');
include('config.php');

$alias = generateRandomString(6);
$receive = generateRandomString(10) . "@" . generateRandomString(6) . ".de";
$description = generateRandomString(20);

$delete_input = (isset($_GET['delete'])) ? $_GET['delete'] : FALSE;
$insert = (isset($_GET['insert'])) ? TRUE : FALSE;

$delete = [$delete_input];


$test = new mailias\mailias();
$test->setConfig('$config')

if ($test->checkUser('tester@nkio.de')) {

    if ($delete) {
        if (!$test->delAlias($delete)) {
            
        }
    }

    if ($insert) {
        if (!$test->insertAlias($alias, $receive, $description)) {
            
        }
    }

    if (!$test->readList()) {
        
    }

    if (!$data = $test->getList()) {
        
    }

    $test->disconnect();
}

$notes = $test->getNotification('info', 'user');

foreach ($notes as $note) {
    echo $note['text'] . "\n";
}

echo "<table>\n"
 . "  <tr>\n"
 . "    <td>id</td>\n"
 . "    <td>alias</td>\n"
 . "    <td>user_id</td>\n"
 . "    <td>activ</td>\n"
 . "    <td>description</td>\n"
 . "    <td>receive</td>\n"
 . "    <td>created</td>\n"
 . "   <td>decay</td>\n"
 . "  <tr>\n";

foreach ($data as $row) {
    echo"  <tr>\n";
    foreach ($row as $key => $value) {
        if ($key == 'id') {
            echo "    <td><a href='index.php?delete=" . $value . "'>" . $value . "</a></td>\n";
        } else {
            echo "    <td>" . $value . "</td>\n";
        }
    }
    echo"  </tr>\n";
}

echo "</table>\n";

echo "<a href='index.php?insert=true'>Insert</a>";
