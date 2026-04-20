<?php

namespace Dev\Site\Agents;

class Iblock
{
    public static function clearOldLogs()
    {
        if (\Bitrix\Main\Loader::includeModule('iblock')) {
        
        $iblock = new \CIBlock;
        $section = new \CIBlockSection;
        $element = new \CIBlockElement;
        
        $res = $iblock->GetList([], ["CODE" => "LOG"]); 
        if ($ar_res = $res->Fetch()) {
            $IBLOCK_ID = $ar_res["ID"];
        }else {
            die("Ошибка: Инфоблок с кодом LOG не найден.");
        }
        
        $arOrder = [
            "DATE_ACTIVE_FROM" => "DESC", 
            "ID" => "DESC"         
        ];
        $arFilter = [
            "IBLOCK_ID" => $IBLOCK_ID,
            "ACTIVE" => "Y"
        ];
        $res = $element->GetList($arOrder, $arFilter);
        
        $count = 0;
        while($elem = $res->Fetch()){
            $count++;
            if($count <= 10){
                continue;
            }
            if ($elem["IBLOCK_ID"] == $IBLOCK_ID) {
                $element->Delete($elem["ID"]);
            }
        }
        }
        return '\\' . __CLASS__ . '::' . __FUNCTION__ . '();';
    }

    public static function example()
    {
        global $DB;
        if (\Bitrix\Main\Loader::includeModule('iblock')) {
            $iblockId = \Only\Site\Helpers\IBlock::getIblockID('QUARRIES_SEARCH', 'SYSTEM');
            $format = $DB->DateFormatToPHP(\CLang::GetDateFormat('SHORT'));
            $rsLogs = \CIBlockElement::GetList(['TIMESTAMP_X' => 'ASC'], [
                'IBLOCK_ID' => $iblockId,
                '<TIMESTAMP_X' => date($format, strtotime('-1 months')),
            ], false, false, ['ID', 'IBLOCK_ID']);
            while ($arLog = $rsLogs->Fetch()) {
                \CIBlockElement::Delete($arLog['ID']);
            }
        }
        return '\\' . __CLASS__ . '::' . __FUNCTION__ . '();';
    }
}
