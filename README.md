ESIA Authclient
===============
Расширение для Yii2 для авторизации через портал госуслуг.
Реализована только авторизация и получение базовых данных (personInfo)

Установка
---------

Запустить
```
php composer.phar require --prefer-dist heggi/yii2-esia "*"
```

Или добавить

```
"heggi/yii2-esia": "*"
```

в секцию require вашего файла `composer.json`.

Настройка
---------

В файле конфигурации (frontend/config/main.php или config/web.php) добавить
```php
return [
    'components' => [
        ...
        'authClientCollection' => [
            'class' => 'yii\authclient\Collection',
            'clients' => [
                ...
                'esia' => [
                    'class' => 'heggi\yii2esia\Esia',
                    'clientId' => 'xxx',
                    'certPath' => __DIR__ . '/xxx.pem',
                    'privateKeyPath' => __DIR__ . '/xxx.key',
                    'privateKeyPassword' => 'xxx',
                    'scope' => 'fullname',
                    'production' => false,
                ],
                ...
            ],
        ],
        ...
    ]
]
        
```
Указать верные clientId, certPath, privateKeyPath, privateKeyPassword.

`'production' => false` для подключения к тестовой среде ESIA.


В контроллере SiteController.php
```php
public function actions() {
    return [
        ...
        'auth' => [
            'class' => 'yii\authclient\AuthAction',
            'successCallback' => [$this, 'ssoCallback'],
        ],
        ...
    ];
}

public function ssoCallback($client) {
    $attributes = $client->getUserAttributes();
    $oid = $attributes['oid'];

    $user = User::findByOid($oid);
    if($user) {
        return Yii::$app->user->login($user);
    }

    $personInfo = $client->getPersonInfo($oid);
    $user = new User();
    $user->oid = $oid;
    $user->first_name = $personInfo['firstName'];
    $user->last_name = $personInfo['lastName'];
    $user->middle_name = $personInfo['middleName'];
    if(!$user->save()) {
        throw new yii\web\ServerErrorHttpException('Внутренняя ошибка сервера');
    }

    Yii::$app->user->login($user);
}
```

В файл views/layouts/main.php добавить ссылку для авторизации
```php
<?= Html::a('Войти через госуслуги', ['site/auth', 'authclient' => 'esia']) ?>
```
или использовать виджет 
```php
<?= yii\authclient\widgets\AuthChoice::widget([
    'baseAuthUrl' => ['site/auth'],
    'popupMode' => false,
]) ?>
```

Полезные ссылки
---------------

[Сайт госуслуги](https://gosuslugi.ru)

[Информация про подключение](https://partners.gosuslugi.ru/catalog/esia)