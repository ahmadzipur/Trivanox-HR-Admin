<?php
session_start();
setcookie("remember", "", time() - 3600, "/", "", true, true);
unset($_SESSION['user_id']);
session_destroy();
header("Location: login");
exit;
