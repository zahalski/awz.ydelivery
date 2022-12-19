# AWZ: Яндекс Доставка

Интеграция с логистической платформой Яндекс Доставка для Cms Bitrix    
Маркетплейс 1с-Битрикс https://marketplace.1c-bitrix.ru/solutions/awz.ydelivery/

## 1. Установка

вариант 1    
Установите модуль с Маркетплейса 1с-Битрикс кнопкой "Установить"    
либо по ссылке https://<МОЙ_САЙТ>/bitrix/admin/update_system_partner.php?addmodule=awz.ydelivery    
    
вариант 2    
Скопируйте файлы модуля в папку /bitrix/modules/awz.ydelivery/    
Перейдите в админ панели: Маркетплейс - Установленные решения
   
версия для cp1251 в папке dist

## 2. События модуля

**OnCalcBeforeReturn**    
Позволяет переопределить или изменить результат расчета перед установкой даты доставки в свойство заказа

| Параметр | Описание |
| --- | --- |
| `shipment` `\Bitrix\Sale\Shipment` | Объект отгрузки
| `calcResult` `\Bitrix\Sale\Delivery\CalculationResult` | Объект расчета доставки

должен вернуть [`result` => `\Bitrix\Main\Result`] в случае ошибки    
[`disableWriteDate` => `bool`] отключить установку даты в свойство заказа
изменение данных расчета через объект `\Bitrix\Sale\Delivery\CalculationResult`

```php
//добавим 1-2 дня к сроку доставки если сегодня суббота или воскресенье

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    'awz.ydelivery', 'OnCalcBeforeReturn', 
    array('handlersDelivery','OnCalcBeforeReturn')
);

class handlersDelivery {

    public static function OnCalcBeforeReturn(\Bitrix\Main\Event $event){

        $result = new \Bitrix\Main\Result();
        $calc = $event->getParameter('calcResult');

        $addDay = 0;
        if(date("w") == 0){ //воскресенье
            $addDay += 1;
        }elseif(date("w") == 6){ //суббота
            $addDay += 2;
        }

        if($addDay){
            $fromDay = $calc->getPeriodFrom();
            if($fromDay>0){
                $fromDay += $addDay;
                $calc->setPeriodFrom($fromDay);
                $calc->setPeriodDescription($fromDay.' д.');
            }
            $toDay = $calc->getPeriodFrom();
            if($toDay>0 && $toDay!=$fromDay){
                $toDay += $addDay;
                $calc->setPeriodTo($toDay);
                $calc->setPeriodDescription($fromDay.'-'.$toDay.' д.');
            }
        }
        
        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            array('result'=>$result)
        );

    }

}
```

**changePrepareData**    
Позволяет переопределить подготовленную заявку в доставку

| Параметр | Описание |
| --- | --- |
| `result` `\Bitrix\Main\Result` | Объект с данными заявки |

должен вернуть [`result` => `\Bitrix\Main\Result`] с новыми данными либо ошибкой    
изменение данных возможно и через объект на входе `\Bitrix\Main\Result` с данными заявки

```php
//переопределим минимальный и максимальный срок доставки перед получением списка офферов (0-7 дней)
//вернем ошибку если сумма доставки в заказе меньше 500 рублей

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    'awz.ydelivery', 'changePrepareData', 
    array('handlersDelivery','changePrepareData')
);

class handlersDelivery {

    public static function changePrepareData(\Bitrix\Main\Event $event){

        $result = new \Bitrix\Main\Result();

        $prepareData = $event->getParameter('result')->getData();

        $orderId = $prepareData['info']['operator_request_id'];
        $order = \Bitrix\Sale\Order::load($orderId);
        if($order->getDeliveryPrice()<500){
            $result->addError(new \Bitrix\Main\Error('Неверная сумма доставки'));
        }else{
            $prepareData['destination']['interval']['from'] = strtotime(date('d.m.Y'));
            $prepareData['destination']['interval']['to'] += 7*86400;
            $result->setData($prepareData);
        }

        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            array('result'=>$result)
        );

    }

}
```

**onBeforeReturnBarCode**    
Позволяет переопределить сгенерированный штрих код места в посылке

