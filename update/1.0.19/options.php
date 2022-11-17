<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Awz\Ydelivery\Handler;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
Loc::loadMessages(__FILE__);
global $APPLICATION;
$module_id = "awz.ydelivery";
$MODULE_RIGHT = $APPLICATION->GetGroupRight($module_id);
$zr = "";
if (! ($MODULE_RIGHT >= "R"))
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));

$APPLICATION->SetTitle(Loc::getMessage('AWZ_YDELIVERY_OPT_TITLE'));

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

\Bitrix\Main\Loader::includeModule($module_id);

$defStatus = array(
    "CREATED_IN_PLATFORM" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_CREATED_IN_PLATFORM"),
    "CREATED_IN_PLATFORM_DRAFT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_CREATED_IN_PLATFORM_DRAFT"),
    "VALIDATING" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_VALIDATING"),
    "CREATED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_CREATED"),
    "CREATED_DELIVERY_PROCESSING_STARTED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_CREATED_DELIVERY_PROCESSING_STARTED"),
    "DELIVERY_TRACK_RECEIVED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_TRACK_RECEIVED"),
    "SORTING_CENTER_PROCESSING_STARTED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_PROCESSING_STARTED"),
    "SORTING_CENTER_PROCESSING_STARTED_DELIVERY_LOADED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_PROCESSING_STARTED_DELIVERY_LOADED"),
    "DELIVERY_LOADED_DELIVERY_PROCESSING_STARTED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_LOADED_DELIVERY_PROCESSING_STARTED"),
    "DELIVERY_PROCESSING_STARTED_DELIVERY_LOADED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_PROCESSING_STARTED_DELIVERY_LOADED"),
    "SORTING_CENTER_CANCELED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_CANCELED"),
    "RETURN_PREPARING" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_RETURN_PREPARING"),
    "DELIVERY_TRACK_RECEIVED_DELIVERY_LOADED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_TRACK_RECEIVED_DELIVERY_LOADED"),
    "SORTING_CENTER_PROCESSING_STARTED_DELIVERY_PROCESSING_STARTED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_PROCESSING_STARTED_DELIVERY_PROCESSING_STARTED"),
    "DELIVERY_LOADED_DELIVERY_AT_START" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_LOADED_DELIVERY_AT_START"),
    "SORTING_CENTER_PREPARED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_PREPARED"),
    "SORTING_CENTER_TRANSMITTED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_TRANSMITTED"),
    "SORTING_CENTER_TRANSMITTED_DELIVERY_AT_START" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_TRANSMITTED_DELIVERY_AT_START"),
    "DELIVERY_AT_START_DELIVERY_AT_START_SORT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_AT_START_DELIVERY_AT_START_SORT"),
    "DELIVERY_AT_START_SORT_DELIVERY_TRANSPORTATION_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_AT_START_SORT_DELIVERY_TRANSPORTATION_RECIPIENT"),
    "DELIVERY_DELIVERED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_DELIVERED"),
    "DELIVERED_FINISH" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERED_FINISH"),
    "RETURN_PREPARING_DRAFT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_RETURN_PREPARING_DRAFT"),
    "DELIVERY_TRANSPORTATION_RECIPIENT_DELIVERY_UPDATED_BY_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_TRANSPORTATION_RECIPIENT_DELIVERY_UPDATED_BY_RECIPIENT"),
    "DELIVERY_UPDATED_BY_RECIPIENT_DELIVERY_TRANSPORTATION_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_UPDATED_BY_RECIPIENT_DELIVERY_TRANSPORTATION_RECIPIENT"),
    "DELIVERY_TRANSPORTATION_RECIPIENT_DELIVERY_AT_START_SORT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_TRANSPORTATION_RECIPIENT_DELIVERY_AT_START_SORT"),
    "ZAKAZ_GOTOVITSYA_K_OTPRAVKE_DELIVERY_AT_START_SORT_DELIVERY_TRANSPORTATION_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_ZAKAZ_GOTOVITSYA_K_OTPRAVKE_DELIVERY_AT_START_SORT_DELIVERY_TRANSPORTATION_RECIPIENT"),
    "ZAKAZ_PODTVERZHDEN_V_SORTIROVO_DELIVERY_PROCESSING_STARTED_DELIVERY_LOADED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_ZAKAZ_PODTVERZHDEN_V_SORTIROVO_DELIVERY_PROCESSING_STARTED_DELIVERY_LOADED"),
    "DELIVERY_PROCESSING_STARTED_DELIVERY_PROCESSING_STARTED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_PROCESSING_STARTED_DELIVERY_PROCESSING_STARTED"),
    "ON_THE_TRACK" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_ON_THE_TRACK"),
    "DELIVERY_ARRIVED_PICKUP_POINT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_ARRIVED_PICKUP_POINT"),
    "FINISHED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_FINISHED"),
    "DELIVERY_TRANSMITTED_TO_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_TRANSMITTED_TO_RECIPIENT"),
    "SORTING_CENTER_TRANSMITTED_DELIVERY_AT_START_SORT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_TRANSMITTED_DELIVERY_AT_START_SORT"),
    "DELIVERY_AT_START_SORT_DELIVERY_AT_START" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_AT_START_SORT_DELIVERY_AT_START"),
    "DELIVERY_AT_START_DELIVERY_TRANSPORTATION_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_AT_START_DELIVERY_TRANSPORTATION_RECIPIENT"),
    "CANCELLED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_CANCELLED"),
    "RETURN_PREPARING_DELIVERY_PROCESSING_STARTED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_RETURN_PREPARING_DELIVERY_PROCESSING_STARTED"),
    "CANCELLED_USER" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_CANCELLED_USER"),
    "CANCELED_IN_PLATFORM" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_CANCELED_IN_PLATFORM"),
    "DELIVERY_UPDATED_BY_RECIPIENT_DELIVERY_AT_START_SORT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_UPDATED_BY_RECIPIENT_DELIVERY_AT_START_SORT"),
    "POSYLKA_DOSTAVLYAETSYA_KLIENTU_DELIVERY_TRANSPORTATION_RECIPIENT_DELIVERY_UPDATED_BY_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_POSYLKA_DOSTAVLYAETSYA_KLIENTU_DELIVERY_TRANSPORTATION_RECIPIENT_DELIVERY_UPDATED_BY_RECIPIENT"),
    "DELIVERY_TRANSPORTATION" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_TRANSPORTATION"),
    "SORTING_CENTER_RETURN_PREPARING" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_RETURN_PREPARING"),
    "DELIVERY_STORAGE_PERIOD_EXTENDED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_STORAGE_PERIOD_EXTENDED"),
    "DELIVERY_STORAGE_PERIOD_EXPIRED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_STORAGE_PERIOD_EXPIRED"),
    "DELIVERY_UPDATED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_UPDATED"),
    "DELIVERY_UPDATED_DELIVERY_AT_START_SORT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_UPDATED_DELIVERY_AT_START_SORT"),
    "SORTING_CENTER_TRANSMITTED_DELIVERY_LOADED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_SORTING_CENTER_TRANSMITTED_DELIVERY_LOADED"),
    "DELIVERY_TRANSPORTATION_RECIPIENT_DELIVERY_UPDATED_BY_DELIVERY" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_TRANSPORTATION_RECIPIENT_DELIVERY_UPDATED_BY_DELIVERY"),
    "DELIVERY_UPDATED_BY_DELIVERY_DELIVERY_TRANSPORTATION_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_UPDATED_BY_DELIVERY_DELIVERY_TRANSPORTATION_RECIPIENT"),
    "DOSTAVKA_PERENESENA_PO_PROSBE__DELIVERY_UPDATED_BY_RECIPIENT_DELIVERY_TRANSPORTATION_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DOSTAVKA_PERENESENA_PO_PROSBE__DELIVERY_UPDATED_BY_RECIPIENT_DELIVERY_TRANSPORTATION_RECIPIENT"),
    "DELIVERY_AT_START_DELIVERY_LOADED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_AT_START_DELIVERY_LOADED"),
    "DELIVERY_LOADED_DELIVERY_AT_START_SORT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_LOADED_DELIVERY_AT_START_SORT"),
    "DELIVERY_UPDATED_DELIVERY_TRANSPORTATION_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_UPDATED_DELIVERY_TRANSPORTATION_RECIPIENT"),
    "DELIVERY_ATTEMPT_FAILED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_ATTEMPT_FAILED"),
    "DELIVERY_ATTEMPT_FAILED_DELIVERY_TRANSPORTATION_RECIPIENT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_ATTEMPT_FAILED_DELIVERY_TRANSPORTATION_RECIPIENT"),
    "DELIVERY_AT_START_SORT_DELIVERY_UPDATED_BY_DELIVERY" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_AT_START_SORT_DELIVERY_UPDATED_BY_DELIVERY"),
    "DELIVERY_UPDATED_BY_DELIVERY_DELIVERY_AT_START_SORT" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_UPDATED_BY_DELIVERY_DELIVERY_AT_START_SORT"),
    "DELIVERY_LOADED_DELIVERY_UPDATED_BY_DELIVERY" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_LOADED_DELIVERY_UPDATED_BY_DELIVERY"),
    "DELIVERY_UPDATED_BY_DELIVERY_DELIVERY_LOADED" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_UPDATED_BY_DELIVERY_DELIVERY_LOADED"),
    "ZAKAZ_DOBAVLEN_V_TEKUSHCHUYU_O_DELIVERY_LOADED_DELIVERY_UPDATED_BY_DELIVERY" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_ZAKAZ_DOBAVLEN_V_TEKUSHCHUYU_O_DELIVERY_LOADED_DELIVERY_UPDATED_BY_DELIVERY"),
    "DELIVERY_UPDATED_BY_DELIVERY_DELIVERY_AT_START" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_UPDATED_BY_DELIVERY_DELIVERY_AT_START"),
    "DELIVERY_ATTEMPT_FAILED_DELIVERY_UPDATED_BY_DELIVERY" => Loc::getMessage("AWZ_YDELIVERY_ENQ_STAT_DELIVERY_ATTEMPT_FAILED_DELIVERY_UPDATED_BY_DELIVERY")
);

