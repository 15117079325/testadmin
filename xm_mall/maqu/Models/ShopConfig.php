<?php

namespace maqu\Models;

use  Illuminate\Database\Eloquent\Model  as Eloquent;

class ShopConfig extends Eloquent
{
	protected $table = 'shop_config';

	protected $primaryKey  ='id';

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'int';

}