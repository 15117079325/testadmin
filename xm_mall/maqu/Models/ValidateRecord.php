<?php

namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class ValidateRecord extends Eloquent
{
	protected $table = 'validate_record';
    public $timestamps = false;
//	protected $primaryKey  ='record_key';

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	//protected $keyType = 'int';

}