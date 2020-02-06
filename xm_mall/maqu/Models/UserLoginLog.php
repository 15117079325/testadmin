<?php

namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class UserLoginLog extends Eloquent
{
	protected $table = 'user_login_log';

	protected $primaryKey  ='log_id';

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'int';

}