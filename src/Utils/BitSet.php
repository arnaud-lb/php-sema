<?php

namespace PhpSema\Utils;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class BitSet
{
    /** @param int[] $bits */
    private function __construct(
        private array $bits,
    ) {
    }

    public static function empty(): BitSet
    {
        return new BitSet([]);
    }

    public static function unit(int $bit): BitSet
    {
        assert($bit >= 0);

        $bits = [];

        for (
            $size = \intval(($bit + (\PHP_INT_SIZE * 8)) / (\PHP_INT_SIZE * 8));
            $size > 0;
            $size--
        ) {
            $bits[] = 0;
        }

        $index = \intval($bit / (\PHP_INT_SIZE * 8));
        $bit = \intval($bit % (\PHP_INT_SIZE * 8));
        $bits[$index] |= 1 << $bit;

        return new BitSet($bits);
    }


    /** @param int[] $bitList */
    public static function fromArray(array $bitList): BitSet
    {
        if (count($bitList) === 0) {
            return new BitSet([]);
        }

        $bits = [];

        for (
            $size = \intval((\max($bitList) + (\PHP_INT_SIZE * 8)) / (\PHP_INT_SIZE * 8));
            $size > 0;
            $size--
        ) {
            $bits[] = 0;
        }

        foreach ($bitList as $bit) {
            $index = \intval($bit / (\PHP_INT_SIZE * 8));
            $bit = \intval($bit % (\PHP_INT_SIZE * 8));
            $bits[$index] |= 1 << $bit;
        }

        return new BitSet($bits);
    }

    public function set(int $bit): void
    {
        $index = \intval($bit / (\PHP_INT_SIZE * 8));

        for ($size = \count($this->bits); $size <= $index; $size++) {
            $this->bits[] = 0;
        }

        $bit = \intval($bit % (\PHP_INT_SIZE * 8));

        $this->bits[$index] |= 1 << $bit;
    }

    public function isset(int $bit): bool
    {
        $index = \intval($bit / (\PHP_INT_SIZE * 8));
        $bit = \intval($bit % (\PHP_INT_SIZE * 8));

        return (($this->bits[$index] ?? 0) & (1 << $bit)) !== 0;
    }

    public function unset(int $bit): void
    {
        $index = \intval($bit / (\PHP_INT_SIZE * 8));

        for ($size = \count($this->bits); $size <= $index; $size++) {
            $this->bits[] = 0;
        }

        $bit = \intval($bit % (\PHP_INT_SIZE * 8));

        $this->bits[$index] &= ~(1 << $bit);
    }

    public function equals(BitSet $other): bool
    {
        $aBits = $this->bits;
        $bBits = $other->bits;

        for ($i = 0, $l = \max(\count($aBits), \count($bBits)); $i < $l; $i++) {
            if (($aBits[$i] ?? 0) !== ($bBits[$i] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    public function overlaps(BitSet $other): bool
    {
        $aBits = $this->bits;
        $bBits = $other->bits;

        for ($i = 0, $l = \max(\count($aBits), \count($bBits)); $i < $l; $i++) {
            if ((($aBits[$i] ?? 0) | ($bBits[$i] ?? 0)) !== 0) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        if (count($this->bits) === 0) {
            return true;
        }

        foreach ($this->bits as $elem) {
            if ($elem !== 0) {
                return false;
            }
        }

        return true;
    }

    public function count(): int
    {
        $count = 0;

        foreach ($this->bits as $elem) {
            for ($i = 0; $i < PHP_INT_SIZE * 8; $i++) {
                if (($elem & (1 << $i)) !== 0) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** @return array<int> */
    public function toArray(): array
    {
        $array = [];

        foreach ($this->bits as $index => $elem) {
            for ($i = 0; $i < PHP_INT_SIZE * 8; $i++) {
                if (($elem & (1 << $i)) !== 0) {
                    $array[] = $index * (PHP_INT_SIZE * 8) + $i;
                }
            }
        }

        return $array;
    }

    public static function union(BitSet $a, BitSet $b): BitSet
    {
        $bits = [];
        $aBits = $a->bits;
        $bBits = $b->bits;

        for ($i = 0, $l = \max(\count($aBits), \count($bBits)); $i < $l; $i++) {
            $bits[] = ($aBits[$i] ?? 0) | ($bBits[$i] ?? 0);
        }

        return new BitSet($bits);
    }

    public static function intersect(BitSet $a, BitSet $b): BitSet
    {
        $bits = [];
        $aBits = $a->bits;
        $bBits = $b->bits;

        for ($i = 0, $l = \min(\count($aBits), \count($bBits)); $i < $l; $i++) {
            $bits[] = ($aBits[$i] ?? 0) & ($bBits[$i] ?? 0);
        }

        return new BitSet($bits);
    }

    public static function diff(BitSet $a, BitSet $b): BitSet
    {
        $bits = [];
        $aBits = $a->bits;
        $bBits = $b->bits;

        for ($i = 0, $l = \max(\count($aBits), \count($bBits)); $i < $l; $i++) {
            $bits[] = ($aBits[$i] ?? 0) & ~($bBits[$i] ?? 0);
        }

        return new BitSet($bits);
    }
}
