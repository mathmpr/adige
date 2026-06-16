<?php

namespace app\web\models;

use Adige\core\database\ActiveRecord;

class TicketOrders extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'ticket_orders';
    }
}