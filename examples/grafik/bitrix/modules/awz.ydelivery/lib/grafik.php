<?php

namespace Awz\Ydelivery;

use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Entity;
use Bitrix\Main\Result;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class GrafikTable extends Entity\DataManager
{
    public static function getFilePath()
    {
        return __FILE__;
    }

    public static function getTableName()
    {
        return 'b_awz_ydelivery_grafik';
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
            new Entity\StringField('GEO_ID', array(
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

        $connection = \Bitrix\Main\Application::getConnection();
        $sql = "TRUNCATE TABLE ".self::getTableName().";";
        $connection->queryExecute($sql);

    }

    public static function loadGetFile($filePath){

        $result = new \Bitrix\Main\Result;

        //$filePath = $_SERVER['DOCUMENT_ROOT'].'/local/routes_abridged_abridged.csv';
        $file = new \Bitrix\Main\IO\File($_SERVER["DOCUMENT_ROOT"].$filePath);

        if($file->isExists()){
            $content = $file->getContents();
            $arRows = explode("\n",$content);
            $firstRow = explode(";",$arRows[0]);
            if(mb_strtolower(SITE_CHARSET) === 'utf-8')
                $firstRow = \Bitrix\Main\Text\Encoding::convertEncoding($firstRow, 'cp1251', SITE_CHARSET);
            $findKeys = array();
            foreach($firstRow as $key=>$row){
                if(mb_strpos($row, 'Geo ID')!==false){
                    $findKeys['geo_id'] = $key;
                }else{
                    $arParams = array("replace_space"=>"-","replace_other"=>"-");
                    $trans = \Cutil::translit(trim($row),"ru",$arParams);
                    if($trans){
                        $findKeys[$trans] = $key;
                    }
                }
            }

            $keysData = array('otkuda','ot-dn','do-dn','dni-nedeli-zabora','dni-nedeli-dostavki');
            if(isset($findKeys['geo_id'], $findKeys[$keysData[0]], $findKeys[$keysData[1]], $findKeys[$keysData[2]], $findKeys[$keysData[3]], $findKeys[$keysData[4]])){
                foreach($arRows as $k=>$row){
                    if($k==0) {
                        //self::deleteAll();
                        continue;
                    }
                    $rowAr = explode(";",$row);
                    if(mb_strtolower(SITE_CHARSET) === 'utf-8')
                        $rowAr = \Bitrix\Main\Text\Encoding::convertEncoding($rowAr, 'cp1251', SITE_CHARSET);
                    $prepare = array();
                    foreach($keysData as $keyDataKey=>$keyData){
                        if($keyDataKey === 0) continue;
                        $prepare[$keyData] = trim($rowAr[$findKeys[$keyData]]);
                    }
                    self::add(array(
                        'PVZ_ID'=>md5(trim($rowAr[$findKeys['otkuda']])),
                        'GEO_ID'=>trim($rowAr[$findKeys['geo_id']]),
                        'PRM'=>$prepare
                    ));
                }
            }else{
                $result->addError(new \Bitrix\Main\Error('Неверный формат файла'));
            }

        }else{
            $result->addError(new \Bitrix\Main\Error('Файл не найден'));
        }

        return $result;

    }

    public static function checkExists(){

        $connection = \Bitrix\Main\Application::getConnection();
        $checkTable = false;
        $recordsRes = $connection->query("select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='".self::getTableName()."'");
        if($dt = $recordsRes->fetch()) {
            $checkTable = true;
        }
        if(!$checkTable){
            $sql = "CREATE TABLE IF NOT EXISTS `b_awz_ydelivery_grafik` (
                ID int(18) NOT NULL AUTO_INCREMENT,
                PVZ_ID varchar(255) NOT NULL,
                GEO_ID varchar(25) NOT NULL,
                PRM longtext,
                PRIMARY KEY (`ID`),
                unique IX_PVZ_ID_GEO_ID (PVZ_ID, GEO_ID)
            );";
            $connection->query($sql);
        }
    }

}