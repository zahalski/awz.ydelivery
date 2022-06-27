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
    'CREATED_ERROR'=> GetMessage("AWZ_YDELIVERY_OSIBKA_BRONIROVANIE"),
	'DRAFT'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_ZAGRUJEN"),
	'CREATED'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_PODTVERJDEN"),
	'RESERVED'=> GetMessage("AWZ_YDELIVERY_ZAREZERVIROVANO_MEST"),
	'EXPECTING_DELIVERY'=> GetMessage("AWZ_YDELIVERY_OJIDAETSA_POSTAVKA"),
	'VALIDATING_ERROR'=> GetMessage("AWZ_YDELIVERY_NEOBHODIMO_PERESOZDA"),
	'VALIDATING'=> GetMessage("AWZ_YDELIVERY_IDET_POISK_ISPOLNITE"),
	'DELIVERY_PROCESSING_STARTED'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_SOZDAN_V_SORTI"),
	'DELIVERY_TRACK_RECEIVED'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_SOZDAN_V_SISTE"),
	'SORTING_CENTER_AT_START'=> GetMessage("AWZ_YDELIVERY_NA_SKLADE_SORTIROVOC"),
	'SORTING_CENTER_PREPARED'=> GetMessage("AWZ_YDELIVERY_GOTOV_K_OTPRAVKE_V_S"),
	'DELIVERY_AT_START'=> GetMessage("AWZ_YDELIVERY_NA_SKLADE_SLUJBY_DOS"),
	'OUT_OF_STOCK'=> GetMessage("AWZ_YDELIVERY_NET_NA_SKLADE"),
	'SENDER_WAIT_FULFILLMENT'=> GetMessage("AWZ_YDELIVERY_OJIDAETSA_V_SLUJBE_D"),
	'READY_FOR_TRACK'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_PODGOTOVLEN_NA"),
	'ON_THE_TRACK'=> GetMessage("AWZ_YDELIVERY_NA_MAGISTRALI"),
	'DELIVERY_ARRIVED'=> GetMessage("AWZ_YDELIVERY_V_GORODE_POLUCATELA"),
	'ORDERED'=> GetMessage("AWZ_YDELIVERY_POLQZOVATELQ_POLOJIL"),
	'COURIER_ASSIGNED'=> GetMessage("AWZ_YDELIVERY_KURQER_NAZNACEN"),
	'READY_FOR_DELIVERY'=> GetMessage("AWZ_YDELIVERY_GOTOVY_DOSTAVITQ_K"),
	'DELIVERY_TRANSPORTATION_RECIPIENT'=> GetMessage("AWZ_YDELIVERY_POSYLKA_DOSTAVLAETSA"),
	'DELIVERY_STORAGE_PERIOD_EXTENDED'=> GetMessage("AWZ_YDELIVERY_SROK_HRANENIA_ZAKAZA"),
	'DELIVERY_STORAGE_PERIOD_EXPIRED'=> GetMessage("AWZ_YDELIVERY_SROK_HRANENIA_ZAKAZA1"),
	'ORDER_CANCELLED'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_OTMENEN"),
	'DELIVERY_DELIVERED'=> GetMessage("AWZ_YDELIVERY_DOSTAVLEN"),
	'PARTIALLY_DELIVERED'=> GetMessage("AWZ_YDELIVERY_DOSTAVLEN_CASTICNO"),
	'DELIVERY_UPDATED'=> GetMessage("AWZ_YDELIVERY_DOSTAVKA_PERENESENA"),
	'DELIVERY_UPDATED_BY_RECIPIENT'=> GetMessage("AWZ_YDELIVERY_DOSTAVKA_PERENESENA1"),
	'DELIVERY_UPDATED_BY_DELIVERY'=> GetMessage("AWZ_YDELIVERY_DOSTAVKA_PERENESENA2"),
	'DELIVERY_ATTEMPT_FAILED'=> GetMessage("AWZ_YDELIVERY_NEUDACNAA_POPYTKA_VR"),
	'DELIVERY_CAN_NOT_BE_COMPLETED'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_NE_MOJET_BYTQ"),
	'CANCELLED_BY_RECIPIENT'=> GetMessage("AWZ_YDELIVERY_DOSTAVKA_OTMENENA_PO"),
	'DELIVERY_REJECTED'=> GetMessage("AWZ_YDELIVERY_ZAAVKA_NA_DOSTAVKU_O"),
	'DELIVERED_FINISH'=> GetMessage("AWZ_YDELIVERY_DOSTAVLEN_PODTVERJ"),
	'RETURN_PREPARING'=> GetMessage("AWZ_YDELIVERY_GOTOVITSA_K_VOZVRATU"),
	'SORTING_CENTER_RETURN_PREPARING'=> GetMessage("AWZ_YDELIVERY_GOTOVITSA_K_VOZVRATU1"),
	'SORTING_CENTER_RETURN_ARRIVED'=> GetMessage("AWZ_YDELIVERY_VOZVRATNYY_ZAKAZ_NA"),
	'SORTING_CENTER_RETURN_PREPARING_SENDER'=> GetMessage("AWZ_YDELIVERY_GOTOV_DLA_PEREDACI_M"),
	'SORTING_CENTER_CANCELED'=> GetMessage("AWZ_YDELIVERY_OTMENEN_SORTIROVOCNY"),
	'SORTING_CENTER_RETURN_TRANSFERRED'=> GetMessage("AWZ_YDELIVERY_VOZVRAT_NA_PUTI_K_MA"),
	'RETURN_ARRIVED'=> GetMessage("AWZ_YDELIVERY_VODITELQ_PRIEHAL_V_T"),
	'SORTING_CENTER_RETURN_RETURNED'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_VOZVRASEN_V_TO"),
	'RETURNED_FINISH'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_ZAVERSEN"),
	'CANCELLED'=> GetMessage("AWZ_YDELIVERY_OTMENA_PODTVERJDEN"),
	'CANCEL_WITH_PAYMENT'=> GetMessage("AWZ_YDELIVERY_ZAKAZ_BYL_OTMENEN_KL"),
	'DELIVERY_ARRIVED_PICKUP_POINT'=> GetMessage("AWZ_YDELIVERY_V_PUNKTE_SAMOVYVOZA"),
	'SORTING_CENTER_ERROR'=> GetMessage("AWZ_YDELIVERY_OSIBKA_SOZDANIA_ZAKA"),
	'LOST'=> GetMessage("AWZ_YDELIVERY_UTERAN"),
	'UNEXPECTED'=> GetMessage("AWZ_YDELIVERY_STATUS_UTOCNAETSA"),
	'CANCELLED_USER'=> GetMessage("AWZ_YDELIVERY_OTMENEN_POLQZOVATELE")
);

