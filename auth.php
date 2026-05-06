<?php 

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_login():void {
    if(empty($_SESSION["user_id"])){
        header("Location: /Valistoque/login.php");
        exit;
    }
}