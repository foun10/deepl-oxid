<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Application\Model;

use foun10\DeepL\Traits\MultilangModel;

class Actions extends Actions_parent
{
    use MultilangModel;

    protected function getUntranslatableFields(): array
    {
        return ['oxlink'];
    }
}
