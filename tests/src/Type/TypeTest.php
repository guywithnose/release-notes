<?php
namespace Guywithnose\ReleaseNotes\Tests\Type;

use Guywithnose\ReleaseNotes\Type\Type;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Guywithnose\ReleaseNotes\Type\Type
 * @covers ::<public>
 */
class TypeTest extends TestCase
{
    public function testGetValues()
    {
        $type = new Type('short', 'c', 'long', 3);
        $this->assertSame('short', $type->getName());
        $this->assertSame('c', $type->getCode());
        $this->assertSame('long', $type->getDescription());
        $this->assertSame(3, $type->getWeight());
    }

    /**
     * @dataProvider sortDataProvider
     */
    public function testSort(Type $a, Type $b, $expectedResult)
    {
        $this->assertSame($expectedResult, Type::cmp($a, $b));
    }

    public function sortDataProvider()
    {
        return [
            'Equals' => [new Type('n', 'c', 'd', 3), new Type('n', 'c', 'd', 3), 0],
            'Lower Weight' => [new Type('n', 'c', 'd', 2), new Type('n', 'c', 'd', 3), -1],
            'Higher Weight' => [new Type('n', 'c', 'd', 3), new Type('n', 'c', 'd', 2), 1],
            'Less than fallback to Name' => [new Type('m', 'c', 'd', 3), new Type('n', 'c', 'd', 3), -1],
            'Greater than fallback to Name' => [new Type('n', 'c', 'd', 3), new Type('m', 'c', 'd', 3), 1],
        ];
    }
}
