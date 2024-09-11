<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationalUser extends Model
{
    use HasFactory;

    protected $table = 'organizational_user';

    protected $fillable = ['user_id', 'organizational_id' ];

    // Define relationships (if necessary)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organizationUsers()
    {
        return $this->belongsTo(User::class, 'id' );
    }
}
