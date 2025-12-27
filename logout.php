<?php
/**
 * Logout Handler
 */

define('IN_APP', true);
require __DIR__ . '/auth.php';

logoutUser();

header('Location: index.php?logged_out=1');
exit;

?>

