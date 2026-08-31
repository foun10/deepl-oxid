<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Application\Model;

use foun10\DeepL\Traits\MultilangModel;

class Manufacturer extends Manufacturer_parent
{
    use MultilangModel;

    protected function getUntranslatableFields(): array
    {
        return ['oxtitle'];
    }
}
