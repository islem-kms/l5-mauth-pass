# Laravel Passport Multi-Auth

Add multi-authentication support to [Laravel Passport](https://laravel.com/docs/5.5/passport)

## Compatibility

| Laravel Passport |
|------------------|
| ^2.0             |
| ^3.0             |

## Installing and configuring

- Install using composer:

```console
composer require islem-kms/l5-mauth-pass
```

- If you are using a Laravel version **less than 5.5** you **need to add** the provider on `config/app.php`:

```php
    'providers' => [
        ...
        IslemKms\PassportMultiauth\Providers\MultiauthServiceProvider::class,
    ],
```

- Migrate database to create `oauth_access_token_providers` table:

```console
php artisan migrate
```

- Add new provider in `config/auth.php` using a model that extends of `Authenticatable` class and use `HasApiTokens` trait.

Ex.:

- Configure your model:

```php
<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class Admin extends Authenticatable
{
   use Notifiable, HasApiTokens;

```

- And your `config/auth.php` providers:

```php
<?php

return [
    ...

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\User::class,
        ],

        // ** New provider**
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Administrator::class,
        ],
    ],
];

```

- Add a new `guard` in `config/auth.php` guards array using driver `passport` and the provider added above:

```php
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'passport',
            'provider' => 'users',
        ],

        // ** New guard **
        'admin' => [
            'driver' => 'passport',
            'provider' => 'admins',
        ],
    ]
```

- Register the middlewares `AddCustomProvider` and `ConfigAccessTokenCustomProvider` on `$middlewareGroups` attribute on `app/Http/Kernel`.

```php

class Kernel extends HttpKernel
{
    ...

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:60,1',
            'bindings',
            \Barryvdh\Cors\HandleCors::class,
            'custom-provider',
        ],

        'custom-provider' => [
            \IslemKms\PassportMultiauth\Http\Middleware\AddCustomProvider::class,
            \IslemKms\PassportMultiauth\Http\Middleware\ConfigAccessTokenCustomProvider::class,
        ]
    ];

    ...
}
```

- Encapsulate the passport routes for access token with the registered middleware in `AuthServiceProvider`:

```php
use Route;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    ...
    
    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Passport::routes();

        // Middleware `api` that contains the `custom-provider` middleware group defined on $middlewareGroups above
        Route::group(['middleware' => 'api'], function () {
            Passport::routes(function ($router) {
                return $router->forAccessTokens();
            });
        });
    }
    ...
}
```

**Optional:** Publish migrations:

```console
php artisan vendor:publish
```

And choose the provider `IslemKms\PassportMultiauth\Providers\MultiauthServiceProvider`

## Usage

- Add the `provider` parameter in your request at `/oauth/token`:

```
POST /oauth/token HTTP/1.1
Host: localhost
Accept: application/json, text/plain, */*
Content-Type: application/json;charset=UTF-8
Cache-Control: no-cache

{
    "username":"user@domain.com",
    "password":"password",
    "grant_type" : "password",
    "client_id": "client-id",
    "client_secret" : "client-secret",
    "provider" : "admins"
}
```

- You can pass your guards on `auth` middleware as you wish. Example:

```php
Route::group(['middleware' => ['api', 'auth:admin']], function () {
    Route::get('/admin', function ($request) {
        // Get the logged admin instance
        return $request->user(); // You can use too `$request->user('admin')` passing the guard.
    });
});

```

The  `api` guard use is equals the example with `admin`.

- You can pass many guards to `auth` middleware. 

**OBS:** To pass many you need pass the `api` guard on end of guards and the guard `api` as parameter on `$request->user()` method. Ex:

```php
// `api` guard on end of guards separated by comma
Route::group(['middleware' => ['api', 'auth:admin,api']], function () {
    Route::get('/admin', function ($request) {
        // Passing `api` guard to `$request->user()` method
        // The instance of user authenticated (Admin or User in this case) will be returned
        return $request->user('api');
    });
});
```

### Refreshing tokens

- Add the `provider` parameter in your request at `/oauth/token`:

```
POST /oauth/token HTTP/1.1
Host: localhost
Accept: application/json, text/plain, */*
Content-Type: application/json;charset=UTF-8
Cache-Control: no-cache

{
    "grant_type" : "refresh_token",
    "client_id": "client-id",
    "client_secret" : "client-secret",
    "refresh_token" : "refresh-token",
    "provider" : "admins"
}
```

### Using scopes

- If you are using more than one guard on same route, use the following package middlewares instead of using the [`scope` and `scopes`](https://laravel.com/docs/5.5/passport#checking-scopes
) middlewares from `Laravel\Passport`.

```php
protected $routeMiddleware = [
    'multiauth.scope' => \IslemKms\PassportMultiauth\Http\Middleware\MultiAuthCheckForAnyScope::class,
    'multiauth.scopes' => \IslemKms\PassportMultiauth\Http\Middleware\MultiAuthCheckScopes::class,
];
```

The middlewares are equals the `Laravel\Passport` middlewares with guard `api` on `request->user()` object.

User Sample:

```php
Route::group([
    'middleware' => ['auth:admin,api', 'multiauth.scope:read-books']
], function ($request) {
    return $request->user('api');
});
```

### Unit tests

If you are using multi-authentication in a request you need to pass just an `Authenticatable` object to `Laravel\Passport\Passport::actingAs()`. E.g.:

- You have a route with multi-auth:

```php
Route::group(['middleware' => 'auth:admin,api'], function () {
    Route::get('/foo', function ($request) {
        return $request->user('api'); // Return user or admin
    });
});
```

- On your test just pass your entity to `Passport::actingAs()`:

```php
use App\User;
use App\Admin;
use Laravel\Passport\Passport;

class MyTest extends TestCase
{
    public function testFooAdmin()
    {
        $admin = factory(Admin::class)->create();

        Passport::actingAs($admin);

        // When you use your endpoint your admin will be returned
        $this->json('GET', '/foo')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => 1,
                    'name' => 'Admin',
                    'email' => 'admin@admin.com'
                ]
            ]);
    }

    public function testFooUser()
    {
        $user = factory(User::class)->create();

        Passport::actingAs($user);

        // When you use your endpoint your user will be returned
        $this->json('GET', '/foo')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => 1,
                    'name' => 'User',
                    'email' => 'user@user.com'
                ]
            ]);
    }
}
```

- If your route has just one guard:

```php
Route::group(['middleware' => 'auth:admin'], function () {
    Route::get('/foo', function ($request) {
        return $request->user(); // Return admin
    });
});
```

- And on your tests just pass your entity, scopes and guard to `Passport::actingAs()`:

```php
use App\User;
use App\Admin;
use Laravel\Passport\Passport;

class MyTest extends TestCase
{
    public function testFooAdmin()
    {
        $admin = factory(Admin::class)->create();

        Passport::actingAs($admin, [], 'admin');

        // When you use your endpoint your admin will be returned
        $this->json('GET', '/foo')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => 1,
                    'name' => 'Admin',
                    'email' => 'admin@admin.com'
                ]
            ]);
    }
}
