<?php

namespace app\web\models;

use Adige\core\database\ActiveRecord;

class TicketOrderTransactions extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'ticket_order_transactions';
    }
}