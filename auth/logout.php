<?php
require_once '../includes/auth.php';

doLogout();

header('Location: ../auth/login.php');
exit();     