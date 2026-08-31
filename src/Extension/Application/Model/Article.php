<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Application\Model;

use foun10\DeepL\Core\DeepL;
use foun10\DeepL\Traits\MultilangModel;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;

class Article extends Article_parent
{
    use MultilangModel;

    public function getLongDescription()
    {
        $longDesc = parent::getLongDescription();

        if (!$this->isAdmin()) {
            /** @var DeepL $deepL */
            $deepL = Registry::get(DeepL::class);
            $langOnDemand = $deepL->getActiveLanguageOnDemand();

            if (!empty($langOnDemand)) {
                $rawValue = $longDesc->getRawValue();

                if (!empty($rawValue)) {
                    $translatedField = $deepL->translateText(
                        Registry::getLang()->getLanguageAbbr(),
                        $langOnDemand,
                        $rawValue,
                        [
                            'tag_handling' => 'html',
                        ]
                    );

                    $longDesc = new Field($translatedField, Field::T_RAW);
                }
            }
        }

        return $longDesc;
    }
}