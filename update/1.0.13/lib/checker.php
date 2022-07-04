<?php
namespace Awz\Ydelivery;

use Bitrix\Main\Config\Option;
use Bitrix\Sale\Order;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Application;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

class Checker {

    public static function runJob($points){
        if(!is_array($points)) return;
        foreach($points as $point){
            PvzTable::updatePvz($point);
        }
    }

    public static function agentGetPickpoints($force=false){

        if(Option::get(Handler::MODULE_ID, "UPDATE_PVZ_BG", "Y", "") == 'Y' && !$force) {
            return "\\Awz\\Ydelivery\\Checker::agentGetPickpoints();";
        }

		$isUtf8 = Application::getInstance()->isUtfMode();

        $deliveryProfileList = Helper::getActiveProfileIds(Helper::DOST_TYPE_PVZ);

        if(!empty($deliveryProfileList)){
            foreach($deliveryProfileList as $profileId=>$profileName){
                $api = Helper::getApiByProfileId($profileId);

                //if($api->isTest()) continue;

				if (!$isUtf8){
				$api->setStandartJson(true);
				}
                $pvzResult = $api->getPickpoints();
				if (!$isUtf8){
					$api->setStandartJson(false);
				}
                if($pvzResult->isSuccess()){
                    $pvzData = $pvzResult->getData();
                    foreach($pvzData['result']['points'] as $point){
						if (!$isUtf8){
							$point = Json::decode(json_encode($point));
						}
                        PvzTable::updatePvz($point);
                    }
                }

                break;
            }
        }

        return "\\Awz\\Ydelivery\\Checker::agentGetPickpoints();";

    }

    /**
     * перевод статусов яндекса в не дублирующие статусы модуля
     * (костыль с добавлением предыдущего статуса)
     *
     * @param $histStatus
     * @return array
     */
    public static function unDoubleStatus($histStatus){
        $dublicateStatusList = array(
            'DELIVERY_PROCESSING_STARTED',
            'DELIVERY_LOADED',
            'DELIVERY_AT_START',
            'DRAFT',
            'DELIVERY_TRANSPORTATION_RECIPIENT',
            'DELIVERY_AT_START_SORT',
            'DELIVERY_UPDATED_BY_RECIPIENT',
            'DELIVERY_UPDATED_BY_DELIVERY'
        );

        $statList = array();
        $prevStatus = array();
        foreach($histStatus as $stat){
            if(isset($statList[$stat['status']]) || in_array($stat['status'], $dublicateStatusList)){
                $statusCode = $prevStatus['status'].'_'.$stat['status'];
                $statusMess = $prevStatus['description'].', '.$stat['description'];
                if(isset($statList[$statusCode])){
                    $statusCode = strtoupper(Helper::translit($prevStatus['description'])).'_'.$statusCode;
                    $statusMess = $prevStatus['description'].', '.$statusMess;
                }
                $statList[$statusCode] = $stat;
                $statList[$statusCode]['hash'] = md5(serialize($stat));
                $statList[$statusCode]['status_m'] = $statusCode;
                $statList[$statusCode]['status_d'] = $statusMess;
            }else{
                $statList[$stat['status']] = $stat;
                $statList[$stat['status']]['hash'] = md5(serialize($stat));
                $statList[$stat['status']]['status_m'] = $stat['status'];
                $statList[$stat['status']]['status_d'] = $stat['description'];

            }
            $prevStatus = $stat;
        }
        return $statList;
    }