$statusList = unserialize(Option::get($module_id, 'YD_STATUSLIST', '', ''));
if(isset($statusList['DRAFT'])) { //old dublicates statuses
    $statusList = array();
}
//$statusList = array();
if(empty($statusList)) {
    $statusList = $defStatus;
    Option::set($module_id, 'YD_STATUSLIST', serialize($defStatus), '');
}

$deliveryProfileList = \Awz\Ydelivery\Helper::getActiveProfileIds();

$payYandexMethods = \Awz\Ydelivery\Helper::getYandexPayMethods();
$paySystemResult = \Bitrix\Sale\PaySystem\Manager::getList(array(
    'filter'  => array(
        'ACTIVE' => 'Y',
    )
));
$paySystemList = array();
while($payResult = $paySystemResult->fetch()){
    $paySystemList[$payResult['ID']] = $payResult['NAME'];
    //echo'<pre>';print_r($payResult);echo'</pre>';
}

$startProfile = intval($_REQUEST['profile']);
$startCode = trim($_REQUEST['code']);

$minMode = ($startProfile && $startCode);
//print_r(check_bitrix_sessid());
if ($_SERVER["REQUEST_METHOD"] == "POST" && $MODULE_RIGHT == "W" && strlen($_REQUEST["Update"]) > 0 && (check_bitrix_sessid() || $minMode))
{
    if($minMode){

        foreach($statusList as $k=>$v){
            if($startCode != $k) continue;
            foreach($deliveryProfileList as $profileId=>$profileName){
                if($startProfile != $profileId) continue;
                $key = 'md5_'.md5("PARAMS_STATUS_FROM_".$profileId.'_'.$k);
                Option::set($module_id, $key, serialize($_REQUEST["PARAMS_STATUS_FROM_".$profileId.'_'.$k]), '');
                $key = 'md5_'.md5("PARAMS_STATUS_TO_".$profileId.'_'.$k);
                Option::set($module_id, $key, $_REQUEST["PARAMS_STATUS_TO_".$profileId.'_'.$k], '');
            }
        }
    }else{
        $sendRunAgent = false;
        if($_REQUEST['DELETE_PVZ']=='Y'){
            \Awz\Ydelivery\PvzTable::deleteAll();
            CAdminMessage::ShowMessage(array('TYPE'=>'OK',
                'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_OPT_MESS1')));
            $sendRunAgent = true;
        }
        if($_REQUEST['UPDATE_PVZ']=='Y'){
            \Awz\Ydelivery\Checker::agentGetPickpoints(true);
            CAdminMessage::ShowMessage(array('TYPE'=>'OK',
                'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_OPT_MESS2')));
            $sendRunAgent = true;
        }
        if($_REQUEST['UPDATE_GRAFIK_DEL']=='Y'){
            \Awz\Ydelivery\GrafikTable::checkExists();
            \Awz\Ydelivery\GrafikTable::deleteAll();
        }
        if($_REQUEST['UPDATE_GRAFIK']=='Y'){
            \Awz\Ydelivery\GrafikTable::checkExists();
            $resultGrafik = \Awz\Ydelivery\GrafikTable::loadGetFile($_REQUEST['GRAFIK_FILE']);
            if($resultGrafik->isSuccess()){
                CAdminMessage::ShowMessage(array('TYPE'=>'OK',
                    'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_OPT_GRAFIK_OK')));
            }else{
                CAdminMessage::ShowMessage(array('TYPE'=>'ERR',
                    'MESSAGE'=>implode("; ", $resultGrafik->getErrorMessages())));
            }
        }
        //DELETE_AFTER_RESP
        //print_r($_REQUEST);
        //die();

        //if(!$sendRunAgent){

        Option::set($module_id, "ENABLE_LOG", trim($_REQUEST["ENABLE_LOG"]), "");

        Option::set($module_id, "DELETE_AFTER_RESP", trim($_REQUEST["DELETE_AFTER_RESP"]), "");
        Option::set($module_id, "BAR_CODE_TEMPLATE", trim($_REQUEST["BAR_CODE_TEMPLATE"]), "");
        Option::set($module_id, "UPDATE_PVZ_BG", trim($_REQUEST["UPDATE_PVZ_BG"]), "");
        Option::set($module_id, "SEARCH_EXT", trim($_REQUEST["SEARCH_EXT"]), "");
        Option::set($module_id, "MAP_ADDRESS", trim($_REQUEST["MAP_ADDRESS"]), "");
        Option::set($module_id, "CHECKER_FIN_DSBL", serialize($_REQUEST["CHECKER_FIN_DSBL"]), "");

        foreach($statusList as $k=>$v){
            foreach($deliveryProfileList as $profileId=>$profileName){

                foreach($payYandexMethods as $payId=>$payName){
                    Option::set($module_id, "PAY_LINK_".$profileId.'_'.mb_strtoupper($payId), serialize($_REQUEST["PAY_LINK_".$profileId.'_'.mb_strtoupper($payId)]), "");
                }

                Option::set($module_id, "YM_TRADING_ON_".$profileId, trim($_REQUEST["YM_TRADING_ON_".$profileId]), "");

                Option::set($module_id, "PVZ_CODE_".$profileId, trim($_REQUEST["PVZ_CODE_".$profileId]), "");
                Option::set($module_id, "PVZ_ADDRESS_CORD_".$profileId, trim($_REQUEST["PVZ_ADDRESS_CORD_".$profileId]), "");
                Option::set($module_id, "DATE_CODE_".$profileId, trim($_REQUEST["DATE_CODE_".$profileId]), "");
                Option::set($module_id, "PVZ_ADDRESS_".$profileId, trim($_REQUEST["PVZ_ADDRESS_".$profileId]), "");
                Option::set($module_id, "PVZ_ADDRESS_TMPL_".$profileId, trim($_REQUEST["PVZ_ADDRESS_TMPL_".$profileId]), "");
                Option::set($module_id, "OFFERS_ORDER_STATUS_".$profileId, trim($_REQUEST["OFFERS_ORDER_STATUS_".$profileId]), "");

                Option::set($module_id, "HIDE_PAY_ON_".$profileId, trim($_REQUEST["HIDE_PAY_ON_".$profileId]), "");
                Option::set($module_id, "CHECKER_ON_".$profileId, trim($_REQUEST["CHECKER_ON_".$profileId]), "");
                Option::set($module_id, "CHECKER_INTERVAL_".$profileId, trim($_REQUEST["CHECKER_INTERVAL_".$profileId]), "");
                Option::set($module_id, "CHECKER_COUNT_".$profileId, trim($_REQUEST["CHECKER_COUNT_".$profileId]), "");
                Option::set($module_id, "CHECKER_FIN_".$profileId, serialize($_REQUEST["CHECKER_FIN_".$profileId]), "");
                $key = 'md5_'.md5("PARAMS_STATUS_FROM_".$profileId.'_'.$k);
                Option::set($module_id, $key, serialize($_REQUEST["PARAMS_STATUS_FROM_".$profileId.'_'.$k]), '');
                $key = 'md5_'.md5("PARAMS_STATUS_TO_".$profileId.'_'.$k);
                Option::set($module_id, $key, $_REQUEST["PARAMS_STATUS_TO_".$profileId.'_'.$k], '');
            }
        }
        //}else{
        //    CAdminMessage::ShowMessage(array('TYPE'=>'ERR',
        //        'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_OPT_MESS3')));
        //}
    }
}



