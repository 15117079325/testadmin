<?php

namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class Feedback extends Eloquent
{
	protected $table = 'feedbacks';

	protected $primaryKey  ='fb_id';
    public $timestamps = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'int';

}
