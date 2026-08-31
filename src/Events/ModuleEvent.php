<?php
declare(strict_types=1);

namespace foun10\DeepL\Events;

use OxidEsales\Eshop\Core\DatabaseProvider;

/**
 * Add table for deepL translation
 */
class ModuleEvent
{
    /**
     * Event called on Module activation
     */
    public static function onActivate()
    {
        $database = DatabaseProvider::getDb();

        $database->execute("
            CREATE TABLE IF NOT EXISTS foun10deepltranslations (
                OXID char(32) not null primary key,
                FOUN10TEXTHASH varchar(64) default '' not null,
                FOUN10FROMLANG varchar(2) default '' not null,
                FOUN10TOLANG varchar(2) default '' not null,
                FOUN10TRANSLATEDTEXT text not null,
                FOUN10OPTIONHASH varchar(64) default '' not null,
                OXTIMESTAMP timestamp default CURRENT_TIMESTAMP not null on update CURRENT_TIMESTAMP,
                UNIQUE INDEX TEXT_LANG_ID (FOUN10TEXTHASH, FOUN10FROMLANG, FOUN10TOLANG, FOUN10OPTIONHASH)
            );
        ");
    }
}
