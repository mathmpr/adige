<?php


namespace app\web\models;

use Adige\core\database\ActiveRecord;
use Adige\core\database\RelationDefinition;

class Tickets extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'tickets';
    }

    public function orders(): RelationDefinition
    {
        return $this->hasManyRelation(
            TicketOrders::class,
            'ticket_id',
            'id'
        );
    }
}