<?php

namespace IslemKms\PassportMultiauth\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'companies';

    use \Laravel\Passport\HasApiTokens;

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public static function createCompany()
    {
        \DB::table('companies')->insert([
            'name' => 'Islem Khemissi',
            'email' => 'khemissi.islem@gmail.com',
            'password' => \Hash::make('123456'),
        ]);
    }
}
