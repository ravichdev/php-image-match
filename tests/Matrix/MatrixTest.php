<?php
namespace ImageMatch\Tests;

use ImageMatch\Matrix;

class MatrixTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @testCase     shape returns the shape of a multidimensional array
     * @dataProvider dataProviderForShape
     * @param        array $array
     * @param        array $expectedShape
     */
    public function testShape(array $array, array $expectedShape)
    {
        // When
        $shape = Matrix::shape($array);
        // Then
        $this->assertEquals($shape, $expectedShape);
    }

    public function dataProviderForShape()
    {
        return [
            [[0, 0, 0, 0, 1], [5]],
            [[
                [0, 0, 0],
                [1, 1, 1]
            ], [2, 3]],
            [[
                [0, 0, 0],
                [1, 1, 1],
                [2, 2, 2],
                [3, 3, 3]
            ], [4, 3]],
            [[
                [[0, 0, 0], [1, 1, 1]],
                [[2, 2, 2], [3, 3, 3]],
                [[4, 4, 4], [5, 5, 5]],
                [[6, 6, 6], [7, 7, 7]],
            ], [4, 2, 3]],
        ];
    }

    /**
     * @testCase     sum returns the sum of elements in a multidimensional array
     * @dataProvider dataProviderForSum
     * @param        array $array
     * @param        mixed $axis
     * @param        array $expectedSum
     */
    public function testSum(array $array, $axis, $expectedSum)
    {
        // When
        $sum = Matrix::sum($array, $axis);

        // Then
        $this->assertEquals($sum, $expectedSum);
    }

    public function dataProviderForSum()
    {
        $threeDArray = [
            [[0, 1, 5], [1, 2, 6]],
            [[2, 5, 3], [3, 8, 4]],
            [[4, 6, 5], [5, 9, 8]],
            [[6, 8, 7], [7, 2, 3]],
        ];
        return [
            [[1, 2, 3], 0, [1, 2, 3]],
            [[
                [1, 2, 3],
                [4, 5, 6]
            ], 0, [5, 7, 9]], // axis is 0
            [[
                [1, 2, 3],
                [4, 5, 6]
            ], 1, [6, 15]], // axis is 1
            [[
                [1, 2, 3],
                [4, 5, 6]
            ], false, 21], // If no axis return sum of all elements,
            [
                $threeDArray,
                0,
                [
                    [12, 20, 20],
                    [16, 21, 21]
                ]
            ],
            [
                $threeDArray,
                1,
                [
                    [1, 3, 11],
                    [5, 13, 7],
                    [9, 15, 13],
                    [13, 10, 10]
                ]
            ],
            [
                $threeDArray,
                false,
                110
            ],
        ];
    }

    /**
     * @testCase     multiply returns the product of each element with provided number in a multidimensional array
     * @dataProvider dataProviderForMultiply
     * @param        array $array
     * @param        int   $number
     * @param        array $expected
     */
    public function testMultiply(array $array, int $number, array $expected)
    {
        // When
        $sum = Matrix::multiply($array, $number);

        // Then
        $this->assertEquals($sum, $expected);
    }

    public function dataProviderForMultiply()
    {
        return [
            [
                [
                    [1, 2],
                    [3, 4]
                ],
                2,
                [
                    [2, 4],
                    [6, 8]
                ]
            ],
            [
                [
                    [1, 2],
                    [3, 4]
                ],
                0,
                [
                    [0, 0],
                    [0, 0]
                ]
            ],
            [
                [
                    [1, 2],
                    [3, 4]
                ],
                -2,
                [
                    [-2, -4],
                    [-6, -8]
                ]
            ]
        ];
    }

    /**
     * @testCase     cumsum returns the cumulative sum of elements in each row in a multidimensional array
     * @dataProvider dataProviderForCumsum
     * @param        array $array
     * @param        array $expected
     */
    public function testCumsum(array $array, $expected)
    {
        // When
        $cumsum = Matrix::cumsum($array);

        // Then
        $this->assertEquals($cumsum, $expected);
    }

    public function dataProviderForCumsum()
    {
        return [
            [
                [
                    [1, 2],
                    [3, 4]
                ],
                [
                    [1, 3],
                    [3, 7]
                ],
            ],
            [
                [
                    [1, 2, -1],
                    [3, 4, -2]
                ],
                [
                    [1, 3, 2],
                    [3, 7, 5]
                ],
            ]
        ];
    }

    /**
     * @testCase     Subtract each element in an array using a target array
     * @dataProvider dataProviderForSubtract
     * @param        array $array
     * @param        array $expected
     */
    public function testSubtract(array $array1, array $array2, array $expected)
    {
        // When
        $subtract = Matrix::subtract($array1, $array2);

        // Then
        $this->assertEquals($subtract, $expected);
    }

    public function dataProviderForSubtract()
    {
        return [
            [
                [
                    [1, 2],
                    [3, 4]
                ],
                [2, 5],
                [
                    [1, 3],
                    [-1, 1]
                ]
            ],
        ];
    }
}
