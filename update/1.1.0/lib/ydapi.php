<?php
namespace Awz\Ydelivery;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class Ydapi {

    /**
     * Точка входа api Яндекс доставки (боевой режим)
     */
    const URL = 'https://b2b.taxi.yandex.net';

    const CACHE_TYPE_RESPONSE = 'cache';

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
     * может быть пустым в случае ответа с кеша
     * @var null|HttpClient
     */
    private $lastResponse = null;
    private $lastResponseType;

    private $cacheParams = array();

	private $standartJson = false;

	private static $staticCache = array();

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
     * Очистка параметров для кеша
     * должна вызываться после любого запроса через кеш
     */
    public function clearCacheParams(){
        $this->cacheParams = array();
    }

    /**
     * параметры для кеша результата запроса
     *
     * @param $cacheId string Ид кеша
     * @param $ttl int Время действия в секундах
     */
    public function setCacheParams($cacheId, $ttl){
        $this->cacheParams = array(
            'id'=>$cacheId,
            'ttl'=>$ttl
        );
    }

    public function cleanCache($cacheId=''){
        $obCache = Cache::createInstance();
        if(!$cacheId && $this->cacheParams && isset($this->cacheParams['id'])){
            $cacheId = $this->cacheParams['id'];
        }
        if($cacheId)
            $obCache->clean($cacheId, self::CACHE_DIR);
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
     * Отмена заявки экспресс
     *
     * @param string $offerId ид заявки
     * @return Result
     */
    public function offerCanselEx(string $offerId, string $state = 'free'){
        return $this->send('b2b/cargo/integration/v2/claims/cancel?claim_id='.$offerId, array('version'=>1, 'cancel_state'=>$state));
    }

    /**
     * Информация о заявке
     *
     * @param string $offerId ид заявки
     * @return Result
     */
    public function offerInfo(string $offerId){
        return $this->send('api/b2b/platform/request/info?request_id='.$offerId, array(), 'get');
    }

    /**
     * Информация о заявке экспресс
     *
     * @param string $offerId ид заявки
     * @return Result
     */
    public function offerInfoEx(string $offerId){
        return $this->send('b2b/cargo/integration/v2/claims/info?claim_id='.$offerId, [], 'post');
    }

    /**
     * Смена пвз
     *
     * @param string $offerId ид заявки
     * @param string $pvzId ид новой точки доставки
     * @return Result
     */
    public function reDeliveryInterval(string $offerId, string $pvzId, int $intervalFrom, int $intervalTo){
        $data = array(
            'destination'=>array(
                "platform_station"=>array(
                    "platform_id"=>$pvzId
                ),
                "type"=>"platform_station",
                //"type"=>37,
                /*'interval_utc' => array(
                    'from'=>date("c",$intervalFrom),
                    'to'=>date("c",$intervalTo)
                )*/
            ),
            'last_mile_policy'=>'self_pickup',
            'request_id'=>$offerId
        );
        //print_r($data);
        return $this->send('api/b2b/platform/request/redelivery_options',$data,'post');
    }

    /**
     * Смена пвз
     *
     * @param string $offerId ид заявки
     * @param string $pvzId ид новой точки доставки
     * @return Result
     */
    public function reDelivery(string $offerId, string $pvzId, int $intervalFrom, int $intervalTo){
        return $this->send('api/b2b/platform/request/edit',array(
            'destination'=>array(
                "platform_station"=>array(
                    "platform_id"=>$pvzId
                ),
                "type"=>"platform_station",
                //"type"=>1,
                'interval' => array(
                    'from'=>$intervalFrom,
                    'to'=>$intervalTo
                )
            ),
            'last_mile_policy'=>'self_pickup',
            'request_id'=>$offerId
        ),'post');
    }

    /**
     * История статусов заявки
     *
     * @param string $offerId ид заявки
     * @return Result
     */
    public function offerHistory(string $offerId){
        return $this->send('api/b2b/platform/request/history?request_id='.$offerId, array(),'get');
    }

    /**
     * Получение номера телефона курьера
     *
     * @param string $offerId ид заявки
     * @return Result
     */
    public function getCourierPhone(string $offerId){
        return $this->send('b2b/cargo/integration/v2/driver-voiceforwarding', array('claim_id'=>$offerId));
    }

    /**
     * История статусов заявок
     *
     * @param string $offerId ид последней записи журнала
     * @return Result
     */
    public function offersHistoryEx(string $cursor=''){
        if(!$cursor)
            return $this->send('b2b/cargo/integration/v2/claims/journal', '', 'raw_post');
        return $this->send('b2b/cargo/integration/v2/claims/journal', ['cursor'=>$cursor]);
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
     * Список экспресс предложений для параметров заказа
     *
     * @param array $data параметры заказа
     * @return Result
     */
    public function getOffersEx(array $data){
        return $this->send('b2b/cargo/integration/v2/offers/calculate', $data);
    }

    /**
     * Создание заявки в экспресс доставку
     *
     * @param array $data параметры заказа
     * @return Result
     */
    public function createOffersEx(array $data){
        $r_id = $data['request_id'];
        unset($data['request_id']);
        return $this->send('b2b/cargo/integration/v2/claims/create?request_id='.$r_id, $data);
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
     * Предварительная оценка без создания заявки
     *
     * @param array $data
     * @return Result
     */
    public function calcExpress(array $data){
        return $this->send('b2b/cargo/integration/v2/check-price', $data);
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
     * @param string $method метод апи
     * @param array $data параметры запроса
     * @param string $type post или get
     * @return Result
     * @throws \Bitrix\Main\ArgumentException
     */
    protected function send($method, $data = array(), $type='post'){

        $url = $this->isTest() ? self::SANDBOX_URL : self::URL;
        $url .= '/'.$method;

        $cacheStaticKey = md5(serialize(array($url,$method,$data,$type,$this->getToken())));
        if(isset(self::$staticCache[$cacheStaticKey])) return self::$staticCache[$cacheStaticKey];

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
			$headersPrepare = array(
                'Authorization'=>'Bearer '.$this->getToken(),
                "Content-Type"=> "application/json",
                "Accept-Language"=>"ru"
            );
            /*$httpClient->setHeaders($headersPrepare);*/
			foreach($headersPrepare as $keyHeader=>$valueHeader){
				$httpClient->setHeader($keyHeader, $valueHeader);
			}
            if($type == 'get'){
                $res = $httpClient->get($url);
            }elseif($type == 'raw_post'){
                $res = $httpClient->post($url, $data);
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
                        if(isset($json['error_details']) && is_array($json['error_details'])){
                            foreach($json['error_details'] as $keyCode=>$errVal){
                                if(is_array($errVal)) {
                                    foreach($errVal as $errText){
                                        $result->addError(
                                            new Error($errText, $keyCode)
                                        );
                                    }
                                }else{
                                    $result->addError(
                                        new Error($errVal, $keyCode)
                                    );
                                }
                            }
                        }else{
                            $result->addError(
                                new Error($json['message'], $json['code'])
                            );
                        }
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

        if($typeLog = Option::get(Handler::MODULE_ID, "ENABLE_LOG", "", "")){

            if($method == 'api/b2b/platform/pickup-points/list'){
                $typeLog = 'ERROR';
            }

            if($httpClient && (
                $typeLog === 'DEBUG' || ($typeLog === 'ERROR' && !$result->isSuccess())
                )
            )
            {
                \CEventLog::Add(array(
                        'SEVERITY' => (!$result->isSuccess()  ? 'ERROR' : 'DEBUG'),
                        'AUDIT_TYPE_ID' => 'RESPONSE',
                        'MODULE_ID' => Handler::MODULE_ID,
                        'DESCRIPTION' => serialize(
                            array(
                                'method'=>$method,
                                'data'=>$data,
                                'type'=>$type,
                                'errors'=>$result->getErrorMessages(),
                                'headers'=>$httpClient->getHeaders()->toArray(),
                                'result'=>$res
                            )
                        ),
                    )
                );
            }
            if(!$httpClient && ($typeLog === 'DEBUG')){
                \CEventLog::Add(array(
                        'SEVERITY' => 'DEBUG',
                        'AUDIT_TYPE_ID' => 'CACHE',
                        'MODULE_ID' => Handler::MODULE_ID,
                        'DESCRIPTION' => serialize(
                            array(
                                'method'=>$method,
                                'data'=>$data,
                                'type'=>$type,
                                'errors'=>$result->getErrorMessages(),
                                'headers'=>array(),
                                'result'=>$res
                            )
                        ),
                    )
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

        self::$staticCache[$cacheStaticKey] = $result;

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
     * Тип запроса
     * устанавливается в случае наличия кеша
     *
     * @return null|string
     */
    public function getLastResponseType(){
        return $this->lastResponseType;
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
            $this->lastResponseType = $type;
        }
        $this->lastResponse = $resp;
        return $this->lastResponse;
    }

    public function setStandartJson($val){
        $this->standartJson = $val;
    }
}