$statusList = unserialize(Option::get($module_id, 'YD_STATUSLIST', '', ''));
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && $MODULE_RIGHT == "W" && strlen($_REQUEST["Update"]) > 0 && check_bitrix_sessid())
{
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
    //DELETE_AFTER_RESP
    //print_r($_REQUEST);
    //die();

    //if(!$sendRunAgent){

        Option::set($module_id, "DELETE_AFTER_RESP", trim($_REQUEST["DELETE_AFTER_RESP"]), "");
        Option::set($module_id, "BAR_CODE_TEMPLATE", trim($_REQUEST["BAR_CODE_TEMPLATE"]), "");
        Option::set($module_id, "UPDATE_PVZ_BG", trim($_REQUEST["UPDATE_PVZ_BG"]), "");
        Option::set($module_id, "SEARCH_EXT", trim($_REQUEST["SEARCH_EXT"]), "");

        foreach($statusList as $k=>$v){
            foreach($deliveryProfileList as $profileId=>$profileName){

                foreach($payYandexMethods as $payId=>$payName){
                    Option::set($module_id, "PAY_LINK_".$profileId.'_'.mb_strtoupper($payId), serialize($_REQUEST["PAY_LINK_".$profileId.'_'.mb_strtoupper($payId)]), "");
                }

                Option::set($module_id, "PVZ_CODE_".$profileId, trim($_REQUEST["PVZ_CODE_".$profileId]), "");
                Option::set($module_id, "DATE_CODE_".$profileId, trim($_REQUEST["DATE_CODE_".$profileId]), "");
                Option::set($module_id, "PVZ_ADDRESS_".$profileId, trim($_REQUEST["PVZ_ADDRESS_".$profileId]), "");
                Option::set($module_id, "PVZ_ADDRESS_TMPL_".$profileId, trim($_REQUEST["PVZ_ADDRESS_TMPL_".$profileId]), "");
                Option::set($module_id, "OFFERS_ORDER_STATUS_".$profileId, trim($_REQUEST["OFFERS_ORDER_STATUS_".$profileId]), "");

                Option::set($module_id, "HIDE_PAY_ON_".$profileId, trim($_REQUEST["HIDE_PAY_ON_".$profileId]), "");
                Option::set($module_id, "CHECKER_ON_".$profileId, trim($_REQUEST["CHECKER_ON_".$profileId]), "");
                Option::set($module_id, "CHECKER_INTERVAL_".$profileId, trim($_REQUEST["CHECKER_INTERVAL_".$profileId]), "");
                Option::set($module_id, "CHECKER_COUNT_".$profileId, trim($_REQUEST["CHECKER_COUNT_".$profileId]), "");
                Option::set($module_id, "CHECKER_FIN_".$profileId, serialize($_REQUEST["CHECKER_FIN_".$profileId]), "");
                Option::set($module_id, "PARAMS_STATUS_FROM_".$profileId.'_'.$k, serialize($_REQUEST["PARAMS_STATUS_FROM_".$profileId.'_'.$k]), '');
                Option::set($module_id, "PARAMS_STATUS_TO_".$profileId.'_'.$k, $_REQUEST["PARAMS_STATUS_TO_".$profileId.'_'.$k], '');
            }
        }
    //}else{
    //    CAdminMessage::ShowMessage(array('TYPE'=>'ERR',
    //        'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_OPT_MESS3')));
    //}

}



