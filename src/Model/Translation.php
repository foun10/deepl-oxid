<?php
declare(strict_types=1);

namespace foun10\DeepL\Model;

use OxidEsales\Eshop\Core\Model\BaseModel;

class Translation extends BaseModel
{
    /**
     * Object core table name
     *
     * @var string
     */
    public $_sCoreTable = 'foun10deepltranslations';

    /**
     * Current class name
     *
     * @var string
     */
    protected $_sClassName = self::class;

    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    public function loadByParameter(string $fromLang, string $toLang, string $textHash, string $optionHash): bool
    {
        $query = $this->buildSelectString([
            $this->getViewName() . '.FOUN10FROMLANG' => $fromLang,
            $this->getViewName() . '.FOUN10TOLANG' => $toLang,
            $this->getViewName() . '.FOUN10TEXTHASH' => $textHash,
            $this->getViewName() . '.FOUN10OPTIONHASH' => $optionHash,
        ]);

        $this->_isLoaded = $this->assignRecord($query);

        return $this->_isLoaded;
    }

}
