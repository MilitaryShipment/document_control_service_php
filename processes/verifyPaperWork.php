<?php

date_default_timezone_set("America/Chicago");

require_once __DIR__ . '/../msApi.php';
require_once __DIR__ . '/../db_record_php_7/record.php';
require_once __DIR__ . '/../models/oapaperwork.php';


class VerifyPaperWork{

    const NTS = "/NTS/i";
    const ROOT = '/scan/fPImages/';
    const YEARPAT = "/[0-9]{4}/";

    private $api;
    private $gbl;
    private $shipment;
    private $years = array();
    public $targetFiles = array();

    public function __construct($gbl)
    {
        $this->api = new MSAPI();
        $this->gbl = $gbl;
        $this->shipment = $this->api->getShipment($this->gbl);
        $this->getYears()
            ->buildTargetDocs();
    }
    private function getYears(){
        $results = scandir(self::ROOT);
        foreach($results as $result){
            if(is_dir(self::ROOT . $result) && preg_match(self::YEARPAT,$result)){
                $this->years[] = self::ROOT . $result . "/GOVDOC/";
            }
        }
        return $this;
    }
    private function buildTargetDocs(){
        if(preg_match(self::NTS,$this->shipment->pickup_type)){
            $this->targetFiles[] = "GBL-RATED";
            $this->targetFiles[] = "WEIGHTTICKETS";
            $this->targetFiles[] = "HOUSEHOLD";
        }else{
            $this->targetFiles[] = "GBL-RATED";
            $this->targetFiles[] = "WEIGHTTICKETS";
            $this->targetFiles[] = "HOUSEHOLD";
            $this->targetFiles[] = "DD619-ORIG";
        }
        return $this;
    }
    public function verifyDir($short = true){
        if($short){
            //todo there is currently no support for short gbls
            $gbl = $this->shipment->gbl_dps;
            //$gbl = $this->shipment->getShortGbl();
        }else{
            $gbl = $this->shipment->gbl_dps;
        }
        foreach($this->years as $year){
            $dir = $year . $gbl;
            if(is_dir($dir)){
                return $dir;
            }
        }
        return false;
    }
    public function verifyPpwk(){
        $dir = $this->verifyDir();
        if(!$dir){
            $dir = $this->verifyDir(false);
            if(!$dir){
                return false;
            }else{
//                echo $dir . "\n";
                $this->cyclePaperWork($dir);
            }
        }else{
//            echo $dir . "\n";
            $this->cyclePaperWork($dir);
        }
        return $this;
    }
    public function cyclePaperWork($dir){
        $results = scandir($dir);
        $indexes = array();
        foreach($this->targetFiles as $targetFile){
            $pattern = "/" . $targetFile . "/i";
            foreach($results as $result){
                if($targetFile == "WEIGHTTICKETS"){
                    if(preg_match('/HOLD/',$result)){
                        continue;
                    }elseif(preg_match('/NTS/',$result)){
                        continue;
                    }elseif(preg_match($pattern,$result)){
                        $indexes[] = array_search($targetFile,$this->targetFiles);
                    }
                }elseif(preg_match($pattern,$result)){
                    $indexes[] = array_search($targetFile,$this->targetFiles);
                }
            }
        }
        foreach($indexes as $index){
            unset($this->targetFiles[$index]);
        }
        return $this;
    }
    public function verifyForm($dir,$form){
        $formNames = array("GBL-RATED","WEIGHTTICKETS","HOUSEHOLD","DD619-ORIG");
        if(in_array($form,$formNames)){
            $pattern = "/" . $form . "/i";
        }else{
            throw new Exception('Invalid Form Name');
        }
        $results = scandir($dir);
        foreach($results as $result){
            if($form == "WEIGHTTICKETS"){
                if(preg_match('/HOLD/',$result)){
                    continue;
                }elseif(preg_match('/NTS/',$result)){
                    continue;
                }elseif(preg_match($pattern,$result)){
                    return $dir . "/" . $result;
                }
            }elseif(preg_match($pattern,$result)){
                return $dir . "/" . $result;
            }
        }
        return false;
    }
}
class RefPpwkVerification{

    const MSSQL = 'mssql';
    const SANDBOX = 'Sandbox';
    const OAPPWK = 'ref_oapaperwork';
    const MAYPATT = "/MAYF/i";

    private $api;
    private $gbl_dps;
    private $shipment;
    private $verification;
    public $ppwk;

