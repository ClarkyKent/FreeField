<?php

require_once("../../includes/lib/global.php");
__require("config");
__require("auth");

$service = "discord";

if (!Auth::isProviderEnabled($service)) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ".Config::getEndpointUri("/auth/login.php"));
    exit;
}

__require("vendor");

$provider = new \Wohali\OAuth2\Client\Provider\Discord([
    "clientId" => Config::get("auth/provider/{$service}/client-id"),
    "clientSecret" => Config::get("auth/provider/{$service}/client-secret"),
    "redirectUri" => Config::getEndpointUri("/auth/oa2/{$service}.php")
]);

if (!isset($_GET["code"])) {
    $authUrl = $provider->getAuthorizationUrl(array(
        "scope" => array("identify")
    ));
    header("HTTP/1.1 307 Temporary Redirect");
    setcookie("oa2-{$service}-state", $provider->getState(), 0, $_SERVER["REQUEST_URI"]);
    header("Location: {$authUrl}");
    exit;
} elseif (empty($_GET["state"]) || !isset($_COOKIE["oa2-{$service}-state"]) || $_GET["state"] !== $_COOKIE["oa2-{$service}-state"]) {
    header("303 See Other");
    setcookie("oa2-{$service}-state", "", time() - 3600, $_SERVER["REQUEST_URI"]);
    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
} else {
    $token = $provider->getAccessToken("authorization_code", array(
        "code" => $_GET["code"]
    ));
    try {
        $user = $provider->getResourceOwner($token);
        Auth::setAuthenticatedSession("{$service}:".$user->getId(), Config::get("auth/session-length"));
        
        header("HTTP/1.1 303 See Other");
        setcookie("oa2-{$service}-state", "", time() - 3600, $_SERVER["REQUEST_URI"]);
        header("Location: ".Config::getEndpointUri("/"));
    } catch (Exception $e) {
        header("303 See Other");
        setcookie("oa2-{$service}-state", "", time() - 3600, $_SERVER["REQUEST_URI"]);
        header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
        exit;
    }
}

?>
