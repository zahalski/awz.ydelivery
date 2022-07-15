<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
global $APPLICATION;
$module_id = "awz.ydelivery";

\Bitrix\Main\Loader::includeModule($module_id);
\Bitrix\Main\Loader::includeModule('sale');

use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Awz\Ydelivery\Helper;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Result;
use Bitrix\Main\Security;
use Awz\Ydelivery\OffersTable;
use Awz\Ydelivery\PvzTable;
Loc::loadMessages(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($POST_RIGHT == "D")
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$APPLICATION->SetTitle(Loc::getMessage("AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE"));
$APPLICATION->SetAdditionalCSS("/bitrix/css/".$module_id."/style.css");

\CUtil::InitJSCore(array('ajax', 'awz_yd_lib', 'sidepanel'));

$key = Option::get("fileman", "yandex_map_api_key");
Asset::getInstance()->addString('<script src="//api-maps.yandex.ru/2.1/?lang=ru_RU&apikey='.$key.'"></script>', true);

$ID = intval($_REQUEST['id']);
$ORDER_ID = intval($_REQUEST['ORDER_ID']);


$val_stat_disabled = Option::get($module_id, "CHECKER_FIN_DSBL", "", '');
$val_stat_disabled = unserialize($val_stat_disabled);
if(!is_array($val_stat_disabled)) $val_stat_disabled = array();

$isOrdered = OffersTable::getList(array('select'=>array('ID'),'filter'=>array('=ORDER_ID'=>$ORDER_ID)))->fetch();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if($_REQUEST['START_AGENT'] == 'Y'){
    \Awz\Ydelivery\Checker::agentGetStatus(true);
}

if(!$ID && $isOrdered){
    CAdminMessage::ShowMessage(array('TYPE'=>'ERROR',
        'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ORDER_IS_SET')));
}elseif(!$ID && $ORDER_ID){

    $result = new Result();

    $order = \Bitrix\Sale\Order::load($ORDER_ID);
    /* @var \Bitrix\Sale\PaymentCollection*/
    $paymentCollection = $order->getPaymentCollection();
    $paymentId = 0;
    if($paymentCollection){
        foreach ($paymentCollection as $payment) {
            $paymentId = $payment->getPaymentSystemId();
        }
    }
    //print_r($paymentId);

    $propertyCollection = $order->getPropertyCollection();

    $profilePvz = Helper::getProfileId($order, Helper::DOST_TYPE_PVZ);
    $profileAddress = Helper::getProfileId($order, Helper::DOST_TYPE_ADR);

    //print_r($profilePvz);
    //print_r($profileAddress);

    $curProfile = $profilePvz ? $profilePvz : $profileAddress;

    $arOffers = array();
    $autoOfferId = '';

    if($_REQUEST['action'] == 'send_offer'){

        $api = Helper::getApiByProfileId($curProfile);
        $offerId = $_REQUEST['offer'];

        $bronResult = $api->offerConfirm($offerId);
        if(!$bronResult->isSuccess()){
            $result->addErrors($bronResult->getErrors());
            //return $result;
        }else{

            $bronData = $bronResult->getData();

            $resultAdd = OffersTable::add(
                array(
                    'ORDER_ID'=>$order->getId(),
                    'OFFER_ID'=>$bronData['result']['request_id'],
                    'CREATE_DATE'=>\Bitrix\Main\Type\DateTime::createFromTimestamp(time()),
                    'LAST_DATE'=>\Bitrix\Main\Type\DateTime::createFromTimestamp(time()),
                )
            );

            if(!$resultAdd->isSuccess()){
                $result->addErrors($resultAdd->getErrors());
            }else{
                $result->setData(array('offer_id'=>$resultAdd->getId()));
                $addFraime = '';
                if($_REQUEST['IFRAME_TYPE'] == 'SIDE_SLIDER'){
                    $addFraime = '&IFRAME_TYPE=SIDE_SLIDER&IFRAME=Y';
                }
                LocalRedirect('/bitrix/admin/awz_ydelivery_offers_list_edit.php?lang='.LANGUAGE_ID.'&id='.$resultAdd->getId().$addFraime);
            }

        }

    }elseif($_REQUEST['action'] == 'get_offers'){

        $prepareResult = OffersTable::getPrepare($order);
        //print_r($order->getField('PAYED'));


        if($prepareResult->isSuccess()){
            $prepareData = $prepareResult->getData();

            $prepareData['billing_info']['payment_method'] = trim($_REQUEST['order']['payment_method']);
            $prepareData['billing_info']['last_mile_policy'] = trim($_REQUEST['order']['last_mile_policy']);
            if($_REQUEST['order']['delivery_cost']){
                $prepareData['billing_info']['delivery_cost'] =
                    round($_REQUEST['order']['delivery_cost'], 2)*100;
            }else{
				unset($prepareData['billing_info']['delivery_cost']);
			}
            $prepareData['items'] = array();
            foreach($_REQUEST['product'] as $key=>$product){
                $prepareData['items'][$key] = array();
                $prepareData['items'][$key]['count'] = intval($product['count']);
                $prepareData['items'][$key]['name'] = trim($product['name']);
                $prepareData['items'][$key]['article'] = trim($product['article']);
                $prepareData['items'][$key]['barcode'] = trim($product['barcode']);
                $prepareData['items'][$key]['place_barcode'] = trim($product['place_barcode']);
                $prepareData['items'][$key]['billing_details']['unit_price'] =
                    round($product['unit_price'], 2)*100;
                $prepareData['items'][$key]['billing_details']['assessed_unit_price'] =
                    round($product['assessed_unit_price'], 2)*100;
                $prepareData['items'][$key]['physical_dims']['predefined_volume'] =
                    intval($product['predefined_volume']);
                $prepareData['items'][$key]['physical_dims']['weight_gross'] =
                    intval($product['weight_gross']);
            }

            $prepareData['places'] = array();
            foreach($_REQUEST['places'] as $key=>$place){
                if($place['barcode']){
                    $prepareData['places'][] = array(
                        'barcode'=>trim($place['barcode']),
                        'physical_dims'=>array(
                            'weight_gross'=>intval($place['weight']),
                            'predefined_volume'=>intval($place['pred'])
                        )
                    );
                }
            }

            $prepareData['info']['comment'] = trim($_REQUEST['info']['comment']);
            if($_REQUEST['info']['order_new']){
                $prepareData['info']['operator_request_id'] = trim($_REQUEST['info']['order_new']);
            }
            $prepareData['recipient_info']['phone'] = trim($_REQUEST['info']['phone']);
            $prepareData['recipient_info']['first_name'] = trim($_REQUEST['info']['first_name']);
            if($_REQUEST['info']['email']){
                $prepareData['recipient_info']['email'] = trim($_REQUEST['info']['email']);
            }else{
                unset($prepareData['recipient_info']['email']);
            }
            if($profileAddress){
                if($_REQUEST['info']['address_cord']){
                    $cord = explode(',',$_REQUEST['info']['address_cord']);
                    $prepareData['destination']['custom_location']['latitude'] = doubleval($cord[0]);
                    $prepareData['destination']['custom_location']['longitude'] = doubleval($cord[1]);
                }else{
                    unset($prepareData['destination']['custom_location']['latitude']);
                    unset($prepareData['destination']['custom_location']['longitude']);
                }
                if($_REQUEST['info']['address_kv']){
                    $prepareData['destination']['custom_location']['details']['room'] =
                        trim($_REQUEST['info']['address_kv']);
                }else{
                    unset($prepareData['destination']['custom_location']['details']['room']);
                }
                $prepareData['destination']['custom_location']['details']['full_address'] =
                    trim($_REQUEST['info']['address']);

                if($_REQUEST['info']['date_dost']){
                    $prepareData['destination']['interval'] = array(
                        'from'=>strtotime($_REQUEST['info']['date_dost']),
                        'to'=>strtotime($_REQUEST['info']['date_dost'])+86400,
                    );
                }else{
                    unset($prepareData['destination']['interval']);
                }
            }
            if($profilePvz){
                $prepareData['destination'] = array(
                    'type'=>'platform_station',
                    'platform_station'=>array(
                        'platform_id'=>trim($_REQUEST['info']['pvz'])
                    )
                );
                /*$prepareData['destination']['interval'] = array(
                        'from'=>strtotime(date('d.m.Y'))+86400*4 + 3*60*60,
                        'to'=>strtotime(date('d.m.Y'))+86400*4 + 3*60*60,
                );*/
                /*$prepareData['destination']['interval_utc'] = array(
                        'from'=>'2022-06-22T00:00:00.000000Z',
                        'to'=>'2022-06-22T00:00:00.000000Z',
                );*/
                if($_REQUEST['info']['date_dost']){
                    $prepareData['destination']['interval'] = array(
                        'from'=>strtotime($_REQUEST['info']['date_dost']),
                        'to'=>strtotime($_REQUEST['info']['date_dost']),
                    );
                }else{
                    unset($prepareData['destination']['interval']);
                }
            }

            /*$prepareData['destination']['interval'] = array(
                'from'=>strtotime(date('d.m.Y'))+86400*22 + 10*60*60,
                'to'=>strtotime(date('d.m.Y'))+86400*23 + 18*60*60,
            );*/

            /*$prepareData['destination']['interval'] = array(
                'from'=>strtotime('2022-07-03T09:00:00'),
                'to'=>strtotime('2024-07-30T17:00:00'),
            );*/


            //echo'<pre>';print_r($prepareData);echo'</pre>';

            $api = Helper::getApiByProfileId($curProfile);

            if(isset($prepareData['bx_external']))
                unset($prepareData['bx_external']);

            if(isset($prepareData['destination']['custom_location']['latitude'])){
                unset($prepareData['destination']['custom_location']['details']);
            }

            $offersRes = $api->getOffers($prepareData);

            //echo'<pre>';print_r($offersRes);echo'</pre>';
            /*echo'<pre>';print_r($api->grafik(array(
                    'station_id'=>$prepareData['source']['platform_station']['platform_id'],
                'self_pickup_id'=>$prepareData['destination']['platform_station']['platform_id']
            )));echo'</pre>';*/

            if(!$offersRes->isSuccess()){
                $result->addErrors($offersRes->getErrors());
            }else {
                $offers = $offersRes->getData();
                $autoOffer = Helper::autoOffer($order, $offers);
                if($autoOffer->isSuccess()){
                    $autoOfferData = $autoOffer->getData();
                    $autoOfferId = $autoOfferData['offer_id'];
                }
                $arOffers = $offers['result']['offers'];
            }


            echo'<div class="debug_yandex">';
            echo'<div class="debug_prepareData"><pre>';print_r($prepareData);echo'</pre></div>';
            echo'<div class="debug_offersRes"><pre>';print_r($offersRes);echo'</pre></div>';
            echo'<div class="debug_LastResponse"><pre>';print_r($api->getLastResponse());echo'</pre></div>';
            echo'</div>';
            echo'<a href="#" class="debug_yandex_show">'.Loc::getMessage('AWZ_YDELIVERY_SYSTEM_INFO').'</a>';

            //echo'<pre>';print_r($autoOffer);echo'</pre>';

        }else{
            CAdminMessage::ShowMessage(array('TYPE'=>'ERROR',
                'MESSAGE'=>implode('; ', $prepareResult->getErrorMessages())));
        }


    }

    if(!$result->isSuccess()){
        CAdminMessage::ShowMessage(array(
            'TYPE'=>'ERROR',
            'MESSAGE'=>implode('; ', $result->getErrorMessages())
        ));
    }

    /*$locationCode = $propertyCollection->getDeliveryLocation()->getValue();
    if ($loc = \Bitrix\Sale\Location\LocationTable::getRowById($locationCode)) {
        $locationCode = $loc['CODE'];
    }
    $locationName = '';
    $res = \Bitrix\Sale\Location\LocationTable::getList(array(
        'filter' => array(
            '=CODE' => $locationCode,
            '=PARENTS.NAME.LANGUAGE_ID' => LANGUAGE_ID,
            '=PARENTS.TYPE.NAME.LANGUAGE_ID' => LANGUAGE_ID,
        ),
        'select' => array(
            'I_ID' => 'PARENTS.ID',
            'I_NAME_LANG' => 'PARENTS.NAME.NAME',
            'I_TYPE_CODE' => 'PARENTS.TYPE.CODE',
            'I_TYPE_NAME_LANG' => 'PARENTS.TYPE.NAME.NAME',
        ),
        'order' => array(
            'PARENTS.DEPTH_LEVEL' => 'asc'
        )
    ));
    while($item = $res->fetch())
    {
        if($locationName){
            $locationName .= ', '.$item['I_NAME_LANG'];
        }else{
            $locationName = $item['I_NAME_LANG'];
        }
    }
    if(!$locationName){
        $result->addError(
                new Error(
                        Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ERR_LOCATION')
                )
        );
        //return $result;
    }
    */

    /* @var \Bitrix\Sale\EntityPropertyValue $prop*/
    $adressProp = $propertyCollection->getAddress();

    $prepareAutoResult = OffersTable::getPrepare($order, true);
    $items = array();
    $prepareAutoData = array();
    $locationName = '';
    if($prepareAutoResult->isSuccess()){
        $prepareAutoData = $prepareAutoResult->getData();
        $items = $prepareAutoData['items'];
        $locationName = $prepareAutoData['bx_external']['location_name'];
        //echo'<pre>';print_r($prepareAutoData);echo'</pre>';
    }else{
        CAdminMessage::ShowMessage(array(
            'TYPE'=>'ERROR',
            'MESSAGE'=>implode('; ', $prepareAutoResult->getErrorMessages())
        ));
        $items = Helper::getItems($order);
    }


    //echo'<pre>';print_r($_REQUEST);echo'</pre>';
    //echo'<pre>';print_r($arOffers);echo'</pre>';

    ?>
<style>
    .debug_prepareData, .debug_offersRes, .debug_LastResponse {display:block;border:1px solid #000000;padding:10px;margin:10px;}
    .debug_yandex {display:none;}
</style>
    <script>
        $(document).on('click','.debug_yandex_show',function(e){
            e.preventDefault();
            if($(this).hasClass('active')){
                $('.debug_yandex').hide();
                $(this).html('<?=Loc::getMessage('AWZ_YDELIVERY_SYSTEM_INFO')?>');
                $(this).removeClass('active');
            }else{
                $('.debug_yandex').show();
                $(this).html('<?=Loc::getMessage('AWZ_YDELIVERY_SYSTEM_HIDE')?>');
                $(this).addClass('active');
            }
        });
    </script>
    <?if(!empty($arOffers)){?>

    <div class="adm-list-table-layout">
        <div class="adm-list-table-top">
            <b style="font-size:18px;line-height:30px;"><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST')?></b>
        </div>

        <div class="adm-list-table adm-list-table-without-header">
            <div class="adm-detail-content-wrap">

                <div class="adm-detail-content">
                    <div class="adm-list-table-wrap">
                        <table class="awz-yandex-items adm-list-table">
                            <tr class="adm-list-table-header">
                                <th class="adm-list-table-cell">
                                    <div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST_TH1')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell">
                                    <div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST_TH2')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell">
                                    <div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST_TH3')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell">
                                    <div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST_TH4')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell">
                                    <div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST_TH5')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell">
                                    <div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST_TH6')?>
                                    </div>
                                </th>
                            </tr>
                            <?foreach($arOffers as $offer){?>
                                <?
                                $activeClass = '';
                                if($autoOfferId == $offer['offer_id']){
                                    $activeClass = ' adm-list-row-active';
                                }
                                ?>
                            <tr class="adm-list-table-row<?=$activeClass?>">
                                <td class="adm-list-table-cell">
                                    <?$date = \Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime($offer['expires_at']));
                                    ?><?=$date->toString()?>
                                </td>
                                <td class="adm-list-table-cell">
                                    <?=$offer['offer_details']['pricing_total']?>
                                </td>
                                <td class="adm-list-table-cell">
                                    <?=$offer['offer_details']['pricing']?>
                                </td>
                                <td class="adm-list-table-cell">
                                    <?
                                    $dateFrom = \Bitrix\Main\Type\DateTime::createFromTimestamp(
                                            strtotime($offer['offer_details']['delivery_interval']['min'])
                                    );
                                    $dateTo = \Bitrix\Main\Type\DateTime::createFromTimestamp(
                                            strtotime($offer['offer_details']['delivery_interval']['max'])
                                    );
                                    ?>
                                    <?=$dateFrom->toString()?> - <?=$dateTo->toString()?>
                                </td>
                                <td class="adm-list-table-cell">
                                    <?
                                    $dateFrom = \Bitrix\Main\Type\DateTime::createFromTimestamp(
                                            strtotime($offer['offer_details']['pickup_interval']['min'])
                                    );
                                    $dateTo = \Bitrix\Main\Type\DateTime::createFromTimestamp(
                                            strtotime($offer['offer_details']['pickup_interval']['max'])
                                    );
                                    ?>
                                    <?=$dateFrom->toString()?> - <?=$dateTo->toString()?>
                                </td>
                                <td class="adm-list-table-cell">
                                    <form method="post">
                                        <input type="hidden" name="action" value="send_offer">
                                        <input type="hidden" name="offer" value="<?=$offer['offer_id']?>">
                                        <input type="hidden" name="IFRAME_TYPE" value="<?=$_REQUEST['IFRAME_TYPE']?>">
                                        <input type="hidden" name="IFRAME" value="<?=$_REQUEST['IFRAME']?>">
                                        <input type="submit" class="adm-btn-save" value="<?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST_TH6')?>">
                                    </form>
                                </td>
                            </tr>
                            <?}?>
                        </table>
                    </div>
                </div>

                <br>
                <div class="adm-detail-content">
                    <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST_COST_DOST')?></b>:
                    <?=$order->getDeliveryPrice()?> <?=$order->getCurrency()?>
                </div>
                <br>

            </div>
        </div>

    </div>

    <?php }?>
    <form method="post">
        <div class="adm-list-table-layout">
            <div class="adm-list-table-top">
                <b style="font-size:18px;line-height:30px;"><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_CREATE')?></b>
            </div>

            <div class="adm-list-table adm-list-table-without-header">
                <div class="adm-detail-content-wrap">

                    <div class="adm-detail-content">
                        <div class="adm-detail-title">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_CREATE_DESC')?>
                        </div>
                        <div class="adm-list-table-wrap">
                            <table class="awz-yandex-items adm-list-table">
                                <tr class="adm-list-table-header">
                                    <th class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH1')?>
                                        </div>
                                    </th>
                                    <th class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH2')?>
                                        </div>
                                    </th>
                                    <th class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH3')?>
                                        </div>
                                    </th>
                                    <th class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH4')?>
                                        </div>
                                    </th>
                                    <th class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH5')?>
                                        </div>
                                    </th>
                                </tr>
                                <?foreach($items as $key=>$item){?>
                                    <tr class="adm-list-table-row">
                                        <td class="adm-list-table-cell">
                                            <?
                                            $val = $item['name'];
                                            if(isset($_REQUEST['product'][$key]['name']))
                                                $val = htmlspecialcharsEx(trim($_REQUEST['product'][$key]['name']));
                                            ?>
                                            <input type="text" name="product[<?=$key?>][name]" value="<?=$val?>"><br>
                                            <?
                                            $val = $item['count'];
                                            if(isset($_REQUEST['product'][$key]['count']))
                                                $val = intval($_REQUEST['product'][$key]['count']);
                                            ?>
                                            <input type="text" name="product[<?=$key?>][count]" value="<?=$val?>">
                                        </td>
                                        <td class="adm-list-table-cell">
                                            <?
                                            $val = $item['barcode'];
                                            if(isset($_REQUEST['product'][$key]['barcode']))
                                                $val = htmlspecialcharsEx(trim($_REQUEST['product'][$key]['barcode']));
                                            ?>
                                            <input type="text" name="product[<?=$key?>][barcode]" value="<?=$val?>"><br>
                                            <?
                                            $val = $item['place_barcode'];
                                            if(isset($_REQUEST['product'][$key]['place_barcode']))
                                                $val = htmlspecialcharsEx(trim($_REQUEST['product'][$key]['place_barcode']));
                                            ?>
                                            <input type="text" name="product[<?=$key?>][place_barcode]" value="<?=$val?>">
                                        </td>
                                        <td class="adm-list-table-cell">
                                            <?
                                            $val = $item['article'];
                                            if(isset($_REQUEST['product'][$key]['article']))
                                                $val = htmlspecialcharsEx(trim($_REQUEST['product'][$key]['article']));
                                            ?>
                                            <input type="text" name="product[<?=$key?>][article]" value="<?=$val?>">
                                            <?
                                            $val = $item['marking_code'];
                                            if(isset($_REQUEST['product'][$key]['marking_code']))
                                                $val = htmlspecialcharsEx(trim($_REQUEST['product'][$key]['marking_code']));
                                            ?>
                                            <br><input type="text" name="product[<?=$key?>][marking_code]" value="<?=$val?>">
                                        </td>
                                        <td class="adm-list-table-cell">
                                            <?
                                            $val = $item['billing_details']['unit_price']/100;
                                            if(isset($_REQUEST['product'][$key]['unit_price']))
                                                $val = round($_REQUEST['product'][$key]['unit_price'], 2);
                                            ?>
                                            <input type="text" name="product[<?=$key?>][unit_price]" value="<?=$val?>"><br>
                                            <?
                                            $val = $item['billing_details']['assessed_unit_price']/100;
                                            if(isset($_REQUEST['product'][$key]['assessed_unit_price']))
                                                $val = round($_REQUEST['product'][$key]['assessed_unit_price'], 2);
                                            ?>
                                            <input type="text" name="product[<?=$key?>][assessed_unit_price]" value="<?=$val?>">
                                        </td>
                                        <td class="adm-list-table-cell">
                                            <?
                                            $val = $item['physical_dims']['weight_gross'];
                                            if(isset($_REQUEST['product'][$key]['weight_gross']))
                                                $val = intval($_REQUEST['product'][$key]['weight_gross']);
                                            ?>
                                            <input type="text" name="product[<?=$key?>][weight_gross]" value="<?=$val?>"><br>
                                            <?
                                            $val = $item['physical_dims']['predefined_volume'];
                                            if(isset($_REQUEST['product'][$key]['predefined_volume']))
                                                $val = intval($_REQUEST['product'][$key]['predefined_volume']);
                                            ?>
                                            <input type="text" name="product[<?=$key?>][predefined_volume]" value="<?=$val?>">
                                        </td>
                                    </tr>
                                <?}?>
                            </table>
                        </div>



                    </div>


                    <?
                    $allWeight = 0;
                    $allPredefined = 0;
                    foreach($items as $item){
                        $allWeight += $item['count']*$item['physical_dims']['weight_gross'];
                        $allPredefined += $item['count']*$item['physical_dims']['predefined_volume'];
                    }
                    //$allPredefined = $allPredefined * 1.5;
                    ?>

                    <div class="adm-detail-content">
                        <div class="adm-detail-title"><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PLACE_TITLE')?></div>
                        <div class="adm-list-table-wrap">
                            <table class="awz-yandex-items adm-list-table">
                                <tr class="adm-list-table-header">
                                    <th class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PLACE_TH1')?>
                                        </div>
                                    </th>
                                    <th class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PLACE_TH2')?>
                                        </div>
                                    </th>
                                    <th class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PLACE_TH3')?>
                                        </div>
                                    </th>
                                </tr>
                                <?foreach(array(0,1,2) as $key){?>
                                    <tr class="adm-list-table-row">
                                        <td class="adm-list-table-cell">
                                            <?$val = $prepareAutoData['places'][0]['barcode'];
                                            if($key != 0) $val = '';
                                            if(isset($_REQUEST['places'][$key]['barcode'])) $val = htmlspecialcharsEx(trim($_REQUEST['places'][$key]['barcode']));
                                            ?>
                                            <input type="text" name="places[<?=$key?>][barcode]" value="<?=$val?>">
                                        </td>
                                        <td class="adm-list-table-cell">
                                            <?$val = $allWeight;
                                            if($key != 0) $val = '';
                                            if(isset($_REQUEST['places'][$key]['weight'])) $val = intval($_REQUEST['places'][$key]['weight']);
                                            ?>
                                            <input type="text" name="places[<?=$key?>][weight]" value="<?=$val?>">
                                        </td>
                                        <td class="adm-list-table-cell">
                                            <?$val = $allPredefined;
                                            if($key != 0) $val = '';
                                            if(isset($_REQUEST['places'][$key]['pred'])) $val = intval($_REQUEST['places'][$key]['pred']);
                                            ?>
                                            <input type="text" name="places[<?=$key?>][pred]" value="<?=$val?>">
                                        </td>
                                    </tr>
                                <?}?>
                            </table>
                        </div>



                    </div>


                    <div class="adm-detail-content">
                        <div class="adm-detail-title">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL')?>
                        </div>
                        <div class="adm-list-table-wrap">
                            <table class="adm-detail-content-table">
                                <tbody>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_NUM')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = $order->getId();
                                        ?>
                                        <input type="text" name="info[order]" value="<?=$val?>" readonly="readonly">
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_NUM_REPL')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = '';
                                        if(isset($prepareAutoData['info']['operator_request_id']))
                                            $val = $prepareAutoData['info']['operator_request_id'];
                                        if(isset($_REQUEST['info']['order_new'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['order_new']));
                                        ?>
                                        <input type="text" name="info[order_new]" value="<?=$val?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_LOC')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = $locationName;
                                        ?>
                                        <?=$val?>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_CM')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = '';
                                        if(isset($prepareAutoData['info']['comment']))
                                            $val = $prepareAutoData['info']['comment'];
                                        /*$userComment = $order->getField('USER_DESCRIPTION');
                                        if($userComment){
                                            $val .= '; '.$userComment;
                                        }*/
                                        if(isset($_REQUEST['info']['comment'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['comment']));
                                        ?>
                                        <textarea cols="30" rows="5" name="info[comment]"><?=$val?></textarea>
                                    </td>
                                </tr>
                                <?if($profileAddress){?>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_ADDR_CORD')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = '';
                                        if(isset($prepareAutoData['destination']['custom_location']['latitude']))
                                            $val = $prepareAutoData['destination']['custom_location']['latitude'].','.$prepareAutoData['destination']['custom_location']['longitude'];
                                        if(isset($_REQUEST['info']['address_cord'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['address_cord']));
                                        ?>
                                        <input type="text" cols="30" rows="5" name="info[address_cord]" value="<?=$val?>"><br>
                                        <span style="color:red;">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_ADDR_CORD_DESC')?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_ADDR')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = '';
                                        if(isset($prepareAutoData['destination']['custom_location']['details']['full_address']))
                                            $val = $prepareAutoData['destination']['custom_location']['details']['full_address'];
                                        if(isset($_REQUEST['info']['address'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['address']));
                                        ?>
                                        <textarea cols="30" rows="5" name="info[address]"><?=$val?></textarea>
                                    </td>
                                </tr>

                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_ADDR2')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = '';
                                        if(isset($prepareAutoData['destination']['custom_location']['details']['room']))
                                            $val = $prepareAutoData['destination']['custom_location']['details']['room'];
                                        if(isset($_REQUEST['info']['address_kv'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['address_kv']));
                                        ?>
                                        <input type="text" name="info[address_kv]" value="<?=$val?>">
                                    </td>
                                </tr>
                                <?}else{?>
                                    <tr>
                                        <td width="50%" class="adm-detail-content-cell-l">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_ADDR3')?>
                                        </td>
                                        <td class="adm-detail-content-cell-r">
                                            <?
                                            $val = '';
                                            if(isset($prepareAutoData['destination']['platform_station']['platform_id']))
                                                $val = $prepareAutoData['destination']['platform_station']['platform_id'];
                                            if(isset($_REQUEST['info']['pvz'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['pvz']));
                                            ?>
                                            <input type="text" id="info-pvz-field" name="info[pvz]" value="<?=$val?>">
                                            <a onclick="BX.SidePanel.Instance.open('/bitrix/admin/awz_ydelivery_picpoint_list.php?LANG=ru&page=order_edit&order=<?=intval($_REQUEST['ORDER_ID'])?>',{cacheable: false});return false;" href="#">
                                                <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_BTN')?>
                                            </a>
                                            <div class="awz-yd-bln-wrap-admin-detail">
                                            <?
                                            $resPost = Helper::getBaloonHtml($val, true);
                                            if($resPost->isSuccess()){
                                                $dataPost = $resPost->getData();
                                                echo $dataPost['html'];
                                            }else{
                                                echo '<p style="color:red;">'.implode('; ',$resPost->getErrorMessages()).'</p>';
                                            }
                                            ?>
                                            </div>
                                            <?
                                            $signer = new \Bitrix\Main\Security\Sign\Signer();
                                            $signedParameters = $signer->sign(base64_encode(serialize(array(
                                                'address'=>$locationName,
                                                'geo_id'=>'',
                                                'profile_id'=>$curProfile,
                                                's_id'=>bitrix_sessid(),
                                                'page'=>'pvz-edit'
                                            ))));
                                            ?>
                                            <script>
                                                $(document).ready(function(){
                                                    $('#info-pvz-field').on('change',function(){
                                                        var id = $(this).val();
                                                        window.awz_yd_modal.loadBaloonAjax(
                                                            false,
                                                            '<?=$signedParameters?>',
                                                            $('.awz-yd-bln-wrap-admin-detail'),
                                                            id
                                                        );
                                                    });
                                                });
                                            </script>
                                        </td>
                                    </tr>
                                <?}?>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_DATE')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = '';
                                        if(isset($prepareAutoData['bx_external']['delivery_date']))
                                            $val = $prepareAutoData['bx_external']['delivery_date'];
                                        if(isset($_REQUEST['info']['date_dost'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['date_dost']));
                                        ?>
                                        <?=\CAdminCalendar::CalendarDate('info[date_dost]', $val)?>

                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_NAME')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = '';
                                        if(isset($prepareAutoData['recipient_info']['first_name']))
                                            $val = $prepareAutoData['recipient_info']['first_name'];
                                        if(isset($_REQUEST['info']['first_name'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['first_name']));
                                        ?>
                                        <input type="text" name="info[first_name]" value="<?=$val?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_PHONE')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = '';
                                        if(isset($prepareAutoData['recipient_info']['phone']))
                                            $val = $prepareAutoData['recipient_info']['phone'];
                                        if(isset($_REQUEST['info']['phone'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['phone']));
                                        ?>
                                        <input type="text" name="info[phone]" value="<?=$val?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" class="adm-detail-content-cell-l">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_EMAIL')?>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <?
                                        $val = '';
                                        if(isset($prepareAutoData['recipient_info']['email']))
                                            $val = $prepareAutoData['recipient_info']['email'];
                                        if(isset($_REQUEST['info']['email'])) $val = htmlspecialcharsEx(trim($_REQUEST['info']['email']));
                                        ?>
                                        <input type="text" name="info[email]" value="<?=$val?>">
                                    </td>
                                </tr>

                                </tbody>
                            </table>
                        </div>



                    </div>


                    <div class="adm-detail-content">
                        <div class="adm-detail-title">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PAY_TITLE')?>
                        </div>
                        <div class="adm-list-table-wrap">
                            <table class="adm-detail-content-table">
                                <tbody>
                                    <tr>
                                        <td width="50%" class="adm-detail-content-cell-l">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PAY_DOST_SUMM')?>
                                        </td>
                                        <td class="adm-detail-content-cell-r">
                                            <?
                                            $val = '';
                                            if(isset($prepareAutoData['billing_info']['delivery_cost']))
                                                $val = $prepareAutoData['billing_info']['delivery_cost']/100;
                                            if(isset($_REQUEST['order']['delivery_cost']))
                                                $val = round($_REQUEST['order']['delivery_cost'], 2);
                                            ?>
                                            <input type="text" name="order[delivery_cost]" value="<?=$val?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="50%" class="adm-detail-content-cell-l">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PAY_TITLE2')?>
                                        </td>
                                        <td class="adm-detail-content-cell-r">
                                            <?
                                            $valAr = array();
                                            $valAr = Helper::getYandexPaymentIdFromOrder($order, $curProfile);
                                            /*if(!empty($valAr)){
                                                $val = $valAr[0];
                                            }*/
                                            $val = '';
                                            if(isset($prepareAutoData['billing_info']['payment_method']))
                                                $val = $prepareAutoData['billing_info']['payment_method'];
                                            $methods = Helper::getYandexPayMethods();

                                            if(isset($_REQUEST['order']['payment_method']))
                                                $val = htmlspecialcharsEx(trim($_REQUEST['order']['payment_method']));

                                            ?>
                                            <?if(count($valAr)>1){?>
                                                <p style="color:red;">
                                                    <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PAY_ERR_DLIST')?>
                                                </p>
                                            <?}?>
                                            <select name="order[payment_method]">
                                                <?foreach($methods as $code=>$name){
                                                    $selected = '';
                                                    if($code == $val) $selected = ' selected="selected"';

                                                    ?>
                                                    <option value="<?=$code?>"<?=$selected?>><?=$name?></option>
                                                <?}?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="50%" class="adm-detail-content-cell-l">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_MILE_POLISY')?>
                                        </td>
                                        <td class="adm-detail-content-cell-r">
                                            <?
                                            $val = '';
                                            if($profileAddress){
                                                $methods = Helper::getYandexLastMilePolicy(Helper::DOST_TYPE_ADR);
                                            }else{
                                                $methods = Helper::getYandexLastMilePolicy(Helper::DOST_TYPE_PVZ);
                                            }
                                            if(isset($prepareAutoData['last_mile_policy']))
                                                $val = $prepareAutoData['last_mile_policy'];
                                            if(isset($_REQUEST['order']['last_mile_policy'])) $val = htmlspecialcharsEx(trim($_REQUEST['order']['last_mile_policy']));

                                            ?>
                                            <select name="order[last_mile_policy]">
                                                <?foreach($methods as $code=>$name){
                                                    $selected = '';
                                                    if($code == $val) $selected = ' selected="selected"';

                                                    ?>
                                                    <option value="<?=$code?>"<?=$selected?>><?=$name?></option>
                                                <?}?>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <br>
                    <br>
                    <input type="hidden" name="action" value="get_offers">
                    <input type="hidden" name="IFRAME_TYPE" value="<?=$_REQUEST['IFRAME_TYPE']?>">
                    <input type="hidden" name="IFRAME" value="<?=$_REQUEST['IFRAME']?>">
                    <input type="submit" class="adm-btn-active" value="<?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PAY_OFFER_BTN')?>">


                </div>


            </div>

        </div>

    </form>
    <?php

}else{

    $yandexRes = null;

    $data = OffersTable::getList(array(
        'select'=>array('*'),
        'filter'=>array('ID'=>$ID)
    ))->fetch();

    //echo'<pre>';print_r($data);echo'</pre>';

    if($_REQUEST['DELETE'] == 'Y'){
        if($data['OFFER_ID'] == $_REQUEST['OFFER_ID_CONFIRM']){

            if($_REQUEST['DELETE_AFTER_RESP'] == 'Y'){

                $resultCansel = \Awz\Ydelivery\OffersTable::canselOffer($data['ID']);
                if($resultCansel->isSuccess()){
                    $dataCansel = $resultCansel->getData();
                    CAdminMessage::ShowMessage(array('TYPE'=>'OK', 'MESSAGE'=>$dataCansel['result']['description']));
                    OffersTable::delete(array('ID'=>$data['ID']));
                    $data = null;
                }else{
                    //array("MESSAGE"=>"", "TYPE"=>("ERROR"|"OK"|"PROGRESS"), "DETAILS"=>"", "HTML"=>true)
                    CAdminMessage::ShowMessage(array(
                            'TYPE'=>'ERROR',
                        'MESSAGE'=>implode("; ",$resultCansel->getErrorMessages())
                    ));
                }
            }else{
                OffersTable::delete(array('ID'=>$data['ID']));
                $data = null;
            }

        }else{
            CAdminMessage::ShowMessage(array(
                'TYPE'=>'ERROR',
                'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ERR_NUM')
            ));
        }
    }elseif($_REQUEST['CANSEL'] == 'Y'){
        if($data['OFFER_ID'] == $_REQUEST['OFFER_ID_CONFIRM']){
            $resultCansel = \Awz\Ydelivery\OffersTable::canselOffer($data['ID']);
            if($resultCansel->isSuccess()){
                $dataCansel = $resultCansel->getData();
                CAdminMessage::ShowMessage(array('TYPE'=>'OK', 'MESSAGE'=>$dataCansel['result']['description']));
            }else{
                CAdminMessage::ShowMessage(array(
                        'TYPE'=>'ERROR',
                    'MESSAGE'=>implode("; ",$resultCansel->getErrorMessages())
                ));
            }
        }else{
            CAdminMessage::ShowMessage(array(
                'TYPE'=>'ERROR',
                'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ERR_NUM')
            ));
        }
    }elseif($_REQUEST['GET_EXTERNAL_INVOICE'] == 'Y' && $data){
        //order_invoice_file

        $config = Helper::getConfigFromOrderId($data['ORDER_ID']);
        $api = Helper::getApiFromConfig($config);
        $resInvoice = $api->getInvoice(array(
                'request_id'=>array($data['OFFER_ID'])
            )
        );
        if($resInvoice->isSuccess()){

            $invoiceData = $resInvoice->getData();
            $fileContent = $invoiceData['result'];
            $tmpName = time().'-invoice-'.\Bitrix\Main\Security\Random::getString(20).'.pdf';
            $fileOb = new \Bitrix\Main\IO\File(\Bitrix\Main\Application::getDocumentRoot() . "/upload/tmp/".$tmpName);
            $fileOb->putContents($fileContent);
            $makeFile = \CFile::MakeFileArray($fileOb->getPath());
            $makeFile['MODULE_ID'] = $module_id;
            $fileId = \CFile::SaveFile($makeFile, "ydelivery");
            if(intval($fileId)>0){
                if(!is_array($data['HISTORY'])) $data['HISTORY'] = array();
                if($data['HISTORY']['order_invoice_file']){
                    \CFile::Delete($data['HISTORY']['order_invoice_file']);
                }
                $data['HISTORY']['order_invoice_file'] = $fileId;
                OffersTable::update(array('ID'=>$data['ID']), array('HISTORY'=>$data['HISTORY']));
            }
            $fileOb->delete();

        }else{
            CAdminMessage::ShowMessage(array(
                'TYPE'=>'ERROR',
                'MESSAGE'=>implode('; ', $resInvoice->getErrorMessages())
            ));
        }

    }elseif($_REQUEST['GET_EXTERNAL_LABEL'] == 'Y' && $data){

        $config = Helper::getConfigFromOrderId($data['ORDER_ID']);
        $api = Helper::getApiFromConfig($config);
        $labelType = $_REQUEST['GET_EXTERNAL_LABEL_TYPE'] == 'one' ? 'one': 'many';
        Option::set($module_id, 'label_type', $labelType, '');
        $resLabel = $api->getLabels(array(
            'generate_type'=>$labelType,
            'request_ids'=>array($data['OFFER_ID'])
            )
        );
        if($resLabel->isSuccess()){

            $labelData = $resLabel->getData();
            $fileContent = $labelData['result'];
            $tmpName = time().'-label-'.\Bitrix\Main\Security\Random::getString(20).'.pdf';
            $fileOb = new \Bitrix\Main\IO\File(\Bitrix\Main\Application::getDocumentRoot() . "/upload/tmp/".$tmpName);
            $fileOb->putContents($fileContent);
            $makeFile = \CFile::MakeFileArray($fileOb->getPath());
            $makeFile['MODULE_ID'] = $module_id;
            $fileId = \CFile::SaveFile($makeFile, "ydelivery");
            if(intval($fileId)>0){
                if(!is_array($data['HISTORY'])) $data['HISTORY'] = array();
                if($data['HISTORY']['order_label_file']){
                    \CFile::Delete($data['HISTORY']['order_label_file']);
                }
                $data['HISTORY']['order_label_file'] = $fileId;
                OffersTable::update(array('ID'=>$data['ID']), array('HISTORY'=>$data['HISTORY']));
            }
            $fileOb->delete();

        }else{
            CAdminMessage::ShowMessage(array(
                'TYPE'=>'ERROR',
                'MESSAGE'=>implode('; ', $resLabel->getErrorMessages())
            ));
        }
    }

    if(!$data){
        $yandexRes = new \Bitrix\Main\Result();
        $yandexRes->addError(
                new \Bitrix\Main\Error(
                        Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ERR_ID')
                )
        );
    }

    if(!$yandexRes){
        $config = Helper::getConfigFromOrderId($data['ORDER_ID']);
        $api = Helper::getApiFromConfig($config);

        if($_REQUEST['UPDATE_EXTERNAL_DATA']!='Y' && !empty($data['HISTORY']['last'])){
            $yandexRes = new \Bitrix\Main\Result();
            $yandexRes->setData($data['HISTORY']['last']);
        }else{
            $yandexRes = $api->offerInfo($data['OFFER_ID']);
        }
    }

    if($yandexRes->isSuccess()){

        $order = \Bitrix\Sale\Order::load($data['ORDER_ID']);
        $curProfile = Helper::getProfileId($order);
        $val_stat_all = Option::get($module_id, "CHECKER_FIN_".$curProfile, "", '');
        $val_stat_all = unserialize($val_stat_all);
        if(!is_array($val_stat_all)) $val_stat_all = array();

        $config = Helper::getConfigByProfileId($curProfile);

        $yandexData = $yandexRes->getData();
        $data['HISTORY']['last'] = $yandexData;
        if(!$data['HISTORY']['last_history_date']) {
            $data['HISTORY']['last_history_time'] = time();
        }
        OffersTable::update(array('ID'=>$ID), array('HISTORY'=>$data['HISTORY']));
        ?>
        <div class="adm-list-table-layout">

            <div class="adm-list-table-top">
                <?if(isset($config['MAIN']['TEST_MODE']) && $config['MAIN']['TEST_MODE']=='Y'){?>
                    <b style="color:red;font-weight:bold;">TEST</b>
                <?}?>
                <b style="font-size:18px;line-height:30px;"><?=Loc::getMessage("AWZ_YDELIVERY_ZAAVKA")?><?=$yandexData['result']['request_id']?></b>
            </div>
        <div class="adm-list-table adm-list-table-without-header">
            <div class="adm-detail-content-wrap">
                <div class="adm-detail-content">
                    <table style="width:100%;">
                        <tr>
                            <td>
                                <?if($data['HISTORY']['last_history_time']){?>
                                    <p><b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_UP_LABEL')?></b>:
                                        <?=\Bitrix\Main\Type\DateTime::createFromTimestamp($data['HISTORY']['last_history_time'])->toString()?></p>
                                <?}?>


                                <form method="post">
                                    <input type="hidden" name="UPDATE_EXTERNAL_DATA" value="Y">
                                    <input type="hidden" name="IFRAME_TYPE" value="<?=$_REQUEST['IFRAME_TYPE']?>">
                                    <input type="hidden" name="IFRAME" value="<?=$_REQUEST['IFRAME']?>">
                                    <input type="submit" class="adm-btn-active" value="<?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_BTN_UPDATE')?>">
                                </form>
                            </td>
                            <td>
                                <p><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_LABEL')?>:
                                    <?if($data['HISTORY']['order_label_file']){?>
                                    <a href="<?=CFile::GetPath($data['HISTORY']['order_label_file'])?>" target="blank">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_LABEL_DOWNLOAD')?>
                                    </a>
                                <?}else{?>
                                    <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_LABEL_NO_CREATE')?>
                                <?}?>
                                </p>

                                <form method="post">
                                    <input type="hidden" name="GET_EXTERNAL_LABEL" value="Y">
                                    <input type="hidden" name="IFRAME_TYPE" value="<?=$_REQUEST['IFRAME_TYPE']?>">
                                    <input type="hidden" name="IFRAME" value="<?=$_REQUEST['IFRAME']?>">
                                    <?$def = Option::get($module_id, 'label_type', 'one', '');?>
                                    <select name="GET_EXTERNAL_LABEL_TYPE">
                                        <option value="one"<?=($def == 'one') ? ' selected="selected"' : ''?>><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_LABEL')?> v1</option>
                                        <option value="many"<?=($def == 'many') ? ' selected="selected"' : ''?>><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_LABEL')?> v2</option>
                                    </select>
                                    <input type="submit" class="adm-btn-active" value="<?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_BTN_LABEL')?>">
                                </form>
                            </td>
                            <td>
                                <p><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_INVOICE')?>:
                                    <?if($data['HISTORY']['order_invoice_file']){?>
                                        <a href="<?=CFile::GetPath($data['HISTORY']['order_invoice_file'])?>" target="blank">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_LABEL_DOWNLOAD')?>
                                        </a>
                                    <?}else{?>
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_LABEL_NO_CREATE')?>
                                    <?}?>
                                </p>

                                <form method="post">
                                    <input type="hidden" name="GET_EXTERNAL_INVOICE" value="Y">
                                    <input type="hidden" name="IFRAME_TYPE" value="<?=$_REQUEST['IFRAME_TYPE']?>">
                                    <input type="hidden" name="IFRAME" value="<?=$_REQUEST['IFRAME']?>">
                                    <input type="submit" class="adm-btn-active" value="<?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_BTN_INVOICE')?>">
                                </form>
                            </td>
                        </tr>
                    </table>

                    <br>
                </div>
            </div>
        </div>
        <div class="adm-list-table adm-list-table-without-header">
        <div class="adm-detail-content-wrap">
            <div class="adm-detail-content">
                <p>
                    <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_STAT_ID')?>: </b>
                    <?=$data['HISTORY']['last']['result']['courier_order_id']?>
                </p>
                <?if(isset($data['HISTORY']['last']['result']['state']['description'])){?>
                    <p><b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_STAT_LABEL')?></b>:
                        <?=$data['HISTORY']['last']['result']['state']['description']?></p>
                <?}?><p>
                    <?if(isset($data['HISTORY']['last']['result']['state']['status'])){?>
                        <b><?=$data['HISTORY']['last']['result']['state']['status']?></b>
                    <?}?>
                    <?if(isset($data['HISTORY']['last']['result']['state']['timestamp']) &&
                        strtotime($data['HISTORY']['last']['result']['state']['timestamp'])>100000)
                    {?>
                        [<?=\Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime($data['HISTORY']['last']['result']['state']['timestamp']))->toString()?>]
                    <?}?>
                </p>
            </div>
        </div>
        </div>
            <div class="adm-list-table adm-list-table-without-header">
                <div class="adm-detail-content-wrap">

                    <div class="adm-detail-content">
                        <div class="adm-detail-title">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_CREATE_DESC')?>
                        </div>
                        <div class="adm-list-table-wrap">
                        <table class="awz-yandex-items adm-list-table">
                            <tr class="adm-list-table-header">
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PLACE_TH1')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH3_MIN')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH1_MIN')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH1_MIN2')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH4_MIN')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH4_MIN2')?>
                                    </div>
                                </th>
                            </tr>
                            <?foreach($yandexData['result']['request']['items'] as $item){?>
                            <tr class="adm-list-table-row">
                                <td class="adm-list-table-cell"><?=$item['barcode']?></td>
                                <td class="adm-list-table-cell"><?=$item['article']?></td>
                                <td class="adm-list-table-cell"><?=$item['name']?></td>
                                <td class="adm-list-table-cell"><?=$item['count']?></td>
                                <td class="adm-list-table-cell">
                                    <?=($item['billing_details']['unit_price']/100)?> <?=$item['billing_details']['currency']?>
                                </td>
                                <td class="adm-list-table-cell">
                                    <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH5_MIN')?>:
                                    <?=$item['physical_dims']['predefined_volume']?>
                                    (<?=$item['physical_dims']['dz']?>x<?=$item['physical_dims']['dy']?>x<?=$item['physical_dims']['dx']?>)
                                    <br>
                                    <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH5_MIN2')?>:
                                    <?=$item['physical_dims']['weight_gross']?>
                                </td>
                            </tr>
                            <?}?>
                        </table>
                        </div>
                    </div>

                    <div class="adm-detail-content">
                        <div class="adm-detail-title">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PLACE_TITLE')?>
                        </div>
                            <div class="adm-list-table-wrap">
                            <table class="awz-yandex-items adm-list-table">
                                <tr class="adm-list-table-header">
                                    <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PLACE_TH1')?>
                                        </div></th>
                                    <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH4_MIN2')?>
                                        </div></th>
                                </tr>

                                <?foreach($yandexData['result']['request']['places'] as $item){?>
                                    <tr class="adm-list-table-row">
                                        <td class="adm-list-table-cell"><?=$item['barcode']?></td>
                                        <td class="adm-list-table-cell">
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH5_MIN')?>:
                                            <?=$item['physical_dims']['predefined_volume']?>
                                            (<?=$item['physical_dims']['dz']?>x<?=$item['physical_dims']['dy']?>x<?=$item['physical_dims']['dx']?>)
                                            <br>
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_SOSTAV_TH5_MIN2')?>:
                                            <?=$item['physical_dims']['weight_gross']?>
                                        </td>
                                    </tr>
                                <?}?>
                            </table>
                        </div>
                    </div>


                    <div class="adm-detail-content">
                        <div class="adm-detail-title">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_INF_TITLE')?>
                        </div>
                        <div class="adm-detail-content-item-block">
                            <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_INF_ID')?>:</b>
                            <?=$yandexData['result']['request']['info']['operator_request_id']?>
                            <?$orderLink = '';?>
                            <a href="#" onclick="BX.SidePanel.Instance.open('/bitrix/admin/sale_order_view.php?lang=<?=LANGUAGE_ID?>&ID=<?=$data['ORDER_ID']?>',{cacheable: false});return false;">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_BTN_ORDER_LINK')?>
                            </a><br><br>
                            <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_CM')?>:</b>
                            <?=$yandexData['result']['request']['info']['comment']?><br><br>
                            <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_PHONE')?>:</b>
                            <?=$yandexData['result']['request']['recipient_info']['phone']?><br><br>
                            <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_NAME')?>:</b>
                            <?=$yandexData['result']['request']['recipient_info']['first_name']?><br><br>
                            <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_POL_EMAIL')?>:</b>
                            <?=$yandexData['result']['request']['recipient_info']['email']?><br><br>
                            <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_PAY_TITLE')?>:</b>
                            <?$methods = Helper::getYandexPayMethods();?>
                            <?=$methods[$yandexData['result']['request']['billing_info']['payment_method']]?><br><br>
                            <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_OFR_LIST_COST_DOST_MIN')?>:</b>
                            <?=($yandexData['result']['request']['billing_info']['delivery_cost']/100)?><br><br>
                        </div>
                    </div>


                    <div class="adm-detail-content">
                        <div class="adm-detail-title">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_DOST_TITLE')?>
                        </div>
                        <div class="adm-detail-content-item-block">
                            <b><?=Loc::getMessage("AWZ_YDELIVERY_INTERVAL_DOSTAVKI")?></b>:
                            <?
                            $from = \Bitrix\Main\Type\DateTime::createFromTimestamp(
                                    $yandexData['result']['request']['destination']['interval']['from']
                            );
                            $to = \Bitrix\Main\Type\DateTime::createFromTimestamp(
                                    $yandexData['result']['request']['destination']['interval']['to']
                            );
                            echo $from->toString();
                            echo ' - ';
                            echo $to->toString();
                            ?>
                            <?if(!empty($yandexData['result']['request']['destination']['custom_location'])){?>
                                <br><br>
                                <b><?=Loc::getMessage("AWZ_YDELIVERY_ADRES_DOSTAVKI")?></b>:
                                <?=$yandexData['result']['request']['destination']['custom_location']['details']['full_address']?>
                            <?}?>
                            <?if(!empty($yandexData['result']['request']['destination']['custom_location']['latitude'])){?>
                                <br><br>
                                <div id="awz-ydelivery-map-detail" style="width:100%;height:300px;"></div>
                                <script>
                                    $(document).ready(function(){
                                        ymaps.ready(function () {
                                            var map = new ymaps.Map("awz-ydelivery-map-detail", {
                                                center: [
                                                    <?=$yandexData['result']['request']['destination']['custom_location']['longitude']?>,
                                                    <?=$yandexData['result']['request']['destination']['custom_location']['latitude']?>
                                                ],
                                                zoom: 16,
                                                controls: ['zoomControl']
                                            }, {
                                                balloonMaxWidth: 200
                                            });
                                            var placemark = new ymaps.Placemark(
                                                [
                                                    <?=$yandexData['result']['request']['destination']['custom_location']['longitude']?>,
                                                    <?=$yandexData['result']['request']['destination']['custom_location']['latitude']?>
                                                ], {
                                                balloonContent: '<div><b><?=Loc::getMessage("AWZ_YDELIVERY_ADMIN_OL_EDIT_DOST_TITLE")?></b><br>' +
                                                    '<b><?=Loc::getMessage("AWZ_YDELIVERY_ADRES")?></b>: <?=$yandexData['result']['request']['destination']['custom_location']['details']['full_address']?></div>'
                                            }, {
                                                iconLayout: 'default#image',
                                                iconImageHref: "/bitrix/images/awz.ydelivery/yandexPoint.svg",
                                                iconImageSize: [32, 42],
                                                iconImageOffset: [-16, -42],
                                                preset: 'islands#blackClusterIcons'
                                            });
                                            map.geoObjects.add(placemark);
                                        });
                                    });
                                </script>
                            <?}elseif($yandexData['result']['request']['destination']['platform_station']['platform_id']){?>
                                    <?
                                    $pickDataRes = PvzTable::getPvz($yandexData['result']['request']['destination']['platform_station']['platform_id']);
                                    if($pickDataRes){
                            $pickData = $pickDataRes['PRM'];
                                        ?>
                            <br><br>
                                <b><?=Loc::getMessage("AWZ_YDELIVERY_ADRES_DOSTAVKI")?></b>: <?=Loc::getMessage("AWZ_YDELIVERY_PVZ")?>: <?=$pickData['id']?>
                                <div class="awz-yd-bln-wrap-admin-detail">
                                    <?
                                    $resPost = Helper::getBaloonHtml($pickData['id'], true);
                                    if($resPost->isSuccess()){
                                        $dataPost = $resPost->getData();
                                        echo $dataPost['html'];
                                    }else{
                                        echo '<p style="color:red;">'.implode('; ',$resPost->getErrorMessages()).'</p>';
                                    }
                                    ?>
                                </div>
                            <br><br>
                                <div id="awz-ydelivery-map-detail" style="width:100%;height:300px;"></div>
                                <script>
                                    $(document).ready(function(){
                                        ymaps.ready(function () {
                                            var map = new ymaps.Map("awz-ydelivery-map-detail", {
                                                center: [<?=$pickData['position']['latitude']?>, <?=$pickData['position']['longitude']?>],
                                                zoom: 16,
                                                controls: ['zoomControl']
                                            }, {
                                                balloonMaxWidth: 200
                                            });
                                            var placemark = new ymaps.Placemark([<?=$pickData['position']['latitude']?>, <?=$pickData['position']['longitude']?>], {
                                                balloonContent: '<div><b><?=Loc::getMessage("AWZ_YDELIVERY_ADMIN_OL_EDIT_DOST_TITLE")?></b><br>' +
                                                    '<b><?=Loc::getMessage("AWZ_YDELIVERY_ADRES")?></b>: <?=$pickData['address']['full_address']?></div>'
                                            }, {
                                                iconLayout: 'default#image',
                                                iconImageHref: "/bitrix/images/awz.ydelivery/yandexPoint.svg",
                                                iconImageSize: [32, 42],
                                                iconImageOffset: [-16, -42],
                                                preset: 'islands#blackClusterIcons'
                                            });
                                            map.geoObjects.add(placemark);
                                        });
                                    });
                                </script>
                                        <?
                                    }
                                ?>
                            <?}?>
                        </div>
                    </div>



                    <?
                    $statusOb = \CSaleStatus::GetList();
                    $statusAr = array('ALL'=>array("NAME"=>Loc::getMessage("AWZ_YDELIVERY_ADMIN_OL_VSE_STATUSY"),"ID"=>"ALL"));
                    while($d = $statusOb->fetch()){
                        $statusAr[$d['ID']] = $d;
                    }
                    ?>

                    <div class="adm-detail-content">
                        <div class="adm-detail-title">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_HIST_TITLE')?>
                        </div>
                        <div class="adm-detail-description">
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_HIST_TITLE_DESC')?><br><br>
                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_COUNT_RESP',array(
                                    '#CN#'=>intval($data['HISTORY']['count_resp']),
                                    '#LIMIT#'=>intval(Option::get($module_id, 'CHECKER_COUNT_'.$curProfile, '0', '')),
                            ))?><br><br>
                        </div>
                        <div class="adm-detail-content-item-block">
                            <form method="post">
                                <p><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_AGN_TITLE')?></p>
                                <p><b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_AGN_DESC')?></b></p>
                                <br>
                                <input type="hidden" name="START_AGENT" value="Y">
                                <input type="hidden" name="IFRAME_TYPE" value="<?=$_REQUEST['IFRAME_TYPE']?>">
                                <input type="hidden" name="IFRAME" value="<?=$_REQUEST['IFRAME']?>">
                                <input type="submit" class="adm-btn-active" value="<?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_AGN_BTN')?>">

                                <br><br>
                            </form>
                        </div>
                        <?if(!empty($data['HISTORY']['hist'])){?>
                        <div class="adm-list-table-wrap">
                        <table class="awz-yandex-items adm-list-table">
                            <tr class="adm-list-table-header">
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_AGN_TH1')?>
                                    </div></th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_AGN_TH2')?>
                                    </div></th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_AGN_TH3')?>
                                    </div></th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_STAT_MODULE')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_STAT_MODULE_SETT')?>
                                    </div>
                                </th>
                                <th class="adm-list-table-cell"><div class="adm-list-table-cell-inner">
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_STAT_MODULE_SETT_5')?>
                                    </div>
                                </th>
                            </tr>
                            <?foreach($data['HISTORY']['hist'] as $item){?>
                                <tr class="adm-list-table-row">
                                    <td class="adm-list-table-cell"><?=$item['status']?></td>
                                    <td class="adm-list-table-cell"><?=$item['description']?></td>
                                    <td class="adm-list-table-cell">
                                        <?
                                        if($item['timestamp']){
                                            $date = \Bitrix\Main\Type\DateTime::createFromTimestamp($item['timestamp']);
                                            ?>
                                            <?=$date->toString()?>
                                        <?}?>
                                    </td>
                                    <td class="adm-list-table-cell">
                                        <?
                                        if(isset($item['status_m'])){
                                            ?><?=$item['status_m']?><br>
                                            <?=(isset($val_stat_all[$item['status_m']]) ? $val_stat_all[$item['status_m']] : '')?><?
                                        }else{?>
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_ERR_EMPSTAT')?>
                                        <?}?>
                                    </td>
                                    <td class="adm-list-table-cell">
                                    <?if(isset($item['status_m'])){?>
                                        <?if(in_array($item['status_m'], $val_stat_disabled)){?>
                                            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_ERR_DSBL')?>
                                        <?}else{?>
                                            <?
                                            $key = 'md5_'.md5('PARAMS_STATUS_FROM_'.$curProfile.'_'.$item['status_m']);

                                            $val = Option::get($module_id, $key, '', '');
                                            $val = unserialize($val);
                                            if(!is_array($val)) $val = array();
                                            if(empty($val)){
                                                ?><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_STAT_MODULE_SETT_1')?><?
                                            }else{
                                                ?>
                                                <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_STAT_MODULE_SETT_2')?></b>:
                                                <?
                                                $statNames = array();
                                                foreach($val as $codeBxStat){
                                                    $statNames[] = '['.$statusAr[$codeBxStat]['ID'].'] '.$statusAr[$codeBxStat]['NAME'];
                                                }
                                                ?>
                                                <?=implode('; ',$statNames)?>
                                                <?
                                            }
                                            ?>
                                            <b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_STAT_MODULE_SETT_2_V')?></b>
                                            <?
                                            $key2 = 'md5_'.md5('PARAMS_STATUS_TO_'.$curProfile.'_'.$item['status_m']);
                                            $val2 = Option::get($module_id, $key2, '', '');
                                            if($val2){
                                                if($val2 == 'DISABLE'){
                                                    $val2 = Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_STAT_MODULE_SETT_4');
                                                }else{
                                                    $val2 = '['.$statusAr[$val2]['ID'].'] '.$statusAr[$val2]['NAME'];
                                                }

                                                ?><?=$val2?><?
                                            }else{
                                                ?><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_STAT_MODULE_SETT_3')?><?
                                            }
                                            ?>
                                            <br><a href="#" onclick="BX.SidePanel.Instance.open('/bitrix/admin/settings.php?mid=awz.ydelivery&lang=ru&profile=<?=$curProfile?>&code=<?=$item['status_m']?>',{cacheable: false});return false;">
                                                настройки</a>
                                        <?}?>
                                    <?}else{?>
                                        <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_ERR_EMPSTAT')?>
                                    <?}?>
                                    </td>
                                    <td class="adm-list-table-cell">
                                        <?if(!empty($data['HISTORY']['setstatus'])){?>
                                            <?foreach($data['HISTORY']['setstatus'] as $ks=>$dt){
                                                if(!isset($dt[2])) continue;
                                                if($dt[2] != $item['status_m']) continue;
                                                ?>
                                                <?if(isset($dt[3])){?>
                                                    <b style="color:red;"><?=$dt[3]?></b>
                                                <?}?>
                                                <b><?=\Bitrix\Main\Type\DateTime::createFromTimestamp($dt[0])->toString()?></b>
                                                <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_TITLE_STAT_MODULE_SETT_2_V')?>
                                                <?
                                                $statusPr = '['.$statusAr[$dt[1]]['ID'].'] '.$statusAr[$dt[1]]['NAME'];
                                                echo $statusPr;
                                                ?><br><br>
                                            <?}?>
                                        <?}?>
                                    </td>
                                </tr>
                            <?}?>
                        </table>
                        </div>
                        <?}else{?>
                        <div class="adm-detail-content-item-block">
                            <p><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_EMPTY')?></p>
                        </div>
                        <?}?>
                    </div>



                    <div class="adm-detail-content">
                        <?if(!empty($data['HISTORY']['errors'])){?>
                            <div class="adm-detail-title">
                                <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ERR_TITLE')?>
                            </div>
                            <div class="adm-detail-content-item-block">
                                <?foreach($data['HISTORY']['errors'] as $cn=>$err){?>
                                    <b>error<?=($cn+1)?>:</b>
                                    <?=is_array($err) ? implode('; ', $err) : $err?><br><br>
                                <?}?>
                            </div>
                        <?}?>
                    </div>

                    <div class="adm-detail-content">
                        <?//echo'<pre>';print_r($yandexData);echo'</pre>';?>
                    </div>

                </div>
            </div>



        </div>

        <?php



    }else{
        CAdminMessage::ShowMessage(array('TYPE'=>'ERROR', 'MESSAGE'=>implode('; ',$yandexRes->getErrorMessages())));
    }

    ?>
    <?if($data){?>
    <div class="adm-list-table-layout">
        <div class="adm-list-table-top">
            <b style="font-size:18px;line-height:30px;"><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_CANS')?> <?=$data['OFFER_ID']?></b>
        </div>
        <div class="adm-list-table adm-list-table-without-header">
            <div class="adm-detail-content-wrap">
                <div class="adm-detail-content">
                    <form method="post">
                        <p><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_CANS_MESS1')?></p>
                        <p><b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_CANS_MESS2')?></b></p>
                        <input type="text" name="OFFER_ID_CONFIRM" value=""><br><br>
                        <input type="hidden" name="CANSEL" value="Y">
                        <input type="hidden" name="IFRAME_TYPE" value="<?=$_REQUEST['IFRAME_TYPE']?>">
                        <input type="hidden" name="IFRAME" value="<?=$_REQUEST['IFRAME']?>">
                        <input type="submit" class="adm-btn-active" value="<?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_CANS_BTN')?>">

                        <br><br>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="adm-list-table-layout">
        <div class="adm-list-table-top">
            <b style="font-size:18px;line-height:30px;"><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_DEL')?> <?=$data['OFFER_ID']?></b>
        </div>
        <div class="adm-list-table adm-list-table-without-header">
            <div class="adm-detail-content-wrap">
                <div class="adm-detail-content">
                    <form method="post">
                        <p><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ATT_BTN_CONF')?></p>
                        <?if(Option::get($module_id, "DELETE_AFTER_RESP", "N","") == 'Y'){?>
                            <p style="color:red;"><b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ATT')?></b>
                                <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ATT_MESS1')?></p>
                        <?}else{?>
                            <p style="color:red;"><b><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ATT')?></b>
                                 <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ATT_MESS2')?></p>
                        <?}?>

                        <input type="text" name="OFFER_ID_CONFIRM" value=""><br><br>
                        <input type="hidden" name="DELETE" value="Y">
                        <input type="hidden" name="DELETE_AFTER_RESP" value="<?=Option::get($module_id, "DELETE_AFTER_RESP", "N","")?>">
                        <input type="hidden" name="IFRAME_TYPE" value="<?=$_REQUEST['IFRAME_TYPE']?>">
                        <input type="hidden" name="IFRAME" value="<?=$_REQUEST['IFRAME']?>">
                        <input type="submit" class="adm-btn-active" value="<?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EDIT_ATT_BTN')?>">

                        <br><br>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?}?>
    <?php

}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");