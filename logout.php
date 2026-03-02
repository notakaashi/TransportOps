<?php
/**
 * Logout Handler
 * Destroys session and redirects to login page
 */

require_once 'auth_helper.php';

secureSessionStart();

// Complete session destruction
destroySessionCompletely();

// Redirect to login page
header('Location: login.php');
exit;