| Параметр | Описание |
| --- | --- |
| `order` `\Bitrix\Sale\Order` | Объект заказа
| `barCode` `string` | Сгенерированный штрих код

должен вернуть [`barCode` => `string`] с новым штрих кодом

```php
//сделаем наш штрик код места в формате <UNIXTIME>-<НОМЕР ЗАКАЗА>

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    'awz.ydelivery', 'onBeforeReturnBarCode', 
    array('handlersDelivery','onBeforeReturnBarCode')
);

class handlersDelivery {

    public static function onBeforeReturnBarCode(\Bitrix\Main\Event $event){
        $result = new \Bitrix\Main\Result();
        
        $order = $event->getParameter('order');
        
        $newBarCode = time().'-'.$order->getId();

        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            array('barCode'=>$newBarCode)
        );
    }
}
```


**setOffer**    
Переопределяет метод выбора варианта доставки из списка офферов

| Параметр | Описание |
| --- | --- |
| `offers` `array` | $resp['result']['offers'] с ответа апи доставки |
| `order` `\Bitrix\Sale\Order` | Объект заказа |

Должен вернуть [`result` => `\Bitrix\Main\Result`] с новым оффером либо ошибку    

```php
//Будем проверять предложения только с датой отгрузки больше чем 2 дня от текущей
//Выберем минимальный по сроку доставки с учетом отгрузки выше

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    'awz.ydelivery', 'setOffer', 
    array('handlersDelivery','setOffer')
);

class handlersDelivery {

    public static function setOffer(\Bitrix\Main\Event $event){
        $result = new \Bitrix\Main\Result();
        $order = $event->getParameter('order');
        $offers = $event->getParameter('offers');

        if(!empty($offers['result']['offers'])){

            $findOffer = null;
            $minTime = time()+86400*30;
            foreach($offers['result']['offers'] as $offer){
                //пропускаем предложения с отгрузкой менее чем через 2 дня
                if(strtotime($offer['offer_details']['pickup_interval']['min']) < (time()+86400*2)){
                    continue;
                }
                if($minTime > strtotime($offer['offer_details']['delivery_interval']['min'])){
                    $minTime = strtotime($offer['offer_details']['delivery_interval']['min']);
                    $findOffer = $offer;
                }
            }

            if($findOffer){
                $result->setData(array('offer_id'=>$findOffer['offer_id']));
            }else{
                $result->addError(
                    new \Bitrix\Main\Error(
                        'Нет предложений по доставке в заказе '.$order->getId()
                    )
                );
            }

            return new \Bitrix\Main\EventResult(
                \Bitrix\Main\EventResult::SUCCESS,
                array('result'=>$result)
            );

        }

    }
    
}
```

**onBeforeReturnItems**    
Позволяет изменить параметры товаров в заявке

| Параметр | Описание |
| --- | --- |
| `order` `\Bitrix\Sale\Order` | Объект заказа |
| `items` `array` | Список товаров |

Должен вернуть [`items` => `array()`] с новым списком товаров  

```php
//добавим номер заказа в штрихкод товара в посылке (по умолчанию ид записи в корзине)

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    'awz.ydelivery', 'onBeforeReturnItems', 
    array('handlersDelivery','onBeforeReturnItems')
);

class handlersDelivery {

    public static function onBeforeReturnItems(\Bitrix\Main\Event $event){

        $items = $event->getParameter('items');
        $order = $event->getParameter('order');

        foreach($items as &$item){
            $item['barcode'] = $item['barcode'].'-'.$order->getId();
        }
        unset($item);

        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            array('items'=>$items)
        );

    }
}
```

**onBeforeStatusUpdate**    
Вызывается перед запуском механизма обновления статусов модулем

| Параметр | Описание |
| --- | --- |
| `order` `\Bitrix\Sale\Order` | Объект заказа |
| `ordStatus` `string` | Код текущего статуса заказа |
| `newOrdStatus` `string` | Расчитанный Код нового статуса заказа |
| `startStatus` `string` | Предыдущий код статуса логистической платформы |
| `newStatus` `string` | Текущий код статуса логистической платформы |

