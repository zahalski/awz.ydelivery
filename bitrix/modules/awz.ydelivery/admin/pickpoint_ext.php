<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
global $APPLICATION;
$module_id = "awz.ydelivery";

use Bitrix\Main\Entity\Query;
use Bitrix\Main\Error;
use Bitrix\Main\IO\File;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Awz\Ydelivery\Helper;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Result;
use Bitrix\Main\Security;
use Awz\Ydelivery\OffersTable;
use Awz\Ydelivery\PvzTable;
use Awz\Ydelivery\PvzExtTable;
Loc::loadMessages(__FILE__);

Loader::includeModule($module_id);

function convertCsvString($str=''){
    if(mb_substr($str,0,1) == '"'){
        $str = mb_substr($str,1,-1);
    }
    $str = str_replace('""','"',$str);
    return trim($str);
}

$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($POST_RIGHT == "D")
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));

$APPLICATION->SetTitle(Loc::getMessage("AWZ_YDELIVERY_ADMIN_EXT_EDIT_TITLE"));

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && $POST_RIGHT == "W" && strlen($_REQUEST["Update"]) > 0)
{
    if($_REQUEST['DELETE_PVZ']=='Y'){
        PvzExtTable::deleteAll();
    }

    if($_REQUEST['filecsv']){
        $file = new File($_SERVER["DOCUMENT_ROOT"].$_REQUEST['filecsv']);

        //die();
        $delim = '';
        $cntLoaded = 0;
        $cntErr = 0;
        if($file->isExists()){
            $data = $file->getContents();
            $dataAr = explode("\n",$data);
            foreach($dataAr as $v){
                if(!$delim && strpos($v, ';')!==false) {
                    $delim = ';';
                }elseif(!$delim && strpos($v, ',')!==false){
                    $delim = ',';
                }
                if(!$delim) break;
                $arRow = explode($delim, $v);
                if(count($arRow)>1){
                    $pvzId = convertCsvString($arRow[0]);
                    $pvzExtId = convertCsvString($arRow[1]);
                    if($pvzId && $pvzExtId){
                        $resUp = PvzExtTable::addLink($pvzId, $pvzExtId);
                        if($resUp->isSuccess()){
                            $cntLoaded += 1;
                        }else{
                            $cntErr += 1;
                            if($cntErr < 11) {
                                CAdminMessage::ShowMessage(array(
                                    'TYPE'=>'ERROR',
                                    'MESSAGE'=>implode("; ", $resUp->getErrorMessages())
                                ));
                            }
                        }
                    }
                }
            }

            CAdminMessage::ShowMessage(array(
                'TYPE'=>'OK',
                'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_EXT_LOAD',array('#CNT#'=>$cntLoaded,'#ERR#'=>$cntErr))
            ));

        }
    }

}



$aTabs = array();
$aTabs[] = array(
    "DIV" => "edit1",
    "TAB" => Loc::getMessage('AWZ_YDELIVERY_ADMIN_EXT_OPT_SECT1'),
    "ICON" => "vote_settings",
    "TITLE" => Loc::getMessage('AWZ_YDELIVERY_ADMIN_EXT_OPT_SECT1')
);

$tabControl = new CAdminTabControl("tabControl", $aTabs);
$tabControl->Begin();
?>
<form method="POST" action="<?echo $APPLICATION->GetCurPage()?>?lang=<?=LANGUAGE_ID?>" id="FORMACTION">

<?
$tabControl->BeginNextTab();
?>
    <tr>
        <td width="50%"><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_EXT_DELPVZ')?></td>
        <td>
            <?
            $err = '';
            try {
                $main_query = new Query(PvzExtTable::getEntity());
                $main_query->registerRuntimeField("CNT", array('expression' => array('COUNT(*)', 'ID'), 'data_type' => 'integer'));
                $main_query->setSelect(array("CNT"));
                $main_query->setFilter(array());
                $result_chain = $main_query->setLimit(null)->setOffset(null)->exec();
                $result_chain = $result_chain->fetch();
                $cntRows = intval($result_chain['CNT']);
            }catch (Bitrix\Main\DB\SqlQueryException $e){
                $cntRows = 0;
                $err = $e->getMessage();
            }

            $main_query = new Query(PvzTable::getEntity());
            $main_query->registerRuntimeField("CNT",array('expression' => array('COUNT(*)','ID'), 'data_type'=>'integer'));
            $main_query->setSelect(array("CNT"));
            $main_query->setFilter(array());
            $result_chain = $main_query->setLimit(null)->setOffset(null)->exec();
            $result_chain = $result_chain->fetch();
            $cntRows2 = intval($result_chain['CNT']);
            ?>
            <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_EXT_COUNT',array('#EXT#'=>$cntRows,'#PVZ#'=>$cntRows2))?>
            <?
            if($err){
                CAdminMessage::ShowMessage(array('TYPE'=>'ERROR', 'MESSAGE'=>$err));
            }
            ?>
            <?if($cntRows){?>
            <?$val = "N";?>
            <input type="checkbox" value="Y" name="DELETE_PVZ" <?if ($val=="Y") echo "checked";?>>
            <?}else{
                CAdminMessage::ShowMessage(array(
                    'TYPE'=>'OK',
                    'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_EXT_COUNT_NULL')
                ));
                ?>
            <?}?>
        </td>
    </tr>

    <tr>
        <td><?=Loc::getMessage("AWZ_YDELIVERY_ADMIN_EXT_FILE")?>:</td>
        <td>
            <?$val = $_REQUEST['filecsv'];?>
            <?CAdminFileDialog::ShowScript(array(
                "event" => "filecsv",
                "arResultDest" => array("ELEMENT_ID" => "filecsv"),
                "arPath" => array("PATH" => GetDirPath(($val))),
                "select" => 'F',// F - file only, D - folder only
                "operation" => 'O',// O - open, S - save
                "showUploadTab" => true,
                "showAddToMenuTab" => false,
                "fileFilter" => 'csv',
                "allowAllFiles" => false,
                "SaveConfig" => true,
            ));?>
            <input
                name="filecsv"
                id="filecsv"
                type="text"
                value="<?echo htmlspecialcharsbx($val)?>"
                size="35">&nbsp;<input
                type="button"
                value="..."
                onClick="window.filecsv()"
            >
        </td>
    </tr>

    <?
    $tabControl->Buttons();
    ?>
    <input <?if ($POST_RIGHT<"W") echo "disabled" ?> type="submit" class="adm-btn-green" name="Update" value="<?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_EXT_BTN_SAVE')?>" />
    <input type="hidden" name="Update" value="Y" />
    <?$tabControl->End();
    ?>
</form>
<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
