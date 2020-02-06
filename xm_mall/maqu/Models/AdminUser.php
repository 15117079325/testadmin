<?php

namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class AdminUser extends Eloquent
{
    protected $table = 'admin_user';

    protected $primaryKey  ='user_id';
    public $timestamps = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'smallint';

}