<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

/**
 * Minimal stand-in for OXID's database adapter, covering only the three calls DeepLStats
 * makes. Hand-written rather than mocked because the adapter interface lives in the shop and
 * is not autoloadable in a unit test.
 */
class FakeDatabase
{
    /** @var StatsWithFakeDb */
    private $recorder;

    public function __construct(StatsWithFakeDb $recorder)
    {
        $this->recorder = $recorder;
    }

    public function getRow(string $query, array $parameters = [])
    {
        $this->recorder->recordQuery($query, $parameters);

        return $this->recorder->row;
    }

    public function getAll(string $query, array $parameters = [])
    {
        $this->recorder->recordQuery($query, $parameters);

        return $this->recorder->rows;
    }

    public function execute(string $query, array $parameters = [])
    {
        $this->recorder->recordQuery($query, $parameters);

        return $this->recorder->affectedRows;
    }
}
