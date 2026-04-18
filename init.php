<?

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
    
if (Loader::includeModule('dev.site')) {
    $manager = new \Dev\Site\Handlers\Iblock;
    $agent = new \Dev\Site\Agents\Iblock;
    
    EventManager::getInstance()->addEventHandler(
        'iblock', 
        'OnAfterIBlockElementAdd', 
        ['\Dev\Site\Handlers\Iblock', 'addLog']
    );

    EventManager::getInstance()->addEventHandler(
        'iblock', 
        'OnAfterIBlockElementUpdate', 
        ['\Dev\Site\Handlers\Iblock', 'addLog']
    );
}

