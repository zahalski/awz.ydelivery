<?php
//not remove this comment, fix empty include.php
\Bitrix\Main\Loader::includeModule('sale');

//min version main 18.1.1

$module_id = 'awz.ydelivery';

$arJsConfig = array(
    'awz_yd_lib' => array(
        'js' => '/bitrix/js/'.$module_id.'/script.js',
        'css' => '/bitrix/css/'.$module_id.'/style.css',
        'lang' => '/bitrix/modules/'.$module_id.'/lang/'.LANGUAGE_ID.'/js/js_script.php',
        'rel' => array('jquery'),
    ),
);
foreach ($arJsConfig as $ext => $arExt) {
    CJSCore::RegisterExt($ext, $arExt);
}