    /**
     * @throws \Bitrix\Main\NotImplementedException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\SystemException
     * @throws \Bitrix\Main\ArgumentException
     */
    public static function agentGetStatus($disableTimer=false){

        if(!\Bitrix\Main\Loader::includeModule('sale')){
            return "\\Awz\\Ydelivery\\Checker::agentGetStatus();";
        }

        if($disableTimer){
            $r = PvzTable::getList(array('select'=>array('ID'),'limit'=>1))->fetch();
            if(!$r){
                self::agentGetPickpoints();
            }
        }

        $deliveryProfileList = Helper::getActiveProfileIds();

        $statusList = unserialize(Option::get(Handler::MODULE_ID, 'YD_STATUSLIST', '', ''));

        $checkedOrder = array();
        foreach($deliveryProfileList as $profileId=>$profileName){

            $activeChecker = Option::get(
                Handler::MODULE_ID,
                'CHECKER_ON_'.$profileId,
                '',''
            );

            if($activeChecker != 'Y') continue;
            $api = Helper::getApiByProfileId($profileId);

            $opt_interval = Option::get(
                Handler::MODULE_ID,
                'CHECKER_INTERVAL_'.$profileId,
                '',''
                )*60*60;
            if($disableTimer) $opt_interval = 0;
            $opt_maxcount = Option::get(
                Handler::MODULE_ID,
                'CHECKER_COUNT_'.$profileId,
                '',''
            );
            $opt_CHECKER_FIN = unserialize(
                Option::get(
                Handler::MODULE_ID,
                'CHECKER_FIN_'.$profileId,
                '',''
                )
            );
            if(!is_array($opt_CHECKER_FIN)) $opt_CHECKER_FIN = array();

            $filter = array(
                "!=HISTORY_FIN"=>'Y',
                '=ORD.CANCELED'=>'N',
                '<=LAST_DATE'=>\Bitrix\Main\Type\DateTime::createFromTimestamp(time()-$opt_interval)
            );
            if(!empty($checkedOrder)){
                $filter['!ORDER_ID'] = $checkedOrder;
            }

            $r = OffersTable::getList(
                array(
                    'select'=>array("*",'ORD_STATUS'=>'ORD.STATUS_ID','ORD_TRACKING_NUMBER'=>'ORD.TRACKING_NUMBER'),
                    'filter'=>$filter,
                    'limit'=>15,
                    'order'=>array('ID'=>'DESC')
                )
            );

            $val_stat_disabled = Option::get(Handler::MODULE_ID, "CHECKER_FIN_DSBL", "", '');
            $val_stat_disabled = unserialize($val_stat_disabled);
            if(!is_array($val_stat_disabled)) $val_stat_disabled = array();

            while($data = $r->fetch()){

                $finUp = $data['HISTORY'];
                if(!$finUp) $finUp = array();
                if(!isset($finUp['hist'])) $finUp['hist'] = array();
                if(!$finUp['errors']) $finUp['errors'] = array();

                $order = Order::load($data['ORDER_ID']);
                if(!$order) continue;


                if(Helper::getProfileId($order) != $profileId) continue;

                $checkedOrder[] = $data['ORDER_ID'];

                $ydRes = $api->offerHistory($data['OFFER_ID']);

                $finUp['count_resp'] = intval($finUp['count_resp']) + 1;

                $noUpdateDate = false;
                $startStatusCode = $finUp['lastStatusCode'];
                if($ydRes->isSuccess()){
                    $ydData = $ydRes->getData();

                    //$data['LAST_STATUS']

                    $undStatus = self::unDoubleStatus($ydData['result']['state_history']);

                    foreach($undStatus as $statRow){
                        if(in_array($statRow['status_m'], $val_stat_disabled)) continue;
                        if($statRow['status_m'])
                            $data['LAST_STATUS'] = $statRow['status_m'];
                    }
                    $lastStatusCode = '';
                    $upStatList = false;
                    foreach($undStatus as $statRow){

                        if(in_array($statRow['status_m'], $val_stat_disabled)) {
                            $hash = $statRow['hash'];
                            if(!isset($finUp['hist'][$hash])) {
                                $finUp['hist'][$hash] = $statRow;
                            }
                            continue;
                        }

                        $lastStatusCode = $statRow['status_m'];
                        if($lastStatusCode && !isset($statusList[$lastStatusCode])){
                            $statusList[$lastStatusCode] = $statRow['status_d'];
                            $upStatList = true;
                        }
                        $hash = $statRow['hash'];
                        if(!isset($finUp['hist'][$hash])){
                            $finUp['hist'][$hash] = $statRow;
                            $noUpdateDate = true;
                            //не обновляем дату т.к. могут быть еще новые статусы
                            break;
                        }
                    }
                    $finUp['lastStatusCode'] = $lastStatusCode;
                    //обновление списка статусов, для настроек
                    if($upStatList)
                        Option::set(Handler::MODULE_ID, 'YD_STATUSLIST', serialize($statusList), '');

                }else{
                    $finUp['errors'][] = $ydRes->getErrorMessages();
                    $finUp['count_error'] = intval($finUp['count_error']) + 1;
                }

                $finalize = 'N';
                if($finUp['count_resp']>$opt_maxcount) {
                    $finalize = 'Y';
                    //проблема с заказом, статус не финализировался
                }
                //if(count($finUp['errors'])>48) {
                    //проблема с заказом, статус не финализировался, много ошибок
                //}
                if($finUp['lastStatusCode']){
                    if(in_array($finUp['lastStatusCode'],$opt_CHECKER_FIN)) $finalize = 'Y';
                }
                if($finalize == 'Y'){
                    $noUpdateDate = false;
                }
                $arChange = array(
                    'HISTORY'=>$finUp,
                    'HISTORY_FIN'=>$finalize,
                    'LAST_STATUS'=>$data['LAST_STATUS']
                );
                if(!$noUpdateDate){
                    $arChange['LAST_DATE'] = \Bitrix\Main\Type\DateTime::createFromTimestamp(time());
                }
                OffersTable::update(
                    array('ID'=>$data['ID']),
                    $arChange
                );

                if($finUp['lastStatusCode']){
                    $lStatus = $finUp['lastStatusCode'];
                    $newStatus = false;
                    $opt = Option::get(
                        Handler::MODULE_ID,
                        'md5_'.md5('PARAMS_STATUS_TO_'.$profileId.'_'.$lStatus),
                        '', ''
                    );
                    if(!$opt) {
                        $newStatus = false;
                    }elseif($opt == 'DISABLE'){
                        $newStatus = false;
                    }elseif($opt){
                        $newStatus = $opt;
                    }
                    $ordStatus = $data['ORD_STATUS'];
                    $optOrd = Option::get(
                        Handler::MODULE_ID,
                        'md5_'.md5('PARAMS_STATUS_FROM_'.$profileId.'_'.$lStatus),
                        '', ''
                    );
                    $optOrd = unserialize($optOrd);
                    if(empty($optOrd)) {
                        $newStatus = false;
                    }elseif(in_array('ALL',$optOrd)){

                    }elseif(in_array($ordStatus,$optOrd)){

                    }else{
                        $newStatus = false;
                    }

                    //$startStatusCode


                    $event = new Event(
                        Handler::MODULE_ID,
                        "onBeforeStatusUpdate",
                        array('order'=>$order,
                            'ordStatus'=>$ordStatus,
                            'startStatus'=>$startStatusCode,
                            'newOrdStatus'=>$newStatus,
                            'newStatus'=>$lStatus
                        )
                    );
                    $event->send();
                    if ($event->getResults()) {
                        foreach ($event->getResults() as $evenResult) {
                            if ($evenResult->getType() == EventResult::SUCCESS) {
                                $r = $evenResult->getParameters();
                                if(isset($r['newOrdStatus']) && $r['newOrdStatus']){
                                    $newStatus = $r['newOrdStatus'];
                                }
                                if(isset($r['result']) && ($r['result'] instanceof \Bitrix\Main\Result)){
                                    if(!$r['result']->isSuccess()) {
                                        $finUp['errors'][] = $r['result']->getErrorMessages();
                                        OffersTable::update(
                                            array('ID'=>$data['ID']),
                                            array(
                                                'HISTORY'=>$finUp
                                            )
                                        );
                                    }
                                }
                            }
                        }
                    }

                    $chekerUpdate = array();
                    $chekerUpdateErr = array();
                    if($newStatus){
                        if($newStatus != $ordStatus) {

                            $order->setField('STATUS_ID', $newStatus);
                            $result = $order->save();
                            /* @var $result \Bitrix\Sale\Result */
                            if(!isset($finUp['setstatus'])){
                                $finUp['setstatus'] = array();
                            }
                            if(!$result->isSuccess()){
                                $finUp['errors'][] = $result->getErrorMessages();
                                $finUp['setstatus'][] = array(
                                    time(), $newStatus,
                                    $finUp['lastStatusCode'], 'error:'.count($finUp['errors'])
                                );
                                $chekerUpdateErr = $result->getErrorMessages();
                            }else{
                                $finUp['setstatus'][] = array(
                                    time(), $newStatus,
                                    $finUp['lastStatusCode']
                                );
                                $chekerUpdate = array(time(), $newStatus);
                            }
                            OffersTable::update(
                                array('ID'=>$data['ID']),
                                array(
                                    'HISTORY'=>$finUp
                                )
                            );

                        }
                    }

                    $event = new Event(
                        Handler::MODULE_ID,
                        "onOfterStatusUpdate",
                        array(
                            'order'=>$order,
                            'ordStatus'=>$ordStatus,
                            'newOrdStatus'=>$newStatus,
                            'startStatus'=>$startStatusCode,
                            'newStatus'=>$lStatus,
                            'chekerUpdate'=>$chekerUpdate,
                            'chekerUpdateErr'=>$chekerUpdateErr
                        )
                    );
                    $event->send();


                }

                //die();

            }

        }

        return "\\Awz\\Ydelivery\\Checker::agentGetStatus();";

    }

}