```php
//для отмены обновления статуса должен вернуть пустой статус

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    'awz.ydelivery', 'onBeforeStatusUpdate', 
    array('handlersDelivery','onBeforeStatusUpdate')
);

class handlersDelivery {

    public static function onBeforeStatusUpdate(\Bitrix\Main\Event $event){
        $newOrdStatus = $event->getParameter('newOrdStatus');        
        
        if($newOrdStatus == 'F') $newOrdStatus = false;

        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            array('newOrdStatus'=>$newOrdStatus)
        );
    }

}
```

```php
//для добавления ошибки в лог \Bitrix\Main\Result

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    'awz.ydelivery', 'onBeforeStatusUpdate', 
    array('handlersDelivery','onBeforeStatusUpdate')
);

class handlersDelivery {

    public static function onBeforeStatusUpdate(\Bitrix\Main\Event $event){
        $result = new \Bitrix\Main\Result();
        $result->addError(new \Bitrix\Main\Error('текст ошибки'));
        
        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            array('newOrdStatus'=>false, 'result'=>$result)
        );
    }

}

```

**onOfterStatusUpdate**    
Вызывается после механизма обновления статусов модулем

| Параметр | Описание |
| --- | --- |
| `order` `\Bitrix\Sale\Order` | Объект заказа |
| `ordStatus` `string` | Код текущего статуса заказа |
| `newOrdStatus` `string` | Расчитанный Код нового статуса заказа |
| `startStatus` `string` | Предыдущий код статуса логистической платформы |
| `newStatus` `string` | Текущий код статуса логистической платформы |
| `chekerUpdate` `array('unixtime', 'код статуса в битриксе')` | Текущий установленный код статуса заказа |
| `chekerUpdateErr` `array('string')` | Ошибки обновления статуса |

```php
//обработчик можно использовать для логирования

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    'awz.ydelivery', 'onOfterStatusUpdate', 
    array('handlersDelivery','onOfterStatusUpdate')
);

class handlersDelivery {

    public static function onOfterStatusUpdate(\Bitrix\Main\Event $event){
        $order = $event->getParameter('order');
        $chekerUpdateErr = $event->getParameter('chekerUpdateErr');
        if(!empty($chekerUpdateErr)){
            $message = 'order: '.$order->getId().', errors: '.implode('; ', $chekerUpdateErr);
            //записываем сообщение
        }
    }

}
```

**onBeforeShowListItems**    
Вызывается после механизма обновления статусов модулем

| Параметр | Описание |
| --- | --- |
| `params` `array()` | Конфигурация вывода списка |
| `custom` `bool` | Флаг указывающий на кастомный вывод страницы|

Должен вернуть [`params` => `array()`, `custom`=>`bool`] с новыми параметрами, либо флагом кастомного вызова

```php
//добавим отмену заказа в фильтр в списке заявок
$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler('awz.ydelivery', 'onBeforeShowListItems', array('handlersDelivery','onBeforeShowListItems'));
class handlersDelivery {

    public static function onBeforeShowListItems(\Bitrix\Main\Event $event){
        $params = $event->getParameter('params');
        $params['FIND'][] = array(
            "NAME"=>"ORD.CANCELED", "KEY"=>"ORD.CANCELED", "TITLE"=>"Заказ отменен", "GROUP"=>"ORD.CANCELED", "FILTER_TYPE"=>"=","TYPE"=>"LIST",
            "VALUES"=> array(
                'reference'=>array(
                    'все заказы',
                    'да',
                    'нет'
                ),
                'reference_id'=>array('','Y','N')
            )
        );
        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            array('params'=>$params)
        );
    }
}
```

<!-- cl-start -->
## История версий

**version 1.0.4**    
- исправлена ошибка отображения профиля в созданных доставках.    

**version 1.0.6**    
- изменение проверки подписи в main 20.200.300.    

**version 1.0.7**    
- добавлены обработчики в механизм смены статусов заказа;    
- исправлена ошибка не учета предыдущих статусов, если изменений больше чем 1.    

**version 1.0.8**    
- улучшена логика фильтрации заказов при обновлении статусов.    

**version 1.0.9**    
- изменен механизм подготовки данных для ручного оформления заказа;    
- добавлен вывод системной информации по запросу списка офферов;    
- исправления в языковых переменных;    
- добавлена опция отключения поиска адреса на карте (для работы требуется ключ сервиса Яндекс Карты).    

