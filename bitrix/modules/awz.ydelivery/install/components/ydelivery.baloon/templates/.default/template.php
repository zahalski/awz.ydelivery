<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
use Bitrix\Main\Localization\Loc;
$item = $arResult['ITEM'];
?>
<div class="awz-yd-bln-wrap">

<?if($item['name']){?>
    <div class="awz-yd-bln-name"><b><?=$item['name']?></b></div>
<?}?>
<?if($arParams['HIDE_BTN']!='Y'){?>
<div>
    <a href="#" class="awz-yd-select-pvz" data-id="<?=$item['id']?>">
        <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_CHOISE')?>
    </a>
</div>
<?}?>

<?if($item['address']['full_address']){?>
    <div><b><?=Loc::getMessage('AWZ_YDELIVERY_BALOON_ADR')?></b>: <?=$item['address']['full_address']?></div>
<?}?>

<?if($item['contact']['phone']){?>
    <div><b><?=Loc::getMessage('AWZ_YDELIVERY_BALOON_PHONE')?></b>: <?=$item['contact']['phone']?></div>
<?}?>

<?if(!empty($item['schedule']['restrictions'])){?>
    <?$dayVariant = array(
        '',
        Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_1'),
        Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_2'),
        Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_3'),
        Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_4'),
        Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_5'),
        Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_6'),
        Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_7'),
    );?>
    <div class="awz-yd-bln-graf"><b><?=Loc::getMessage('AWZ_YDELIVERY_BALOON_TIME')?></b>:
    <?foreach($item['schedule']['restrictions'] as $rasp){?>
        <?$from = sprintf('%02d', $rasp['time_from']['hours']);
        $to = sprintf('%02d', $rasp['time_to']['hours']);
        //if($rasp['time_from']['minutes']!=0 || $rasp['time_to']['minutes']!=0){
        $from .= ':'.sprintf('%02d', $rasp['time_from']['minutes']);
        $to .= ':'.sprintf('%02d', $rasp['time_to']['minutes']);
        //}
        if($from === '00:00' && $to === '00:00') continue;
        ?>
        <?if(count($rasp['days']) == 7){?>
            <span class="awz-yd-bln-graf-days">
                <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL2',array('#FROM#'=>$from,'#TO#'=>$to))?>
            </span>
        <?}elseif(serialize($rasp['days']) == serialize(array(1,2,3,4,5,6))){?>
            <span class="awz-yd-bln-graf-days">
                <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL1',array('#FROM#'=>$dayVariant[1],'#TO#'=>$dayVariant[6]))?> <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL2',array('#FROM#'=>$from,'#TO#'=>$to))?>
            </span>
        <?}elseif(serialize($rasp['days']) == serialize(array(1,2,3,4,5))){?>
            <span class="awz-yd-bln-graf-days">
                <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL1',array('#FROM#'=>$dayVariant[1],'#TO#'=>$dayVariant[5]))?> <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL2',array('#FROM#'=>$from,'#TO#'=>$to))?>
            </span>
        <?}elseif(serialize($rasp['days']) == serialize(array(1,2,3,4))){?>
            <span class="awz-yd-bln-graf-days">
                <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL1',array('#FROM#'=>$dayVariant[1],'#TO#'=>$dayVariant[4]))?> <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL2',array('#FROM#'=>$from,'#TO#'=>$to))?>
            </span>
        <?}elseif(serialize($rasp['days']) == serialize(array(6,7))){?>
            <span class="awz-yd-bln-graf-days">
                <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL1',array('#FROM#'=>$dayVariant[6],'#TO#'=>$dayVariant[7]))?> <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL2',array('#FROM#'=>$from,'#TO#'=>$to))?>
            </span>
        <?}elseif(serialize($rasp['days']) == serialize(array(5,6))){?>
            <span class="awz-yd-bln-graf-days">
                <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL1',array('#FROM#'=>$dayVariant[5],'#TO#'=>$dayVariant[6]))?> <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL2',array('#FROM#'=>$from,'#TO#'=>$to))?>
            </span>
        <?}else{?>
            <?$dayText = array();
            foreach($rasp['days'] as $day){
                $dayText[] = $dayVariant[$day];
            }?>
            <span class="awz-yd-bln-graf-days"><?=implode(',',$dayText)?> <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_DAY_TMPL2',array('#FROM#'=>$from,'#TO#'=>$to))?></span>
        <?}?>
    <?}?>
    </div>
<?}?>

<?if($item['instruction']){?>
    <div><?=$item['instruction']?></div>
<?}?>
<?if(!empty($item['payment_methods'])){?>
    <div class="awz-yd-bln-pay">
    <?foreach($item['payment_methods'] as $code){?>
        <?if($code == 'already_paid'){?>
            <span class="awz-yd-bln-pay-paid">
                <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_PAY_1')?>
            </span>
        <?}elseif($code == 'card_on_receipt'){?>
            <span class="awz-yd-bln-pay-card">
                <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_PAY_2')?>
            </span>
        <?}elseif($code == 'cash_on_receipt'){?>
            <span class="awz-yd-bln-pay-cash">
                <?=Loc::getMessage('AWZ_YDELIVERY_BALOON_PAY_3')?>
            </span>
        <?}?>
    <?}?>
    </div>
<?}?>

</div>
<?php