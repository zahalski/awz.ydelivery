<?php
namespace Awz\Ydelivery;

use Bitrix\Main\DB\Exception;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Result;
use Bitrix\Main\Error;

class Ydapi {

    const URL = 'https://b2b.taxi.yandex.net';
    const SANDBOX_URL = 'https://b2b.taxi.tst.yandex.net';

    private static $_instance = null;
    private $token = null;
    private $testMode = false;

    private $lastResponse = null;

    private function __construct($params=array())
    {
        if($params['token']) $this->token = $params['token'];
    }

    public static function getInstance($params=array())
    {
        if(is_null(self::$_instance)){
            self::$_instance = new self($params);
        }
        return self::$_instance;
    }

    public function setSandbox(){
        $this->testMode = true;
    }

    public function setProdMode(){
        $this->testMode = false;
    }

    public function isTest(){
        return $this->testMode;
    }

    public function getToken(){
        return $this->token;
    }

    public function setToken($token){
        $this->token = $token;
    }

    public function geo_id($adress){
        return $this->send('api/b2b/platform/location/detect', array('location'=>$adress));
    }

    public function calc($data = array()){
        return $this->send('api/b2b/platform/pricing-calculator', $data);
    }

    public function grafik($data){
        return $this->send('api/b2b/platform/offers/info?'.http_build_query($data), array(), 'get');
    }

    public function send($method, $data = array(), $type='post'){

        $url = $this->isTest() ? self::SANDBOX_URL : self::URL;
        $url .= '/'.$method;

        $httpClient = new HttpClient();
        $httpClient->disableSslVerification();
        $httpClient->setHeaders(array(
            'Authorization'=>'Bearer '.$this->getToken(),
            "Content-Type"=> "application/json"
        ));

        /*$dataJson = \Bitrix\Main\Web\Json::encode($data);

        $path = $this->isTest() ? self::SANDBOX_URL : self::URL;
        $path .= '/'.$method;
        $headers  = array(
            "Authorization: Bearer ".$this->getToken()."",
            "Content-Type: application/json"
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $path);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $dataJson);
        $r = curl_exec($curl);
        curl_close($curl);
        print_r($r);
        die();*/

        if($type == 'get'){
            $res = $httpClient->get($url);
        }else{
            $res = $httpClient->post($url, \Bitrix\Main\Web\Json::encode($data));
        }


        $this->lastResponse = $httpClient;

        $result = new Result();
        if(!$res){
            $result->addError(
                new Error('empty response')
            );
        }else{
            try {
                $json = \Bitrix\Main\Web\Json::decode($res);
                /*
                 * error -> array('code'=>'str', 'message'=>'str')
                 * */
                if($json['code'] && $json['message']){
                    $result->addError(
                        new Error($json['message'], $json['code'])
                    );
                }
                $result->setData(array('result'=>$json));
            }catch (Exception $ex){
                $result->addError(
                    new Error($ex->getMessage(), $ex->getCode())
                );
            }
        }

        return $result;

    }

    public function getLastResponse(){
        return $this->lastResponse;
    }

}