<?php
namespace Dev\Site\Handlers;
\Bitrix\Main\Loader::includeModule('iblock');

class Iblock
{
    public static function addLog(&$arFields)
    {
        $iblock = new \CIBlock;
        $section = new \CIBlockSection;
        $element = new \CIBlockElement;
        
        $ID_section = [];
        
        $res = $iblock->GetList([], ["CODE" => "LOG"]); 
        $ar_res = $res->Fetch();
        $IBLOCK_ID = $ar_res["ID"]; 
        
        if($arFields["IBLOCK_ID"] == $IBLOCK_ID){
            return;
        }
        else{
                $res = $iblock->GetList([], ["ID" => $arFields["IBLOCK_ID"]]);
                $ar_res = $res->Fetch();
                $IBLOCK_NAME = $ar_res["NAME"];
                
                $res = $section->GetList([], ["NAME" => $IBLOCK_NAME, "IBLOCK_ID" => $IBLOCK_ID]);
                if ($ar_res = $res->Fetch()) {
                    $ID_section[] = $ar_res["ID"];
                } else {
                    $arSectionFields = [
                        "ACTIVE" => 'Y',
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "NAME" => $IBLOCK_NAME,
                    ];
                    $ID_section[] = $section->Add($arSectionFields);
                }
                
                $sectionNames = [];
                $list = $section->GetNavChain(false, $arFields['IBLOCK_SECTION'][0], array(), true);
                foreach ($list as $arSectionPath){
                    $res = $section->GetList([], ["NAME" => $arSectionPath["NAME"], "IBLOCK_ID" => $IBLOCK_ID]);
                    if ($ar_res = $res->Fetch()) {
                        $ID_section[] = $ar_res["ID"];
                        $sectionNames[] = $arSectionPath['NAME'];
                        continue;
                    }
                    $arSectionFields = [
                        "ACTIVE" => 'Y',
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "IBLOCK_SECTION_ID" => array_pop($ID_section),
                        "NAME" => $arSectionPath['NAME'],
                    ];
                    $ID_section[] = $section->Add($arSectionFields);
                    
                    $sectionNames[] = $arSectionPath['NAME'];
                }
                
                if (count($sectionNames) == 1){
                    $PREVIEW_TEXT = $IBLOCK_NAME."->".$sectionNames[0]."->".$arFields['NAME'];
                }
                else{
                    $PREVIEW_TEXT = $IBLOCK_NAME."->".implode("->", $sectionNames)."->".$arFields['NAME'];
                }
                
                $res = $element->GetList([], ["NAME" => $arFields['ID']." ".$arFields['NAME'], "IBLOCK_ID" => $IBLOCK_ID]);
                if($ar_res = $res->GetNext()){
                    $arLoadProductArray = Array(
                    	"NAME"           => $arFields['ID']." ".$arFields['NAME'],
                    	"ACTIVE"         => "Y",       
                    	"ACTIVE_FROM"    => ConvertTimeStamp(time(), "FULL"),
                    	"PREVIEW_TEXT"   => $PREVIEW_TEXT,
                    );
                    $res = $element->Update($ar_res['ID'], $arLoadProductArray);
                }
                else{
                    $arElementFields = [
                        "IBLOCK_SECTION_ID" => array_pop($ID_section),
                        "IBLOCK_ID"      => $IBLOCK_ID,
                        "NAME"           => $arFields['ID']." ".$arFields['NAME'],
                        "ACTIVE"         => "Y",
                        "ACTIVE_FROM"    => ConvertTimeStamp(time(), "FULL"),
                        "PREVIEW_TEXT"   => $PREVIEW_TEXT,
                    ];
                    $ID_element = $element->Add($arElementFields);
                }
            }
    }

    function OnBeforeIBlockElementAddHandler(&$arFields)
    {
        $iQuality = 95;
        $iWidth = 1000;
        $iHeight = 1000;
        /*
         * Получаем пользовательские свойства
         */
        $dbIblockProps = \Bitrix\Iblock\PropertyTable::getList(array(
            'select' => array('*'),
            'filter' => array('IBLOCK_ID' => $arFields['IBLOCK_ID'])
        ));
        /*
         * Выбираем только свойства типа ФАЙЛ (F)
         */
        $arUserFields = [];
        while ($arIblockProps = $dbIblockProps->Fetch()) {
            if ($arIblockProps['PROPERTY_TYPE'] == 'F') {
                $arUserFields[] = $arIblockProps['ID'];
            }
        }
        /*
         * Перебираем и масштабируем изображения
         */
        foreach ($arUserFields as $iFieldId) {
            foreach ($arFields['PROPERTY_VALUES'][$iFieldId] as &$file) {
                if (!empty($file['VALUE']['tmp_name'])) {
                    $sTempName = $file['VALUE']['tmp_name'] . '_temp';
                    $res = \CAllFile::ResizeImageFile(
                        $file['VALUE']['tmp_name'],
                        $sTempName,
                        array("width" => $iWidth, "height" => $iHeight),
                        BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
                        false,
                        $iQuality);
                    if ($res) {
                        rename($sTempName, $file['VALUE']['tmp_name']);
                    }
                }
            }
        }

        if ($arFields['CODE'] == 'brochures') {
            $RU_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_RU');
            $EN_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_EN');
            if ($arFields['IBLOCK_ID'] == $RU_IBLOCK_ID || $arFields['IBLOCK_ID'] == $EN_IBLOCK_ID) {
                \CModule::IncludeModule('iblock');
                $arFiles = [];
                foreach ($arFields['PROPERTY_VALUES'] as $id => &$arValues) {
                    $arProp = \CIBlockProperty::GetByID($id, $arFields['IBLOCK_ID'])->Fetch();
                    if ($arProp['PROPERTY_TYPE'] == 'F' && $arProp['CODE'] == 'FILE') {
                        $key_index = 0;
                        while (isset($arValues['n' . $key_index])) {
                            $arFiles[] = $arValues['n' . $key_index++];
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'L' && $arProp['CODE'] == 'OTHER_LANG' && $arValues[0]['VALUE']) {
                        $arValues[0]['VALUE'] = null;
                        if (!empty($arFiles)) {
                            $OTHER_IBLOCK_ID = $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? $EN_IBLOCK_ID : $RU_IBLOCK_ID;
                            $arOtherElement = \CIBlockElement::GetList([],
                                [
                                    'IBLOCK_ID' => $OTHER_IBLOCK_ID,
                                    'CODE' => $arFields['CODE']
                                ], false, false, ['ID'])
                                ->Fetch();
                            if ($arOtherElement) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arOtherElement['ID'], $OTHER_IBLOCK_ID, $arFiles, 'FILE');
                            }
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'E') {
                        $elementIds = [];
                        foreach ($arValues as &$arValue) {
                            if ($arValue['VALUE']) {
                                $elementIds[] = $arValue['VALUE'];
                                $arValue['VALUE'] = null;
                            }
                        }
                        if (!empty($arFiles && !empty($elementIds))) {
                            $rsElement = \CIBlockElement::GetList([],
                                [
                                    'IBLOCK_ID' => \Only\Site\Helpers\IBlock::getIblockID('PRODUCTS', 'CATALOG_' . $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? '_RU' : '_EN'),
                                    'ID' => $elementIds
                                ], false, false, ['ID', 'IBLOCK_ID', 'NAME']);
                            while ($arElement = $rsElement->Fetch()) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arElement['ID'], $arElement['IBLOCK_ID'], $arFiles, 'FILE');
                            }
                        }
                    }
                }
            }
        }
    }

}
