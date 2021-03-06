<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'google-util.php';

$client = createGoogleClient();

if (!isset($_GET['code'])) {
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
} else {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    $oauth = new Google_Service_Oauth2($client);
    $_SESSION['email'] = $oauth->userinfo->get()->email;

    $_SESSION['access_token'] = $token;//$client->getAccessToken();
    $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/verktyg/forvaltningsrapporter/';
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}
?>