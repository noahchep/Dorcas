<?php
$apiKey = "AIzaSyA69VeOF6I9hvSBGxeEOTX6_i4WtVsWJjA";
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
$caPath = "C:/xampp/php/extras/ssl/cacert.pem";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CAINFO, $caPath);

$result = curl_exec($ch);

if(curl_errno($ch)) {
    echo "Connection Error: " . curl_error($ch);
} else {
    $response = json_decode($result, true);
    echo "<pre>";
    print_r($response);
    echo "</pre>";
}
curl_close($ch);
?>