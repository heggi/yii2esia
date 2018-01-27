<?php

namespace heggi\yii2esia;

use yii\authclient\OAuth2;
use yii\base\Exception;
use yii\web\HttpException;

class Esia extends OAuth2 {
    public $id = 'esia';
    public $name = 'ESIA Auth';
    public $title = 'ESIA Gosuslugi.ru';
    public $authUrl = '/aas/oauth2/ac';
    public $tokenUrl = '/aas/oauth2/te';
    public $apiBaseUrl = '/rs/prns';
    public $production = true;

    private $baseUrlProd = 'https://esia.gosuslugi.ru';
    private $baseUrlTest = 'https://esia-portal1.test.gosuslugi.ru';

    public $certPath;
    public $privateKeyPath;
    public $privateKeyPassword;

    public function __construct($config = []) {
        parent::__construct($config);

        if($this->production === false) {
            $this->authUrl = $this->baseUrlTest . $this->authUrl;
            $this->tokenUrl = $this->baseUrlTest . $this->tokenUrl;
            $this->apiBaseUrl = $this->baseUrlTest . $this->apiBaseUrl;
        } else {
            $this->authUrl = $this->baseUrlProd . $this->authUrl;
            $this->tokenUrl = $this->baseUrlProd . $this->tokenUrl;
            $this->apiBaseUrl = $this->baseUrlProd . $this->apiBaseUrl;
        }
    }

    public function initUserAttributes() {
        $token = $this->getAccessToken();
        $chunks = explode('.', $token->getToken());
        $payload = json_decode($this->base64UrlSafeDecode($chunks[1]));
        
        return [
            'oid' => $payload->{'urn:esia:sbj_id'},
        ];
    }

    public function buildAuthUrl(array $params = []) {
        $timestamp = date("Y.m.d H:i:s O");
        $authState = $this->generateAuthState();
        $this->setState('authState', $authState);

        $clientSecret = $this->scope . $timestamp . $this->clientId . $authState;
        $clientSecret = $this->signPKCS7($clientSecret);

        if ($clientSecret === false) {
            throw new Exception('signPKCS7 error');
        }

        $defaultParams = [
            'client_id' => $this->clientId,
            'client_secret' => $clientSecret,
            'response_type' => 'code',
            'redirect_uri' => $this->getReturnUrl(),
            'access_type' => 'online',
            'timestamp' => $timestamp,
            'scope' => $this->scope,
            'state' => $authState,
        ];

        return $this->composeUrl($this->authUrl, array_merge($defaultParams, $params));
    }

    protected function signPKCS7($message) {
        if (! file_exists($this->certPath)) {
            throw new Exception('Could not open Cert file');
        }
        if (! file_exists($this->privateKeyPath)) {
            throw new Exception('Could not open Key file');
        }

        $certContent = file_get_contents($this->certPath);
        $keyContent = file_get_contents($this->privateKeyPath);
        $cert = openssl_x509_read($certContent);
        if ($cert === false) {
            throw new Exception('Can\'t read Cert file');
        }

        $privateKey = openssl_pkey_get_private($keyContent, $this->privateKeyPassword);
        if ($privateKey === false) {
            throw new Exception('Can\'t read Private key file');
        }

        // random unique directories for sign
        $messageFile = $this->getTempFile();
        $signFile = $this->getTempFile();

        file_put_contents($messageFile, $message);

        if (! openssl_pkcs7_sign($messageFile, $signFile, $cert, $privateKey, [])) {
            throw new Exception('Sign fail');
        }

        $signed = file_get_contents($signFile);
        # split by section
        $signed = explode("\n\n", $signed);
        # get third section which contains sign and join into one line
        $sign = str_replace("\n", "", $this->urlSafe($signed[3]));
        unlink($signFile);
        unlink($messageFile);
        return $sign;
    }

    protected function getTempFile($prefix = 'temp') {
        $tmpDir = \Yii::$app->runtimePath . '/tmp';
    
        if ( !is_dir($tmpDir) && (!@mkdir($tmpDir) && !is_dir($tmpDir)) ) {
            throw new Exception('Temp directory does not exists');
        }
    
        return tempnam($tmpDir, $prefix);
    }

    private function urlSafe($string) {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }

    protected function generateAuthState() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    protected function applyClientCredentialsToRequest($request) {
        $timestamp = date("Y.m.d H:i:s O");
        $authState = $this->generateAuthState();
        $this->setState('authState', $authState);

        $clientSecret = $this->scope . $timestamp . $this->clientId . $authState;
        $clientSecret = $this->signPKCS7($clientSecret);

        if ($clientSecret === false) {
            throw new Exception('signPKCS7 error');
        }

        $request->addData([
            'client_id' => $this->clientId,
            'client_secret' => $clientSecret,
            'state' => $authState,
            'scope' => $this->scope,
            'timestamp' => $timestamp,
            'token_type' => 'Bearer',
        ]);
    }

    private function base64UrlSafeDecode($string) {
        $base64 = strtr($string, '-_', '+/');
        return base64_decode($base64);
    }

    public function getPersonInfo($oid) {
        return $this->api($oid, 'GET');
    }

    public function applyAccessTokenToRequest($request, $accessToken) {
        return $request->addHeaders([
            'Authorization' => 'Bearer ' . $accessToken->getToken(),
        ]);
    }
}
