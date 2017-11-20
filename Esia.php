<?php

namespace heggi\yii2esia;

use yii\authclient\OAuth2;
use yii\web\HttpException;

class Esia extends OAuth2 {
    public $id = 'esia';
    public $name = 'ESIA Auth';
    public $title = 'ESIA Gosusligi.ru';
    public $authUrl = 'https://esia-portal1.test.gosuslugi.ru/aas/oauth2/ac';
    public $tokenUrl = 'https://esia-portal1.test.gosuslugi.ru/aas/oauth2/te';
    public $apiBaseUrl = 'https://esia-portal1.test.gosuslugi.ru/rs/prns';

    public $certPath;
    public $privateKeyPath;
    public $privateKeyPassword;

    
}
