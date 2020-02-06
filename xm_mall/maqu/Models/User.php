<?php

namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class User extends Eloquent
{
	protected $table = 'users';

	protected $primaryKey  ='user_id';
	public $timestamps = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'mediumint';

}