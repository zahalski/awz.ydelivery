<?php

namespace Awz\Ydelivery\Api\Filters;

use Bitrix\Main\Security;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

class Sign extends Base {

    const ERROR_INVALID_PARAMS = 'invalid_sign';

    protected $keys = array();

    public function __construct(array $params = array())
    {
        $this->keys = $params;
        parent::__construct();
    }

    public function onBeforeAction(Event $event)
    {
        try {
            $signer = new Security\Sign\Signer;
            $params = $signer->unsign($this->action->getController()->getRequest()->get('signedParameters'));
            $params = unserialize(base64_decode($params));
        }catch (\Exception $e){
            $this->addError(new Error(
                'wrong signedParameters',
                self::ERROR_INVALID_PARAMS
            ));

            return new EventResult(EventResult::ERROR, null, null, $this);
        }


        if (empty($params))
        {
            $this->addError(new Error(
                'wrong signedParameters',
                self::ERROR_INVALID_PARAMS
            ));

            return new EventResult(EventResult::ERROR, null, null, $this);
        }


        $httpRequest = $this->getAction()->getController()->getRequest();
        if ($httpRequest)
        {
            $httpRequest->addFilter(new Request\Sign($this->getKeys(), $params));
        }

        return null;
    }

    public function getKeys(){

        return $this->keys;

    }

}