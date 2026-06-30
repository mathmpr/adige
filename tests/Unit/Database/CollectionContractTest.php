<?php

namespace Tests\Unit\Database;

use Adige\core\collection\Collection;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\models\FakeModel;

class CollectionContractTest extends TestCase
{
    public function testToArrayDelegatesToModelToArrayAndKeepsRawItemsUntouched(): void
    {
        $rawObject = new \stdClass();
        $rawObject->name = 'raw';

        $collection = Collection::factory([
            new FakeModel(['id' => 7, 'name' => 'Ada']),
            ['plain' => true],
            $rawObject,
            42,
        ]);

        $array = $collection->toArray();

        self::assertSame(['id' => 7, 'name' => 'Ada'], $array[0]);
        self::assertSame(['plain' => true], $array[1]);
        self::assertSame($rawObject, $array[2]);
        self::assertSame(42, $array[3]);
    }

    public function testMagicGetDoesNotProxyToFirstOrmModel(): void
    {
        $collection = Collection::factory([
            new FakeModel(['id' => 7, 'name' => 'Ada']),
        ]);

        self::assertNull($collection->name);
    }

    public function testNestedForeachKeepsIndependentIterationState(): void
    {
        $collection = Collection::factory(['a', 'b', 'c']);
        $pairs = [];

        foreach ($collection as $outer) {
            foreach ($collection as $inner) {
                $pairs[] = $outer . $inner;
            }
        }

        self::assertSame([
            'aa', 'ab', 'ac',
            'ba', 'bb', 'bc',
            'ca', 'cb', 'cc',
        ], $pairs);
    }

    public function testBeginAndEndWorkWhenCollectionStartsAtZeroIndex(): void
    {
        $collection = Collection::factory(['first', 'second']);

        self::assertSame('first', $collection->begin());
        self::assertSame('second', $collection->end());
    }
}
