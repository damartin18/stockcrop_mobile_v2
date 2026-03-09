<?php
header('Content-Type: application/json');

$response = [
    "status" => "success",
    "message" => "StockCrop API is working",
    "time" => date("Y-m-d H:i:s")
];

echo json_encode($response);
?>




