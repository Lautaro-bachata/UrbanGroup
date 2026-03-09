<?php
session_start();

unset($_SESSION['portal_client']);

header('Location: index.php');
exit;
