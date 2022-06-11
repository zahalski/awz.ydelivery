<?php
namespace Awz\Ydelivery\Api\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter\Scope;
use Awz\Ydelivery\Api\Filters\Sign;

class pickPoints extends Controller
{
    //protected function listKeys

    public function configureActions()
    {
        return array(
            'list' => array(
                'prefilters' => array(
                    new Scope(Scope::AJAX),
                    new Sign(array('address','geo_id'))
                )
            )
        );
    }

    public static function listAction($address = '', $geo_id = '')
    {

        return [
            'address' => $address,
            'geo_id' => $geo_id
        ];
    }
}