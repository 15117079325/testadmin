<?php

namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class Suggestions extends Eloquent
{
	protected $table = 'suggestions';

	protected $primaryKey  ='id';
    public $timestamps = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'mediumint';

}