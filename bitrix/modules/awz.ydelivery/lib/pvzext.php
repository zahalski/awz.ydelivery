<?php

namespace Awz\Ydelivery;

use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Entity;
use Bitrix\Main\Result;

Loc::loadMessages(__FILE__);

class PvzExtTable extends Entity\DataManager
{
    public static function getFilePath()
    {
        return __FILE__;
    }

    public static function getTableName()
    {
        return 'b_awz_ydelivery_pvz_ext';
    }

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('ID', array(
                    'primary' => true,
                    'autocomplete' => false,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_PVZEXT_FIELDS_ID')
                )
            ),
            new Entity\StringField('PVZ_ID', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_PVZ_PVZEXT_PVZ_ID')
                )
            ),
            new Entity\StringField('EXT_ID', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_PVZ_PVZEXT_EXT_ID')
                )
            )
        );
    }

    public static function getIdFromLink($pvzId){

        $result = self::getList(array(
            'select'=>array('PVZ_ID'),
            'filter'=>array('=EXT_ID'=>$pvzId),
            'limit'=>1
        ))->fetch();

        if($result) return $result['PVZ_ID'];

    }

    public static function addLink($pvzId, $pvzExtId){

        $result = new Result();

        $res = PvzTable::getList(array(
            'select'=>array('ID'),
            'filter'=>array('=PVZ_ID'=>$pvzId),
            'limit'=>1
        ))->fetch();
        if(!$res){
            $result->addError(
                new Error($pvzId.' - pvzId not found')
            );
        }else{

            $res = self::getList(array(
                'select'=>array('ID'),
                'filter'=>array('=PVZ_ID'=>$pvzId, '=EXT_ID'=>$pvzExtId),
                'limit'=>1
            ))->fetch();
            if($res){
                $result->addError(
                    new Error($pvzExtId.' - pvzExtId is exists')
                );
            }else{
                $res = self::add(array(
                    'PVZ_ID'=>$pvzId,
                    'EXT_ID'=>$pvzExtId
                ));
                $result->setData(array(
                    'ID'=>$res->getId(),
                    'PVZ_ID'=>$pvzId,
                    'EXT_ID'=>$pvzExtId
                ));
            }
        }

        return $result;

    }

    public static function deleteAll(){

        $connection = \Bitrix\Main\Application::getConnection();
        $sql = "TRUNCATE TABLE ".self::getTableName().";";
        $connection->queryExecute($sql);

    }

}