    public function __construct($gbl_dps)
    {
        //todo record_number,created_by,status_id
        //echo "Verifing REF: " . $gbl_dps . "\n";
        $this->gbl_dps = $gbl_dps;
        $this->api = new MSAPI();
        $this->shipment = $this->api->getShipment($this->gbl_dps);
        $this->verification = new VerifyPaperWork($this->gbl_dps);
        if(!$this->_verifyOaPpwk()){
            $this->_buildOaRecord();
        }else{
            $this->_updateOaRecord();
        }
    }
    private function _verifyOaPpwk(){
        $ppwk = null;
        $ids = array();
        $results = $GLOBALS['db']
			->suite(self::MSSQL)
            ->driver(self::MSSQL)
            ->database(self::SANDBOX)
            ->table(self::OAPPWK)
            ->select("id")
            ->where("gbl_dps","=",$this->gbl_dps)
            ->get();
        if(!sqlsrv_num_rows($results)){
            $this->ppwk = new OaPaperwork();
            return false;
        }
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $ids[] = $row['id'];
        }
        foreach($ids as $id){
            $this->ppwk = new OaPaperwork($id);
        }
        return $this;
    }
    private function _buildOaRecord(){
        $this->_buildShipmentData()
            ->_buildFormNames()
            ->_buildFormLocations()
            ->_isComplete()
            ->_setStatus();
        $this->ppwk->create();
        return $this;
    }
    private function _updateOaRecord(){
        $this->_buildShipmentData()
            ->_buildFormNames()
            ->_buildFormLocations()
            ->_isComplete();
        $this->ppwk->update();
        return $this;
    }
    private function _buildShipmentData(){
        $this->ppwk->gbl_dps = $this->shipment->gbl_dps;
        $this->ppwk->scac = $this->shipment->scac;
        $this->ppwk->full_name = $this->shipment->full_name;
        $this->ppwk->pickup_date = $this->shipment->pickup_date;
        $this->ppwk->ogbloc = $this->shipment->gbloc_orig;
        $this->ppwk->area = $this->shipment->orig_gbloc_area;
        $this->ppwk->pickup_type = $this->shipment->pickup_type;
        if(preg_match(self::MAYPATT,$this->shipment->gbl_dps)){
            $this->ppwk->mayflower = 1;
        }else{
            $this->ppwk->mayflower = 0;
        }
        if($this->shipment->pickup_type == "HAUL ONLY" || $this->shipment->pickup_type == "HAULER ONLY"){
            $this->ppwk->haul_only = 1;
        }else{
            $this->ppwk->haul_only = 0;
        }
        return $this;
    }
    private function _buildFormNames(){
        $this->ppwk->form_name_1 = $this->verification->targetFiles[0];
        $this->ppwk->form_name_2 = $this->verification->targetFiles[1];
        $this->ppwk->form_name_3 = $this->verification->targetFiles[2];
        $this->ppwk->form_name_4 = $this->verification->targetFiles[3];
        return $this;
    }
    private function _buildFormLocations(){
        $dir = $this->verification->verifyDir(false);
        foreach($this->verification->targetFiles as $targetFile){
            switch ($targetFile){
                case "GBL-RATED":
                    $this->ppwk->form_location_1 = $this->verification->verifyForm($dir,$targetFile);
                    $this->ppwk->form_entered_date_1 = $this->_getFormCompleted($this->ppwk->form_location_1);
                    break;
                case "WEIGHTTICKETS":
                    $this->ppwk->form_location_2 = $this->verification->verifyForm($dir,$targetFile);
                    $this->ppwk->form_entered_date_2 = $this->_getFormCompleted($this->ppwk->form_location_2);
                    break;
                case "HOUSEHOLD":
                    $this->ppwk->form_location_3 = $this->verification->verifyForm($dir,$targetFile);
                    $this->ppwk->form_entered_date_3 = $this->_getFormCompleted($this->ppwk->form_location_3);
                    break;
                case "DD619-ORIG":
                    $this->ppwk->form_location_4 = $this->verification->verifyForm($dir,$targetFile);
                    $this->ppwk->form_entered_date_4 = $this->_getFormCompleted($this->ppwk->form_location_4);
                    break;
                default:
                    throw new Exception('Invalid Form Name');
            }
        }
        return $this;
    }
    private function _getFormCompleted($path){
        $time = filemtime($path);
        if(!$time){
            return false;
        }
        return date("m/d/Y H:i:s",$time);
    }
    private function _isComplete(){
        $dates = array();
        $dates[] = $this->ppwk->form_entered_date_1;
        $dates[] = $this->ppwk->form_entered_date_2;
        $dates[] = $this->ppwk->form_entered_date_3;
        if($this->ppwk->form_location_4){
            $dates[] = $this->ppwk->form_entered_date_4;
        }
        if(count($dates) == count($this->verification->targetFiles)){
            $this->ppwk->form_completed_date = max($dates);
        }
        return $this;
    }
    private function _setStatus(){
        //todo there is more to know about different Ids
        $this->ppwk->created_by = 'toap2';
        $this->ppwk->status_id = 1;
        return $this;
    }

}
