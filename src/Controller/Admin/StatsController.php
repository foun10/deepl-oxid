<?php
declare(strict_types=1);

namespace foun10\DeepL\Controller\Admin;

use foun10\DeepL\Core\DeepL;
use foun10\DeepL\Core\DeepLStats;
use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\Registry;

/**
 * Read-only admin view for the DeepL module: live account usage (character quota) and the local
 * translation cache totals (from foun10deepltranslations). Glossary info lives on its own tab
 * (GlossaryController) since that's growing its own management actions.
 */
class StatsController extends AdminController
{
    protected $_sThisTemplate = 'foun10_deepl_stats.tpl';

    public function render()
    {
        parent::render();

        $stats = $this->getStats();

        $this->_aViewData['deepLTestModeActive'] = $this->getDeepL()->isTestModeActive();

        $usageResult = $stats->fetchUsage();
        $usage = $usageResult['usage'];
        $this->_aViewData['deepLUsage'] = ($usage !== null && $usage->character !== null) ? [
            'count' => $usage->character->count,
            'limit' => $usage->character->limit,
        ] : null;
        $this->_aViewData['deepLUsageError'] = $usageResult['error'];

        $this->_aViewData['deepLCacheTotals'] = $stats->getCacheTotals();
        $this->_aViewData['deepLCacheByLanguage'] = $stats->getCacheByLanguage();

        return $this->_sThisTemplate;
    }

    protected function getDeepL(): DeepL
    {
        return Registry::get(DeepL::class);
    }

    protected function getStats(): DeepLStats
    {
        return Registry::get(DeepLStats::class);
    }
}
