<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

use foun10\DeepL\Core\DeepLStats;

/**
 * DeepLStats with the database seam replaced by a recording fake.
 *
 * Tests set the rows the fake should return and afterwards inspect which statement ran and
 * with which bound parameters - which is what matters for the LIKE escaping, since the same
 * pattern is reused by the DELETE.
 */
class StatsWithFakeDb extends DeepLStats
{
    /** @var array Row returned by getRow(). */
    public $row = [];

    /** @var array Rows returned by getAll(). */
    public $rows = [];

    /** Value execute() reports as affected rows. */
    public $affectedRows = 0;

    /** @var string[] Every statement that reached the database. */
    public $queries = [];

    /** @var array Bound parameters of the most recent statement. */
    public $lastParameters = [];

    protected function getDb()
    {
        return new FakeDatabase($this);
    }

    protected function getAssocDb()
    {
        return new FakeDatabase($this);
    }

    public function recordQuery(string $query, array $parameters): void
    {
        $this->queries[] = $query;
        $this->lastParameters = $parameters;
    }
}