$statusOb = \CSaleStatus::GetList();
$statusAr = array(array("NAME"=>GetMessage("AWZ_YDELIVERY_VSE_STATUSY"),"ID"=>"ALL"));
$statusAr2 = array(array("NAME"=>GetMessage("AWZ_YDELIVERY_OTKLUCITQ"),"ID"=>"DISABLE"));
while($d = $statusOb->fetch()){
    $statusAr[$d['ID']] = $d;
    $statusAr2[$d['ID']] = $d;
}



$aTabs = array();
if(!$minMode) {
    $aTabs[] = array(
        "DIV" => "edit1",
        "TAB" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT1'),
        "ICON" => "vote_settings",
        "TITLE" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT1')
    );
}
$cnt = 1;
foreach($deliveryProfileList as $profileId=>$profileName){
    if($minMode && $profileId!=$startProfile) continue;
    $cnt++;
    $aTabs[] = array(
        "DIV" => "edit".$cnt,
        "TAB" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT2',array('#PROFILE_NAME#'=>'['.$profileId.'] - '.$profileName)),
        "ICON" => "vote_settings",
        "TITLE" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT2',array('#PROFILE_NAME#'=>'['.$profileId.'] - '.$profileName))
    );
}
$cnt++;
if(!$minMode) {
    $aTabs[] = array(
        "DIV" => "edit" . $cnt,
        "TAB" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT3'),
        "ICON" => "vote_settings",
        "TITLE" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT3')
    );
}

