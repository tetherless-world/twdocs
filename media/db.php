<?php

function connectDB() {
  $db = new mysqli("localhost","twc_media",'x.45J$Spfi/5Ob',"twc_media");
  if($db->connect_error) {
    die('Connection error ('.$mysqli->connect_errno.')');
  }
  return $db;
}

function getSecretForApp($app) {
  $db = connectDB();
  $results = $db->query("SELECT secret FROM apps WHERE name='$app'");
  $data = $results->fetch_array(MYSQLI_ASSOC);
  $secret = $data["secret"];
  $results->free();
  $db->close();
  return $secret;
}

function getNonceForApp($app) {
  $db = connectDB();
  $results = $db->query("SELECT nonce FROM apps WHERE name='$app'");
  $data = $results->fetch_array(MYSQLI_ASSOC);
  $nonce = $data["nonce"];
  $results->free();
  $db->close();
  return $nonce;
}

function setNonceForApp($app, $nonce) {
  $db = connectDB();
  $results = $db->query("UPDATE apps SET nonce = $nonce WHERE name='$app'");
  $db->close();
}
