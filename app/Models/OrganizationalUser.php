<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class OrganizationalUser extends Model
{
    use HasFactory;

    protected $table = 'organizational_user';

    protected $fillable = ['user_id', 'organizational_id', 'customer_id'];

// Define relationships (if necessary)
public function user()
{
    return $this->belongsTo(User::class);
}

// public function organizational()
// {
//     return $this->hasMany(OrganizationalUser::class,  'user_id','customer_id')->with('normalUser');
// }
// public function normalUser()
// {
//     return $this->hasMany(OrganizationalUser::class, 'organizational_id');
// }
public function organizational()
{
    return $this->hasMany(OrganizationalUser::class, 'user_id', 'user_id')->with('normalUser');
}


// Normal users (linked via organizational_id)
public function normalUser()
{
    return $this->hasMany(OrganizationalUser::class, 'organizational_id', 'organizational_id');
}

}
