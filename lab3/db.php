<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$servername = "localhost";
$username = "root";
$password = "";
$dbname = "php1";

try {
  $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
  // set the PDO error mode to exception
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT * FROM users";
  $result = $conn->query($sql);
  // C1
//   $users = $result->fetchALL();

//   foreach ($users as $key => $value) {
    
//   echo $value['username'] ."<br>";
//   echo $value['name']."<br>";

//   }
//   C2

$users = $result->fetchALL(PDO::FETCH_OBJ);
  foreach ($users as $value) {
    
  echo $value->username."<br>";
  echo $value->name."<br>";
  echo $value->created."<br>";
  echo $value->status."<br>";

  }

$sql_update = "UPDATE users set name ='NTDA' where username='user'";
$result = $conn->exec($sql_update);

#prepqre
//b1 statement



// $sql_login = "SELECT * FROM users where username = ? and password = ?";
// $stmt = $conn->prepare($sql_login);
// $kq = $stmt->execute(['admin','123456']);
$sql_login = "SELECT * FROM users where username = :username and password = :password";
$stmt = $conn->prepare($sql_login);
$kq = $stmt->execute(
  array(
    'username' => 'admin',
    'password' => '123456'
  )
);

$user = $stmt->fetch();

if ($user!=NULL) {  
  echo "Dang nhap thanh cong <br>";
} else {
  echo "Dang nhap that bai <br>";
}


  echo "Connected successfully";
} catch(PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
}
?>
