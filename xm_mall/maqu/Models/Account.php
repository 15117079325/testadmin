<?php
namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class Account extends Eloquent  {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'mq_account';
	protected $primaryKey  ='account_id';
	protected $keyType = 'varchar';

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */

}
