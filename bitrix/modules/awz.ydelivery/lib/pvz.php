<?php

namespace Awz\Ydelivery;

use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Entity;
use Bitrix\Main\Result;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class PvzTable extends Entity\DataManager
{
    public static function getFilePath()
    {
        return __FILE__;
    }

    public static function getTableName()
    {
        return 'b_awz_ydelivery_pvz';
        /*
        CREATE TABLE IF NOT EXISTS `b_awz_ydelivery_pvz` (
        `ID` int(18) NOT NULL AUTO_INCREMENT,
        `PVZ_ID` varchar(255) NOT NULL,
        `PRM` varchar(6255) DEFAULT NULL,
        PRIMARY KEY (`ID`)
        ) AUTO_INCREMENT=1;
        */
        //ALTER TABLE `b_awz_ydelivery_pvz` ADD `DOST_DAY` int(7) DEFAULT NULL
        //ALTER TABLE `b_awz_ydelivery_pvz` ADD `LAST_UP` datetime DEFAULT NULL
    }

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('ID', array(
                'primary' => true,
                'autocomplete' => false,
                'title'=>Loc::getMessage('AWZ_YDELIVERY_PVZ_FIELDS_ID')
                )
            ),
            new Entity\StringField('PVZ_ID', array(
              'required' => true,
              'title'=>Loc::getMessage('AWZ_YDELIVERY_PVZ_FIELDS_PVZ_ID')
                )
            ),
            new Entity\StringField('PRM', array(
                'required' => false,
                'serialized'=>true,
                'title'=>Loc::getMessage('AWZ_YDELIVERY_PVZ_FIELDS_PRM')
                )
            ),
            new Entity\IntegerField('DOST_DAY', array(
                    'required' => false,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_PVZ_FIELDS_DOST_DAY')
                )
            ),
            new Entity\DatetimeField('LAST_UP', array(
                    'required' => false,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_PVZ_FIELDS_LAST_UP')
                )
            ),
            new Entity\ReferenceField('PVZEXT', '\Awz\Ydelivery\PvzExtTable',
                array('=this.PVZ_ID' => 'ref.PVZ_ID')
            ),
        );
    }

    public static function updatePvz($data){
        if($data['id']){
            $dataBd = self::getPvz($data['id']);
            $hash = md5(serialize($data));
            $data['hash'] = $hash;
            if(!$dataBd){
                self::add(array(
                    'PVZ_ID'=>$data['id'],
                    'PRM'=>$data
                ));
            }elseif($data['hash'] != $dataBd['PRM']['hash']){
                self::update(array('ID'=>$dataBd['ID']),array(
                    'PVZ_ID'=>$data['id'],
                    'PRM'=>$data
                ));
            }
        }
    }

    public static function getPvz($pvzId){

        if(Option::get(Handler::MODULE_ID, "SEARCH_EXT", "N","") == 'Y'){
            $extRes = PvzExtTable::getList(array(
                'select'=>array('PVZ_ID'),
                'filter'=>array('=EXT_ID'=>$pvzId),
                'limit'=>1
            ))->fetch();
            if($extRes) $pvzId = $extRes['PVZ_ID'];
        }

        $result = self::getList(array(
            'select'=>array('*'),
            'filter'=>array('=PVZ_ID'=>$pvzId),
            'limit'=>1
        ))->fetch();

        return $result;
    }

    public static function deleteAll(){

        $connection = Application::getConnection();
        $sql = "TRUNCATE TABLE ".self::getTableName().";";
        $connection->queryExecute($sql);

    }

    public static function deleteOldPvzAgent(){

        $endTime = time() + 20;

        $r = PvzTable::getList(
            array(
                'select'=>array('ID'),
                'order'=>array('ID'=>'ASC'),
                'filter'=>array('=DOST_DAY'=>-1)
            )
        );
        while(($dataPvz = $r->fetch()) && ($endTime>time())){
            self::delete($dataPvz);
        }

        return '\Awz\Ydelivery\PvzTable::deleteOldPvzAgent();';
    }

    public static function calcAgent($profileId, $nextTime=0, $lastId=0, $checkPeriod=14400, $town='', $type='', $post_office=false){

        $post_office_ag = $post_office ? 'true' : 'false';
        if(!$nextTime) $nextTime = time();

        if($nextTime > time()){
            return '\Awz\Ydelivery\PvzTable::calcAgent('.$profileId.','.$nextTime.','.$lastId.','.$checkPeriod.',"'.$town.'","'.$type.'",'.$post_office_ag.');';
        }elseif(!$lastId && (intval(date('H', $nextTime)) < 3)){
            $nextTime = $nextTime + 3600;
            return '\Awz\Ydelivery\PvzTable::calcAgent('.$profileId.','.$nextTime.','.$lastId.','.$checkPeriod.',"'.$town.'","'.$type.'",'.$post_office_ag.');';
        }

        if($lastId==0){
            \CEventLog::Add(array(
                    'SEVERITY' => 'DEBUG',
                    'AUDIT_TYPE_ID' => 'AGENT',
                    'MODULE_ID' => Handler::MODULE_ID,
                    'DESCRIPTION' => Loc::getMessage('AWZ_YDELIVERY_PVZ_AGENT_START').' '.$town.' '.$type,
                )
            );
        }

        $endTime = time() + 20;

        $api = Helper::getApiByProfileId($profileId);
        $config = Helper::getConfigByProfileId($profileId);

        if($town=='yandex'){
            $r = PvzTable::getList(
                array(
                    'select'=>array('ID', 'PVZ_ID', 'PRM'),
                    'order'=>array('ID'=>'ASC'),
                    'filter'=>array('>ID'=>$lastId, '!PVZEXT.ID'=>false),
                    'limit'=>1000
                )
            );
        }else{
            $r = PvzTable::getList(
                array(
                    'select'=>array('ID', 'PVZ_ID', 'PRM'),
                    'order'=>array('ID'=>'ASC'),
                    'filter'=>array('>ID'=>$lastId),
                    'limit'=>1000
                )
            );
        }


        $lastId = 0;
        $maxPerSecond = 35;
        $cnt=0;
        while(($dataPvz = $r->fetch()) && ($endTime>time())){
            $lastId = $dataPvz['ID'];
            if($town && ($town!='yandex')){
                if(mb_strpos(mb_strtolower($dataPvz['PRM']['address']['full_address']), mb_strtolower($town)) === false){
                    continue;
                }
            }elseif($town && ($town=='yandex')){
                if(mb_strpos(mb_strtolower($dataPvz['PRM']['address']['full_address']), mb_strtolower('Москва')) !== false){
                    continue;
                }
                if(mb_strpos(mb_strtolower($dataPvz['PRM']['address']['full_address']), mb_strtolower('Санкт-Петербург')) !== false){
                    continue;
                }
            }
            if($type && ($dataPvz['PRM']['type'] != $type)){
                continue;
            }
            if($dataPvz['PRM']['is_post_office'] != $post_office){
                continue;
            }

            $data = array(
                'station_id'=>$config['MAIN']['STORE_ID'],
                'self_pickup_id'=>$dataPvz['PVZ_ID']
            );
            usleep(1000000/$maxPerSecond);
            $r2 = $api->grafik($data);
            $day = -1;
            if($r2->isSuccess()){
                $grafikData = $r2->getData();
                if(isset($grafikData['result']['offers']) && !empty($grafikData['result']['offers'])){
                    foreach($grafikData['result']['offers'] as $offer){
                        if($config['MAIN']['SROK_FROM_STARTDAY']=='Y'){
                            //TODO добавить дни с конфига профиля
                            $offer['from'] = date('c',strtotime(date('d.m.Y',strtotime($offer['from'])+intval($config['MAIN']['ADD_HOUR'])*60*60)));
                        }
                        $day = ceil((strtotime($offer['from']) - time())/86400);
                        break;
                    }
                }
            }

            PvzTable::update(array('ID'=>$dataPvz['ID']), array(
                'DOST_DAY'=>$day,
                'LAST_UP'=>\Bitrix\Main\Type\DateTime::createFromTimestamp(time()),
            ));
            $cnt++;
        }


        /*\CEventLog::Add(array(
                'SEVERITY' => 'DEBUG',
                'AUDIT_TYPE_ID' => 'AGENT',
                'MODULE_ID' => Handler::MODULE_ID,
                'DESCRIPTION' => 'обновлено: '.$cnt.'шт., '.$town.' '.$type,
            )
        );*/

        if($lastId == 0){
            $nextTime = time() + $checkPeriod;
            \CEventLog::Add(array(
                    'SEVERITY' => 'DEBUG',
                    'AUDIT_TYPE_ID' => 'AGENT',
                    'MODULE_ID' => Handler::MODULE_ID,
                    'DESCRIPTION' => Loc::getMessage('AWZ_YDELIVERY_PVZ_AGENT_END').' '.$town.' '.$type,
                )
            );
        }

        return '\Awz\Ydelivery\PvzTable::calcAgent('.$profileId.','.$nextTime.','.$lastId.','.$checkPeriod.',"'.$town.'","'.$type.'",'.$post_office_ag.');';
    }

}