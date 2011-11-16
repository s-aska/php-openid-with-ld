<?php

ini_set('include_path',
    ini_get('include_path') . ':' .
    dirname(dirname(__FILE__)) . '/vendors/limonade/lib' . ':' .
    dirname(dirname(__FILE__)) . '/vendors/openid'
);

require_once 'limonade.php';
require_once 'Auth/OpenID/Consumer.php';
require_once 'Auth/OpenID/FileStore.php';
require_once 'Auth/OpenID/SReg.php';
require_once 'Auth/OpenID/PAPE.php';

dispatch('/info', function(){
    return phpinfo();
});

dispatch('/', function(){
    return render('index.html.php');
});

dispatch('/try_auth', function(){
    $openid = $_GET['openid_identifier'];
    $consumer = getConsumer();
    $auth_request = $consumer->begin($openid);
    if (!$auth_request) {
        return "auth_request is null.";
    }
    if ($auth_request->shouldSendRedirect()) {
        $redirect_url = $auth_request->redirectURL(getTrustRoot(),
                                                   getReturnTo(), true);
        if (Auth_OpenID::isFailure($redirect_url)) {
            return "Could not redirect to server: " . $redirect_url->message;
        } else {
            header("Location: " . $redirect_url);
        }
    } else {
        $form_id = 'openid_message';
        $form_html = $auth_request->htmlMarkup(getTrustRoot(), getReturnTo(),
                                               false, array('id' => $form_id));
        if (Auth_OpenID::isFailure($form_html)) {
            return "Could not redirect to server: " . $form_html->message;
        } else {
            return $form_html;
        }
    }
});

dispatch('/finish_auth', function(){
    $consumer = getConsumer();
    $return_to = getReturnTo();
    $response = $consumer->complete($return_to);

    if ($response->status == Auth_OpenID_CANCEL) {
        $msg = 'Verification cancelled.';
    } else if ($response->status == Auth_OpenID_FAILURE) {
        $msg = "OpenID authentication failed: " . $response->message;
    } else if ($response->status == Auth_OpenID_SUCCESS) {
        $openid = $response->getDisplayIdentifier();
        $esc_identity = htmlentities($openid);
        $success = sprintf('You have successfully verified ' .
                           '<a href="%s">%s</a> as your identity.',
                           $esc_identity, $esc_identity);

        if ($response->endpoint->canonicalID) {
            $htmlentitiesd_canonicalID = htmlentities($response->endpoint->canonicalID);
            $success .= '  (XRI CanonicalID: '.$htmlentitiesd_canonicalID.') ';
        }

        return $success;
    }
    return $msg;
});

session_start();

run();

function configure() {
    option('root_dir',  dirname(dirname(__FILE__)));
    option('views_dir', dirname(dirname(__FILE__)) . '/views/');
}

function getConsumer() {
    $store_path = '/tmp/_php_consumer_test';
    $store = new Auth_OpenID_FileStore($store_path);
    $consumer = new Auth_OpenID_Consumer($store);
    return $consumer;
}

function getScheme() {
    $scheme = 'http';
    if (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') {
        $scheme .= 's';
    }
    return $scheme;
}

function getReturnTo() {
    return sprintf("%s://%s:%s/finish_auth",
                   getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT']);
}

function getTrustRoot() {
    return sprintf("%s://%s:%s/",
                   getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT']);
}

?>