**version 1.0.10**    
- в обработчик OnCalcBeforeReturn добавлена обработка параметра RESULT как алиаса к result, параметра disableWriteDate, отключающего запись даты в свойство;    
- улучшения проверки идентификаторов при записи даты доставки в свойство;    
- добавлена возможность обновления информации о доставке и вывод дополнительных данных в заявку;    
- добавлены функции получения ярлыков и актов;    
- добавлен фильтр по статусу заказов, по варианту доставки в списке заявок;    
- добавлена ссылка на заказ в списке заявок;    
- добавлен обработчик onBeforeShowListItems для кастомизации страницы списка заявок.    

**version 1.0.11**    
- добавлена фильтрация по последнему статусу заявки в логистической платформе.    

**version 1.0.12**    
- добавлена поддержка множественных фильтров в список заявок.    

**version 1.0.13**    
- исправлена ошибка сохранения фильтра в списке заявок;    
- изменение логики синхронизации статусов, добавлен контроль дубликатов статусов (необходима перенастройка автоматизации в модуле);    
- добавлено больше информации по автоматизации в заявку;    
- добавлена возможность настройки автоматизации с истории в заявке;    
- изменен тип колонки со статусом в базе (varchar(255)).    

**version 1.0.14**    
- добавлена возможность задать свойство с координатами доставки;    
- улучшения проверки данных из заказа.    

**version 1.0.15**    
- улучшение поиска местоположений, фикс старых местоположений по ид (deprecated, будет удалено в будущих версиях).    

**version 1.0.16**    
- исправлена ошибка формирования штрихкода со случайными данными (добавлен статический кеш по номеру заказа, в рамках хита).    

**version 1.0.17**    
- исправлена ошибка не подстановки пустых параметров при ручной заявке в доставку;    
- исправлена ошибка получения кода местоположения при выборе ПВЗ в админке (дня нового импорта местоположений);    
- добавлен учет параметров максималдьных габаритов и веса при выводе доставки;    
- добавлено скрытие терминалов если габариты превышают допустимые, но все еще можно доставить до ПВЗ;    
- добавлена обработчка внешних ПВЗ и автоподстановка с модуля yandex.market;    
- добавлено получение габаритов товара с модуля торгового каталога в заявку.    

**version 1.0.18**    
- добавлен обработчик onAfterLocationNameCreate позволяющий переопределить название местоположение Битрикса перед расчетом;    
- исправлена ошибка кеша параметров доставки при нескольких профилях и пустом заказе;    
- исправлена ошибка получения параметров веса и объема по умолчанию при отсутствии доставки при расчете (баг в ядре);    
- добавлена опция в профили доставки (расчет сроков от с начала дня, от 00:00).    

**version 1.0.19**    
- добавлена опция логирования запросов в b_event_log;    
- добавлен статический кеш запросов с одинаковыми параметрами в рамках одного хита.    

**version 1.0.20**    
- запрет выбора ПВЗ всех типов, кроме terminal и pickup_point.    

**version 1.0.21**    
- конфликт в именовании переменных в обработчике onBeforeStatusUpdate.    

**version 1.0.22**    
- добавлено обновление сроков доставки после выбора пвз на карте;    
- изменен алгоритм подключения поиска адреса на карте (скрыта установка метки найденного адреса).    

**version 1.0.23**    
- hard fix, добавлено получение ид точки самовывоза для расчета стоимости доставки (временный отвал или изменения в апи Яндекса).    

**version 1.0.24**    
- исправлена ошибка сериализации заголовков HttpClient в логе, добавлен токен в статический кеш запросов (возможна ошибка, если аккаунты разные и одни параметры запроса).    

**version 1.0.25**    
- логика списка заявок вынесена в отдельный класс, для возможности наследования на обработчике кастомизации списка заявок.    

**version 1.0.26**    
- исправлена ошибка проверки лицензии в битриксе, добавлен параметр для добавления часов (часового пояса) к сроку доставки передаваемому в Яндекс Доставку (баг с часовыми поясами в Апи Яндекса).    

**version 1.0.27**    
- изменения в языковых переменных.    
<!-- cl-end -->