$val_stat_disabled = Option::get($module_id, "CHECKER_FIN_DSBL", "", '');
$val_stat_disabled = unserialize($val_stat_disabled);
if(!is_array($val_stat_disabled)) $val_stat_disabled = array();

$tabControl = new \CAdminTabControl("tabControl", $aTabs);
$tabControl->Begin();
?>
<style>.adm-workarea option:checked {background-color: rgb(206, 206, 206);}</style>
<form method="POST" action="<?echo $APPLICATION->GetCurPage()?>?mid=<?=htmlspecialcharsbx($module_id)?>&lang=<?=LANGUAGE_ID?>&mid_menu=1" id="FORMACTION">
    <?if(!$minMode) {?>
<?
$tabControl->BeginNextTab();
?>
    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BARCODE_TEMPLATE')?></td>
        <td>
            <?$val = Option::get($module_id, "BAR_CODE_TEMPLATE", "", "");?>
            <?if(!$val){
                $val = '#DATE##ORDER#-'.mb_strtoupper(\Bitrix\Main\Security\Random::getString(3));
                Option::set($module_id, "BAR_CODE_TEMPLATE", $val, "");
            }?>
            <input type="text" size="35" maxlength="255" value="<?=$val?>" name="BAR_CODE_TEMPLATE"/>
            <br>
            <p>
            #RAND2# - <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BARCODE_TEMPLATE_MACROS1')?><br>
            #RAND3# - <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BARCODE_TEMPLATE_MACROS2')?><br>
            #DATE# - <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BARCODE_TEMPLATE_MACROS3')?><br>
            #DATE_M# - <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BARCODE_TEMPLATE_MACROS4')?><br>
            #DATE_D# - <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BARCODE_TEMPLATE_MACROS5')?><br>
            #DATE_Y# - <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BARCODE_TEMPLATE_MACROS6')?><br>
            #SEC# - <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BARCODE_TEMPLATE_MACROS7')?><br>
            #ORDER# - <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BARCODE_TEMPLATE_MACROS8')?>
            </p>
        </td>
    </tr>
    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_DSBL_STATUS')?></td>
        <td>
            <?

            ?>
            <select name="CHECKER_FIN_DSBL[]" multiple="multiple" size="20" style="max-width:1200px;">
                <?
                foreach($statusList as $k=>$stat){
                    $selected = '';
                    if(in_array($k,$val_stat_disabled)) $selected = ' selected="selected"';
                    echo '<option value="'.$k.'"'.$selected.'>['.$k.'] - '.$stat.'</option>';
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_ONDEL')?></td>
        <td>
            <?$val = Option::get($module_id, "DELETE_AFTER_RESP", "N","");?>
            <input type="checkbox" value="Y" name="DELETE_AFTER_RESP" <?if ($val=="Y") echo "checked";?>></td>
    </tr>
    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_MAP_ADRESS')?></td>
        <td>
            <?$val = Option::get($module_id, "MAP_ADDRESS", "N","");?>
            <input type="checkbox" value="Y" name="MAP_ADDRESS" <?if ($val=="Y") echo "checked";?>></td>
    </tr>
    <tr class="heading">
        <td colspan="2">
            <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_GROUP1')?>
        </td>
    </tr>
    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_UPPVZ_BG')?></td>
        <td>
            <?$val = Option::get($module_id, "UPDATE_PVZ_BG", "Y", "");?>
            <input type="checkbox" value="Y" name="UPDATE_PVZ_BG" <?if ($val=="Y") echo "checked";?>></td>
    </tr>
    <tr>
        <td colspan="2" align="center">
            <div class="adm-info-message-wrap">
                <div class="adm-info-message">
                    <div> <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_GROUP1_DESC')?></div>
                </div>
            </div>
        </td>
    </tr>
    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_DELPVZ')?></td>
        <td>
            <?$val = "N";?>
            <input type="checkbox" value="Y" name="DELETE_PVZ" <?if ($val=="Y") echo "checked";?>></td>
    </tr>
    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_UPPVZ')?></td>
        <td>
            <?$val = "N";?>
            <input type="checkbox" value="Y" name="UPDATE_PVZ" <?if ($val=="Y") echo "checked";?>></td>
    </tr>
    <tr class="heading">
        <td colspan="2">
            <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_UPPVZ_LINK')?>
        </td>
    </tr>
    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_UPPVZ_LINK_ON')?></td>
        <td>
            <?$val = Option::get($module_id, "SEARCH_EXT", "N","");?>
            <input type="checkbox" value="Y" name="SEARCH_EXT" <?if ($val=="Y") echo "checked";?>></td>
    </tr>
    <tr>
        <td width="50%"></td>
        <td>
            <a href="/bitrix/admin/awz_ydelivery_picpoint_ext.php?lang=ru">
                <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_UPPVZ_LOAD')?>
            </a>
        </td>
    </tr>

        <?if(class_exists("\\Awz\\Ydelivery\\GrafikTable")){?>
        <tr class="heading">
            <td colspan="2">
                <?=Loc::getMessage('AWZ_YDELIVERY_OPT_GRAFIK_FILE')?>
            </td>
        </tr>
        <tr>
            <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_GRAFIK_FILE_L1')?></td>
            <td>
                <?$val = $_REQUEST['UPDATE_GRAFIK'];?>
                <input type="checkbox" value="Y" name="UPDATE_GRAFIK" <?if ($val=="Y") echo "checked";?>></td>
        </tr>
        <tr>
            <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_GRAFIK_FILE_L2')?></td>
            <td>
                <?$val = $_REQUEST['UPDATE_GRAFIK_DEL'];?>
                <input type="checkbox" value="Y" name="UPDATE_GRAFIK_DEL" <?if ($val=="Y") echo "checked";?>></td>
        </tr>
        <tr>
            <td><?=GetMessage("AWZ_YDELIVERY_OPT_GRAFIK_FILE")?>:</td>
            <td>
                <?$val = $_REQUEST['GRAFIK_FILE'];?>
                <?CAdminFileDialog::ShowScript(array(
                    "event" => "GRAFIK_FILE",
                    "arResultDest" => array("ELEMENT_ID" => "GRAFIK_FILE"),
                    "arPath" => array("PATH" => GetDirPath(($val))),
                    "select" => 'F',// F - file only, D - folder only
                    "operation" => 'O',// O - open, S - save
                    "showUploadTab" => true,
                    "showAddToMenuTab" => false,
                    "fileFilter" => 'csv',
                    "allowAllFiles" => false,
                    "SaveConfig" => true,
                ));?>
                <input
                        name="GRAFIK_FILE"
                        id="GRAFIK_FILE"
                        type="text"
                        value="<?echo htmlspecialcharsbx($val)?>"
                        size="35">&nbsp;<input
                        type="button"
                        value="..."
                        onClick="window.GRAFIK_FILE()"
                >
            </td>
        </tr>
        <?}?>
        <tr class="heading">
            <td colspan="2">
                <?=Loc::getMessage('AWZ_YDELIVERY_OPT_ENABLE_LOG_TITLE')?><br>
                <a href="/bitrix/admin/event_log.php?PAGEN_1=1&SIZEN_1=20&lang=ru&set_filter=Y&adm_filter_applied=0&find_type=audit_type_id&find_module_id=awz.ydelivery"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_ENABLE_LOG_PATH')?></a>
            </td>
        </tr>
        <tr>
            <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_ENABLE_LOG')?></td>
            <td>
                <?$val = Option::get($module_id, "ENABLE_LOG", "","");?>
                <select name="ENABLE_LOG">
                    <option value=""><?=Loc::getMessage('AWZ_YDELIVERY_OPT_ENABLE_LOG_1')?></option>
                    <option value="DEBUG"<?=($val==='DEBUG' ? ' selected="selected"' : '')?>><?=Loc::getMessage('AWZ_YDELIVERY_OPT_ENABLE_LOG_2')?></option>
                    <option value="ERROR"<?=($val==='ERROR' ? ' selected="selected"' : '')?>><?=Loc::getMessage('AWZ_YDELIVERY_OPT_ENABLE_LOG_3')?></option>
                </select>
            </td>
        </tr>
<?}?>

    <?
foreach($deliveryProfileList as $profileId=>$profileName){
    if($minMode && $profileId!=$startProfile) continue;
    $tabControl->BeginNextTab();

    $pvzList = \Awz\Ydelivery\Helper::getActiveProfileIds(\Awz\Ydelivery\Helper::DOST_TYPE_PVZ);
    $isPvz = isset($pvzList[$profileId]) ? true : false;

    ?>
<?if(!$minMode){?>
    <tr class="heading">
        <td colspan="2">
            <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROFILE_PROP')?>
        </td>
    </tr>

<?if($isPvz){?>
    <?if(\Bitrix\Main\Loader::includeModule('yandex.market')){?>
    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_YM_TRADING_ON')?></td>
        <td>
            <?$val = Option::get($module_id, "YM_TRADING_ON_".$profileId, "N","");?>
            <input type="checkbox" value="Y" name="YM_TRADING_ON_<?=$profileId?>" <?if ($val=="Y") echo "checked";?>></td>
    </tr>
    <?}?>
    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROPPVZ')?></td>
        <td>
            <?$val = Option::get($module_id, "PVZ_CODE_".$profileId, "AWZ_YD_POINT_ID", "");?>
            <input type="text" size="35" maxlength="255" value="<?=$val?>" name="PVZ_CODE_<?=$profileId?>"/>
        </td>
    </tr>

    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROPPVZ_ADR')?></td>
        <td>
            <?$val = Option::get($module_id, "PVZ_ADDRESS_".$profileId, "PVZ_ADDRESS", "");?>
            <input type="text" size="35" maxlength="255" value="<?=$val?>" name="PVZ_ADDRESS_<?=$profileId?>"/>
        </td>
    </tr>
    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROPPVZ_ADR_TMPL')?></td>
        <td>
            <?$val = Option::get($module_id, "PVZ_ADDRESS_TMPL_".$profileId, "#ADDRESS#", "");?>
            <input type="text" size="35" maxlength="255" value="<?=$val?>" name="PVZ_ADDRESS_TMPL_<?=$profileId?>"/>
            <p><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROPPVZ_ADR_TMPL_DESC')?></p>
        </td>
    </tr>

<?}else{?>
    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROPPVZ_ADR_CORD')?></td>
        <td>
            <?$val = Option::get($module_id, "PVZ_ADDRESS_CORD_".$profileId, "CORD_ADDRESS", "");?>
            <input type="text" size="35" maxlength="255" value="<?=$val?>" name="PVZ_ADDRESS_CORD_<?=$profileId?>"/>
        </td>
    </tr>
<?}?>

    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROPPVZ_DATE')?></td>
        <td>
            <?$val = Option::get($module_id, "DATE_CODE_".$profileId, "AWZ_YD_DATE", "");?>
            <input type="text" size="35" maxlength="255" value="<?=$val?>" name="DATE_CODE_<?=$profileId?>"/>
        </td>
    </tr>


    <tr class="heading">
        <td colspan="2">
            <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROFILE_PAYS')?>
        </td>
    </tr>
    <?if($isPvz){?>

    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PAY_CHANGE_HIDE')?></td>
        <td>
            <?$val = Option::get($module_id, "HIDE_PAY_ON_".$profileId, "N","");?>
            <input type="checkbox" value="Y" name="HIDE_PAY_ON_<?=$profileId?>" <?if ($val=="Y") echo "checked";?>></td>
    </tr>
    <?}?>
        <?foreach($payYandexMethods as $keyPay=>$valPay){?>
    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PAY_CHANGE')?> [<?=$keyPay?>] - <?=$valPay?></td>
        <td>
            <?
            $val = Option::get($module_id, "PAY_LINK_".$profileId.'_'.mb_strtoupper($keyPay), "", '');
            $val = unserialize($val);
            ?>
            <select name="PAY_LINK_<?=$profileId?>_<?=mb_strtoupper($keyPay)?>[]" multiple="multiple">
                <?
                foreach($paySystemList as $k=>$stat){
                    $selected = '';
                    if(in_array($k,$val)) $selected = ' selected="selected"';
                    echo '<option value="'.$k.'"'.$selected.'>['.$k.'] - '.$stat.'</option>';
                }
                ?>
            </select>
        </td>
    </tr>
        <?}?>


    <tr class="heading">
        <td colspan="2">
            <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROFILE_AUTO')?>
        </td>
    </tr>


    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_AUTOCREATE')?></td>
        <td>
            <?$val = Option::get($module_id, 'OFFERS_ORDER_STATUS_'.$profileId, '', '');?>
            <div class="adm-info-message-wrap">
                <div class="adm-info-message">
                    <div> <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_GROUP3_DESC')?></div>
                </div>
            </div>
            <select name="OFFERS_ORDER_STATUS_<?=$profileId?>">
                <?
                foreach($statusAr2 as $stat){
                    $selected = '';
                    if($stat['ID'] == $val) $selected = ' selected="selected"';
                    echo '<option value="'.$stat['ID'].'"'.$selected.'>['.$stat['ID'].'] - '.$stat['NAME'].'</option>';
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_SYNC')?></td>
        <td>
            <?$val = Option::get($module_id, "CHECKER_ON_".$profileId, "N","");?>
            <div class="adm-info-message-wrap">
                <div class="adm-info-message">
                    <div> <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_GROUP2_DESC')?></div>
                </div>
            </div>
            <input type="checkbox" value="Y" name="CHECKER_ON_<?=$profileId?>" <?if ($val=="Y") echo "checked";?>>

        </td>
    </tr>

    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_SYNC_INTERVAL')?></td>
        <td>
            <?$val = Option::get($module_id, "CHECKER_INTERVAL_".$profileId, "", '');?>
            <div class="adm-info-message-wrap">
                <div class="adm-info-message">
                    <div> <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_GROUP5_DESC')?></div>
                </div>
            </div>
            <input type="text" size="35" maxlength="255" value="<?=$val?>" name="CHECKER_INTERVAL_<?=$profileId?>"/>
        </td>
    </tr>
    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_SYNC_MAX')?></td>
        <td>
            <?$val = Option::get($module_id, "CHECKER_COUNT_".$profileId, "", '');?>
            <div class="adm-info-message-wrap">
                <div class="adm-info-message">
                    <div> <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_GROUP4_DESC')?></div>
                </div>
            </div>
            <input type="text" size="35" maxlength="255" value="<?=$val?>" name="CHECKER_COUNT_<?=$profileId?>"/>
        </td>
    </tr>
    <tr>
        <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_SYNC_FIN')?></td>
        <td>
            <?
            $val = Option::get($module_id, "CHECKER_FIN_".$profileId, "", '');
            $val = unserialize($val);
            if(!is_array($val)) $val = array();

            ?>
            <select name="CHECKER_FIN_<?=$profileId?>[]" multiple="multiple" size="20" style="max-width:1200px;">
                <?
                foreach($statusList as $k=>$stat){
                    if(in_array($k,$val_stat_disabled)) continue;
                    $selected = '';
                    if(in_array($k,$val)) $selected = ' selected="selected"';
                    echo '<option value="'.$k.'"'.$selected.'>['.$k.'] - '.$stat.'</option>';
                }
                ?>
            </select>
        </td>
    </tr>
    <?

    foreach($statusList as $k=>$v){
        if(in_array($k,$val_stat_disabled)) continue;
        ?>
        <tr class="heading"><td colspan="2"><?=$k?>: <br><?=$v?></td></tr>
            <tr>
                <td>
                    <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_SYNC_FROM')?>
                </td>
                <td>
                    <?
                    $key = 'md5_'.md5('PARAMS_STATUS_FROM_'.$profileId.'_'.$k);

                    $val = Option::get($module_id, $key, '', '');
                    $val = unserialize($val);
                    if(!is_array($val)) $val = array();
                    ?>
                    <select name="PARAMS_STATUS_FROM_<?=$profileId?>_<?=$k?>[]" multiple="multiple">
                        <?
                        foreach($statusAr as $stat){
                            $selected = '';
                            if(in_array($stat['ID'],$val)) $selected = ' selected="selected"';
                            echo '<option value="'.$stat['ID'].'"'.$selected.'>['.$stat['ID'].'] - '.$stat['NAME'].'</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_SYNC_TO')?></td>
                <td>
                    <?
                    $key = 'md5_'.md5('PARAMS_STATUS_TO_'.$profileId.'_'.$k);
                    $val = Option::get($module_id, $key, '', '');?>
                    <select name="PARAMS_STATUS_TO_<?=$profileId?>_<?=$k?>">
                        <?
                        foreach($statusAr2 as $stat){
                            $selected = '';
                            if($stat['ID'] == $val) $selected = ' selected="selected"';
                            echo '<option value="'.$stat['ID'].'"'.$selected.'>['.$stat['ID'].'] - '.$stat['NAME'].'</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>


        <?
    }
    ?>
<?}else{?>
        <?

        foreach($statusList as $k=>$v){
            if(in_array($k,$val_stat_disabled)) continue;
            if($startCode && ($startCode != $k)) continue;
            ?>
            <tr class="heading"><td colspan="2"><?=$k?>: <br><?=$v?></td></tr>
            <tr>
                <td>
                    <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_SYNC_FROM')?>
                </td>
                <td>
                    <?
                    $key = 'md5_'.md5('PARAMS_STATUS_FROM_'.$profileId.'_'.$k);

                    $val = Option::get($module_id, $key, '', '');
                    $val = unserialize($val);
                    if(!is_array($val)) $val = array();
                    ?>
                    <select name="PARAMS_STATUS_FROM_<?=$profileId?>_<?=$k?>[]" multiple="multiple">
                        <?
                        foreach($statusAr as $stat){
                            $selected = '';
                            if(in_array($stat['ID'],$val)) $selected = ' selected="selected"';
                            echo '<option value="'.$stat['ID'].'"'.$selected.'>['.$stat['ID'].'] - '.$stat['NAME'].'</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_SYNC_TO')?></td>
                <td>
                    <?
                    $key = 'md5_'.md5('PARAMS_STATUS_TO_'.$profileId.'_'.$k);
                    $val = Option::get($module_id, $key, '', '');?>
                    <select name="PARAMS_STATUS_TO_<?=$profileId?>_<?=$k?>">
                        <?
                        foreach($statusAr2 as $stat){
                            $selected = '';
                            if($stat['ID'] == $val) $selected = ' selected="selected"';
                            echo '<option value="'.$stat['ID'].'"'.$selected.'>['.$stat['ID'].'] - '.$stat['NAME'].'</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>


            <?
        }
        ?>
    <?}?>
        <?
}
    ?>
<?if(!$minMode) {?>
    <?
    $tabControl->BeginNextTab();
    require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");
    ?>
<?}?>
    <?
    $tabControl->Buttons();
    ?>
    <input <?if ($MODULE_RIGHT<"W") echo "disabled" ?> type="submit" class="adm-btn-green" name="Update" value="<?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BTN_SAVE')?>" />
    <input type="hidden" name="Update" value="Y" />
    <input type="hidden" name="IFRAME_TYPE" value="<?=$_REQUEST['IFRAME_TYPE']?>">
    <input type="hidden" name="IFRAME" value="<?=$_REQUEST['IFRAME']?>">
    <input type="hidden" name="profile" value="<?=$startProfile?>">
    <input type="hidden" name="code" value="<?=$startCode?>">
    <?$tabControl->End();
    ?>
</form>
<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");