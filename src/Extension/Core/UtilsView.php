<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Core;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Registry;

class UtilsView extends UtilsView_parent
{
    public function parseThroughSmarty($description, $oxid = null, $actView = null, $recompile = false)
    {
        $result = parent::parseThroughSmarty($description, $oxid, $actView, $recompile);

        if (!Registry::getConfig()->isAdmin()) {
            /** @var DeepL $deepL */
            $deepL = Registry::get(DeepL::class);

            if ($result !== null && $deepL->isDeepLTranslateActive() && $deepL->getActiveLanguageOnDemand()) {
                $result = $deepL->translateText(
                    Registry::getLang()->getLanguageAbbr(),
                    $deepL->getActiveLanguageOnDemand(),
                    $result,
                    ['tag_handling' => 'html']
                );
            }
        }

        return $result;
    }
}
