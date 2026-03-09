<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include_once('db_connect.php');

try {
  $res = $conn->query("SELECT 1 AS ok");
  $row = $res->fetch_assoc();
  echo json_encode(["success" => true, "db" => $row]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => $e->getMessage()]);
}