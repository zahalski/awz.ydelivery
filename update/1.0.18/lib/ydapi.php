<?php
namespace Awz\Ydelivery;

use Bitrix\Main\ObjectException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Web\Json;

Loc::loadMessages(__FILE__);

class Ydapi {

    /**
     * Точка входа api Яндекс доставки (боевой режим)
     */
    const URL = 'https://b2b.taxi.yandex.net';

    /**
     * Точка входа api Яндекс доставки (тестовый режим)
     */
    const SANDBOX_URL = 'https://b2b.taxi.tst.yandex.net';

    /**
     * Папка для кеша в /bitrix/cache/
     */
    const CACHE_DIR = '/awz/ydelivery/';

    private static $_instance = null;
    private $token = null;
    private $testMode = false;

    /**
     * Сохраняется последний ответ api с метода send
     * Может быть пустым в случае ответа с кеша
     * @var null|HttpClient
     */
    private $lastResponse = null;

    private $cacheParams = array();
	
	private $standartJson = false;

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

    /**
     * очистка параметров для кеша
     * должна вызываться после любого запроса через кеш
     */
    public function clearCacheParams(){
        $this->cacheParams = array();
    }

    /**
     * параметры для кеша результата запроса
     *
     * @param $cacheId ид кеша
     * @param $ttl время действия в секундах
     */
    public function setCacheParams($cacheId, $ttl){
        $this->cacheParams = array(
            'id'=>$cacheId,
            'ttl'=>$ttl
        );
    }

    /**
     * Переключение в тестовый режим
     * Самостоятельно необходимо изменить токен
     */
    public function setSandbox(){
        $this->testMode = true;
    }

    /**
     * Переключение в боевой режим
     * Самостоятельно необходимо изменить токен
     */
    public function setProdMode(){
        $this->testMode = false;
    }

    /**
     * Активность режима тестирования
     * @return bool
     */
    public function isTest(){
        return $this->testMode;
    }

    /**
     * Получает текущий токен
     *
     * @return string|null
     */
    public function getToken(){
        return $this->token;
    }

    /**
     * Установка токена
     *
     * @param $token
     */
    public function setToken(string $token){
        $this->token = $token;
    }

    /**
     * Создание заявки
     *
     * @param string $offerId ид заявки
     * @return Result
     */
    public function offerConfirm(string $offerId){
        return $this->send('api/b2b/platform/offers/confirm', array('offer_id'=>$offerId));
    }

    /**
     * Отмена заявки
     *
     * @param string $offerId ид заявки
     * @return Result
     */
    public function offerCansel(string $offerId){
        return $this->send('api/b2b/platform/request/cancel', array('request_id'=>$offerId));
    }

    /**
     * Информация о заявке
     *
     * @param string $offerId ид заявки
     * @return Result
     */
    public function offerInfo(string $offerId){
        return $this->send('api/b2b/platform/request/info?request_id='.$offerId,array(),'get');
    }

    /**
     * История статусов заявки
     *
     * @param string $offerId ид заявки
     * @return Result
     */
    public function offerHistory(string $offerId){
        return $this->send('api/b2b/platform/request/history?request_id='.$offerId);
    }

    /**
     * Получение ярлыков
     *
     * @param array $data
     * @return Result
     */
    public function getLabels(array $data){
        return $this->send('api/b2b/platform/request/generate-labels', $data);
    }

    /**
     * Получение акта
     *
     * @param array $data
     * @return Result
     */
    public function getInvoice(array $data){
        $url = 'api/b2b/platform/request/get-handover-act';
        if(isset($data['request_id'])){
            $url .= '?request_id='.implode(',',$data['request_id']);
        }

        return $this->send($url, array(), 'get');
    }

    /**
     * Список предложений для параметров заказа
     *
     * @param array $data параметры заказа
     * @return Result
     */
    public function getOffers(array $data){
        return $this->send('api/b2b/platform/offers/create', $data);
    }

    /**
     * Получение идентификатора местоположения по адресу
     *
     * @param string $address
     * @return Result
     */
    public function geo_id(string $address){
        return $this->send('api/b2b/platform/location/detect', array('location'=>$address));
    }

    /**
     * Расчет стоимости доставки
     *
     * @param array $data
     * @return Result
     */
    public function calc(array $data){
        return $this->send('api/b2b/platform/pricing-calculator', $data);
    }

    /**
     * Получение сроков доставки
     *
     * @param array data
     * @return Result
     */
    public function grafik(array $data){
        return $this->send('api/b2b/platform/offers/info?'.http_build_query($data), array(), 'get');
    }

