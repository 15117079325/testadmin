<?php

namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class IpSafeCheckLog extends Eloquent
{
	protected $table = 'ip_safecheck_log';

	protected $primaryKey  ='log_id';
    public $timestamps = false;
	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'int';

}