$statusOb = \CSaleStatus::GetList();
$statusAr = array(array("NAME"=>GetMessage("AWZ_YDELIVERY_VSE_STATUSY"),"ID"=>"ALL"));
$statusAr2 = array(array("NAME"=>GetMessage("AWZ_YDELIVERY_OTKLUCITQ"),"ID"=>"DISABLE"));
while($d = $statusOb->fetch()){
    $statusAr[$d['ID']] = $d;
    $statusAr2[$d['ID']] = $d;
}

$aTabs = array();
$aTabs[] = array(
    "DIV" => "edit1",
    "TAB" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT1'),
    "ICON" => "vote_settings",
    "TITLE" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT1')
);
$cnt = 1;
foreach($deliveryProfileList as $profileId=>$profileName){
    $cnt++;
    $aTabs[] = array(
        "DIV" => "edit".$cnt,
        "TAB" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT2',array('#PROFILE_NAME#'=>'['.$profileId.'] - '.$profileName)),
        "ICON" => "vote_settings",
        "TITLE" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT2',array('#PROFILE_NAME#'=>'['.$profileId.'] - '.$profileName))
    );
}
$cnt++;
$aTabs[] = array(
    "DIV" => "edit".$cnt,
    "TAB" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT3'),
    "ICON" => "vote_settings",
    "TITLE" => Loc::getMessage('AWZ_YDELIVERY_OPT_SECT3')
);

$tabControl = new \CAdminTabControl("tabControl", $aTabs);
$tabControl->Begin();
?>
<style>.adm-workarea option:checked {background-color: rgb(206, 206, 206);}</style>
<form method="POST" action="<?echo $APPLICATION->GetCurPage()?>?mid=<?=htmlspecialcharsbx($module_id)?>&lang=<?=LANGUAGE_ID?>&mid_menu=1" id="FORMACTION">

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
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_ONDEL')?></td>
        <td>
            <?$val = Option::get($module_id, "DELETE_AFTER_RESP", "N","");?>
            <input type="checkbox" value="Y" name="DELETE_AFTER_RESP" <?if ($val=="Y") echo "checked";?>></td>
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


    <?
foreach($deliveryProfileList as $profileId=>$profileName){
    $tabControl->BeginNextTab();

    $pvzList = \Awz\Ydelivery\Helper::getActiveProfileIds(\Awz\Ydelivery\Helper::DOST_TYPE_PVZ);
    $isPvz = isset($pvzList[$profileId]) ? true : false;

    ?>

    <tr class="heading">
        <td colspan="2">
            <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_PROFILE_PROP')?>
        </td>
    </tr>

<?if($isPvz){?>
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
            ?>
            <select name="CHECKER_FIN_<?=$profileId?>[]" multiple="multiple">
                <?
                foreach($statusList as $k=>$stat){
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
        ?>
        <tr class="heading"><td colspan="2"><?=$k?>: <?=$v?></td></tr>
        <tr>
            <td>
                <?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_STATUS_SYNC_FROM')?>
            </td>
            <td>
                <?$val = Option::get($module_id, 'PARAMS_STATUS_FROM_'.$profileId.'_'.$k, '', '');
                $val = unserialize($val);
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
                <?$val = Option::get($module_id, 'PARAMS_STATUS_TO_'.$profileId.'_'.$k, '', '');?>
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

        <?
}
    ?>

    <?
    $tabControl->BeginNextTab();
    require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");
    $tabControl->Buttons();
    ?>
    <input <?if ($MODULE_RIGHT<"W") echo "disabled" ?> type="submit" class="adm-btn-green" name="Update" value="<?=Loc::getMessage('AWZ_YDELIVERY_OPT_L_BTN_SAVE')?>" />
    <input type="hidden" name="Update" value="Y" />
    <?$tabControl->End();
    ?>
</form>
<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");