    /**
     * Список точек самовывоза
     * @param array $data
     * @return Result
     */
    public function getPickpoints(array $data = array()){
        return $this->send('api/b2b/platform/pickup-points/list', $data);
    }

    /**
     * Запросы к апи логистической платформы
     *
     * @param $method метод апи
     * @param array $data параметры запроса
     * @param string $type post или get
     * @return Result
     * @throws \Bitrix\Main\ArgumentException
     */
    protected function send($method, $data = array(), $type='post'){

        $url = $this->isTest() ? self::SANDBOX_URL : self::URL;
        $url .= '/'.$method;

        $res = null;
        $obCache = null;

        if(!empty($this->cacheParams)){
            $obCache = Cache::createInstance();
            if( $obCache->initCache($this->cacheParams['ttl'],$this->cacheParams['id'],self::CACHE_DIR) ){
                $res = $obCache->getVars();
            }
            $this->clearCacheParams();
        }
        $httpClient = null;
        if(!$res){
            $httpClient = new HttpClient();
            $httpClient->disableSslVerification();
            $httpClient->setHeaders(array(
                'Authorization'=>'Bearer '.$this->getToken(),
                "Content-Type"=> "application/json",
                "Accept-Language"=>"ru"
            ));
            if($type == 'get'){
                $res = $httpClient->get($url);
            }else{
                $res = $httpClient->post($url, Json::encode($data));
            }
            $this->setLastResponse($httpClient);
        }else{
            $this->setLastResponse(null, 'cache');
        }

        $result = new Result();
        if(!$res){
            $result->addError(
                new Error(Loc::getMessage('AWZ_YDELIVERY_YDAPI_RESPERROR'))
            );
        }else{
            try {
                if($httpClient && $httpClient->getHeaders()->get('content-type') == 'application/pdf'){
                    $result->setData(array('result'=>$res));
                }else{
                    if($this->standartJson){
                        $json = json_decode($res, true);
                    }else{
                        $json = Json::decode($res);
                    }
                    /*
                     * error -> array('code'=>'str', 'message'=>'str')
                     * */
                    if(isset($json['error']) && !is_array($json['error'])){
                        $result->addError(
                            new Error($json['error'])
                        );
                    }elseif(isset($json['code'],$json['message']) && $json['code'] && $json['message']){
                        $result->addError(
                            new Error($json['message'], $json['code'])
                        );
                    }elseif(isset($json['code'],$json['details']['debug_message']) && $json['code'] && $json['details']['debug_message']){
                        $result->addError(
                            new Error($json['details']['debug_message'], $json['code'])
                        );
                    }elseif(isset($json['error']['message']) && !empty($json['error']) && $json['error']['message']){
                        $result->addError(
                            new Error($json['error']['message'])
                        );
                        if(!empty($json['error']['details'])){
                            foreach($json['error']['details'] as $err){
                                $result->addError(
                                    new Error(is_array($err) ? implode('; ',$err) : $err)
                                );
                            }
                        }
                    }elseif(isset($json['error_details']) && !empty($json['error_details'])){
                        if(is_array($json['error_details'])){
                            foreach($json['error_details'] as $errText){
                                $result->addError(
                                    new Error($errText)
                                );
                            }
                        }else{
                            $result->addError(
                                new Error($json['error_details'])
                            );
                        }
                    }
                    $result->setData(array('result'=>$json));
                }

                /*if($type == 'get'){
                    $result->setData(array('result'=>$json));
                }else{
                    $result->setData(array('result'=>$json, 'postData'=>Json::encode($data)));
                }*/

            }catch (\Exception  $ex){
                $result->addError(
                    new Error($ex->getMessage(), $ex->getCode())
                );
            }
        }

        if($result->isSuccess() && $this->lastResponse){
            if($obCache){
                if($obCache->startDataCache()){
                    $obCache->endDataCache($res);
                }
            }
        }

        return $result;

    }

    /**
     * Получение последнего запроса
     *
     * @return null|HttpClient
     */
    public function getLastResponse(){
        return $this->lastResponse;
    }

    /**
     * Запись последнего запроса
     *
     * @param null $resp
     * @param string $type
     * @return HttpClient|null
     */
    private function setLastResponse($resp = null, $type=''){
        if($resp && !($resp instanceof HttpClient)){
            $resp = null;
        }
        $this->lastResponse = $resp;
        return $this->lastResponse;
    }

	public function setStandartJson($val){
		$this->standartJson = $val;
	}
}