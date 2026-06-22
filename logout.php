<?php
require_once 'config.php';
session_destroy();
flash('Logged out successfully', 'info');
redirect('index.php');
?>