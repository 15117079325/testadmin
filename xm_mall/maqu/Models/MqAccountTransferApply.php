<?php

namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class MqAccountTransferApply extends Eloquent
{
	protected $table = 'mq_account_transfer_apply';

	protected $primaryKey  ='id';

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'int';

}