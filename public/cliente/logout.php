<?php
session_start();
unset($_SESSION['portal_client_id']);
unset($_SESSION['portal_client_name']);
unset($_SESSION['portal_client_email']);
header('Location: ../login.php');
exit;
