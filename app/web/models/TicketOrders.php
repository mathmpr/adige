<?php

namespace app\web\models;

use Adige\core\database\ActiveRecord;
use Adige\core\database\RelationDefinition;

class TicketOrders extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'ticket_orders';
    }

    public function transactions(): RelationDefinition
    {
        return $this->hasManyRelation(
            TicketOrderTransactions::class,
            'order_id',
            'id'
        );
    }
}