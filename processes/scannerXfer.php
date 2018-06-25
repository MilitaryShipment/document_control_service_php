<?php

require_once __DIR__ . '/../msApi.php';
require_once __DIR__ . '/../db_record_php_7/record.php';
require_once __DIR__ . '/../models/scannerAudit.php';
require_once __DIR__ . '/../models/recEmail.php';
require_once __DIR__ . '/../models/webImage.php';
require_once __DIR__ . '/../models/oapaperwork.php';
require_once __DIR__ . '/../models/missingItem.php';
require_once __DIR__ . '/applyScannerRules.php';

class ScannerXfer{

    const AUDITTABLE = 'tbl_scanner_audit';
    const WEBIMAGETABLE = 'tbl_web_images';
    const RECEMAILTABLE = 'rec_email';
    const OAPAPERWORKTABLE = 'ref_oapaperwork';
    const MISSINGITEMSTABLE = 'dc_missing_items';
    const DBDRIVER = 'mssql';
    const DBDB = 'Sandbox';
    const MYDRIVER = 'mysql';
    const DAILYDB = 'daily';
    const PRIMARYTABLE = 'tbl_shipment_primary';
    const AGENTTABLE = 'tbl_agents';
    const SCANFORMTABLE = 'ctl_scan_form';

    private $api;
    private $pendingXfers = array();
    private $authorizedWebEnabled = array();
    private $currentXfer;
    private $dd619;

    public function __construct()
    {
        $this->api = new MSAPI();
        $this->_getWebEnabledAuthorized()
            ->_getNewRecords()
            ->_processXfers();
    }
    private function _getNewRecords(){
        $ids = array();
        $results = $GLOBALS['db']
            ->driver(self::DBDRIVER)
            ->database(self::DBDB)
            ->table(self::AUDITTABLE)
            ->select("id")
            ->where("status_id",">=",1)
            ->get();
        if(!sqlsrv_num_rows($results)){
            throw new Exception('No new Records to Xfer');
        }
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $ids[] = $row['id'];
        }
        foreach($ids as $id){
            $this->pendingXfers[] = new ScannerAuditFile($id);
        }
        return $this;
    }
    private function _processXfers(){
        foreach($this->pendingXfers as $pending){
            $this->currentXfer = $pending;
            if(!$this->_verifyGbl($this->currentXfer->gbl_dps)){
                continue;
            }else{
                try{
                    echo "Try Xfering: " . $this->currentXfer->gbl_dps . " " . $this->currentXfer->form_name . "\n";
                    $this->currentXfer->xfer();
                }catch(Exception $e){
                    echo $e->getMessage() . "\n";
                }
                if($this->currentXfer->govshp == 'Bucket'){
                    $this->_updateRecEmail();
                }
                if(!empty($this->currentXfer->reg_number) && !is_null($this->currentXfer->reg_number)){
                    $haulerCarrierId = $this->_getHaulerCarrierId();
                    if(!preg_match('/I/i',$haulerCarrierId)){
                        $this->_prepTransDocs();
                    }
                }
                if(in_array($this->currentXfer->form_name,$this->authorizedWebEnabled)){
                    $this->_insertWebImages();
                }
                $applied = new ApplyScannerRules($this->currentXfer->form_name,$this->currentXfer->gbl_dps,$this->currentXfer->target_location);
                echo "Scanner Rules Applied\n";
                $this->_updateOapaperwork()
                    ->_buildMissingItemsData()
                    ->_updateXferComplete();
                echo "Updates Complete\n";
            }
        }
        return $this;
    }
    private function _updateRecEmail(){
        $gbl = $this->currentXfer->gbl_dps;
        if(!preg_match('/^CL-/',$this->currentXfer->form_name)){
            if (preg_match('/([0-9]+)[\/]([0-9]+)[\/]([0-9]+)/', $this->currentXfer->entered_date, $match)) {
                $this->currentXfer->entered_date = $match[3] . '-' . $match[1] . '-' . $match[2];
            }
            $s = $this->api->getShipment($gbl);
            $r = new RecEmail();
            $r->gb = $this->currentXfer->gbl_tops;
            $r->gbl = $this->currentXfer->gbl_dps;
            $r->order_number = $this->currentXfer->reg_number;
            $r->doc_date = $this->currentXfer->entered_date;
            $r->doc_type = $this->currentXfer->form_name;
            $r->doc_path = $this->currentXfer->target_location;
            $r->rec_modified = date("Y-m-d H:i:s");
            $r->scanner_audit_id = $this->currentXfer->id;
            $r->member_name = $s->full_name;
            $r->create();
        }
        return $this;
    }
    private function _prepTransDocs(){
        return $this;
    }
    private function _getHaulerCarrierId(){
        $gbl = $this->currentXfer->gbl_dps;
        $s = $this->api->getShipment($gbl);
        $id = $s->hauler_carrier_id;
        if(empty($id) || is_null($id)){
            $id = "M0134";
        }
        return $id;
    }
    private function _getUnigroupDocType($formName){
        $results = $GLOBALS['db']
            ->driver(self::DBDRIVER)
            ->database(self::DBDB)
            ->table(self::SCANFORMTABLE)
            ->select("unigroup_doc_type")
            ->where("nullif(unigroup_doc_type,'')","IS NOT","NULL")
            ->andWhere("scan_name","=",$formName)
            ->andWhere("is_unigroup_transdocs_authorized","=","y")
            ->andWhere("status_id","=",1)
            ->get();
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $docType = $row['unigroup_doc_type'];
        }
        if(empty($docType) || is_null($docType)){
            return false;
        }
        return $docType;
    }
    private function _insertWebImages(){
        $w = new WebImage();
        $w->gbl_dps = $this->currentXfer->gbl_dps;
        $w->target_location = $this->currentXfer->target_location;
        $w->form_name = $this->currentXfer->form_name;
        $w->is_web_enabled = 1;
        $w->created_by = 'ScannerXfer.php';
        $w->create();
        return $this;
    }
    private function _updateOapaperwork(){
        $gbl = $this->currentXfer->gbl_dps;
        $id = $this->_oaPaperWorkExists($gbl);
        try{
            $fields = new stdClass();
            $s = $this->api->getShipment($gbl);
            $fields->gbl_dps = $s->gbl_dps;
            $fields->scac = $s->scac;
            $fields->ogbloc = $s->orig_gbloc_area;
            $fields->area = $s->orig_gbloc_area;
            $fields->pickup_date = $s->pickup_date;
            $fields->pickup_type = $s->pickup_type;
            $fields->full_name = $s->full_name;
            if (!preg_match('/NON-TEMP/i', $fields->pickup_type) || preg_match('/NTS/i', $fields->pickup_type)) {
                $this->dd619 = "";
            }else{
                $this->dd619 = "DD619-ORIG";
            }
            $fields->form_name_1 = 'GBL-RATED';
            $fields->form_name_2 = 'WEIGHTTICKETS';
            $fields->form_name_3 = 'HOUSEHOLD';
            $fields->form_name_4 = $this->dd619;
            if(!$id){
                $ppwk = new OaPaperwork();
                $ppwk->setFields($fields)->create();
            }else{
                $ppwk = new OaPaperwork($id);
                $ppwk->setFields($fields)->update();

            }
        }catch (Exception $e){
            echo $e->getMessage();
        }
        return $this;
    }
    private function _oaPaperWorkExists($gbl){
        $results = $GLOBALS['db']
            ->driver(self::DBDRIVER)
            ->database(self::DBDB)
            ->table(self::OAPAPERWORKTABLE)
            ->select('id')
            ->where("gbl_dps","=",$gbl)
            ->get();
        if(!sqlsrv_num_rows($results)){
            return false;
        }
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $id = $row['id'];
        }
        return $id;
    }
    private function _getWebEnabledAuthorized(){
        $results = $GLOBALS['db']
            ->driver(self::DBDRIVER)
            ->database(self::DBDB)
            ->table(self::SCANFORMTABLE)
            ->select("scan_name")
            ->where("is_web_authorized","=","Y")
            ->get();
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $this->authorizedWebEnabled[] = $row['scan_name'];
        }
        return $this;
    }
    private function _buildMissingItemsData(){
        $gbl = $this->currentXfer->gbl_dps;
        try{
            $s = $this->api->getShipment($gbl);
            $m = new DCMissingItem();
            $m->gbl = $gbl;
            $m->member_name = $s->full_name;
            $m->oa_id = $s->orig_agent_id;
            $m->da_id = $s->dest_agent_id;
            $m->pu_date = $s->pickup_date;
            $m->order_number = $s->registration_number;
            $m->scac = $s->scac;
            $m->hauler_id = $s->hauler_carrier_id;
            $m->hauler_agent_id = $s->hauler_agent_id;
            $oa = $this->_getAgentInfo($m->oa_id);
            $m->oa = $oa['name'];
            $m->oa_email = $oa['phone'];
            $m->oa_phone = $oa['email'];
            $da = $this->_getAgentInfo($m->da_id);
            $m->da = $da['name'];
            $m->da_email = $da['email'];
            $m->da_phone = $da['phone'];
            $hauler = $this->_getAgentInfo($m->hauler_id);
            $m->hauler = $hauler['name'];
            $m->hauler_email = $hauler['email'];
            $m->hauler_phone = $hauler['phone'];
            $ha = $this->_getAgentInfo($m->hauler_agent_id);
            $m->hauler_agent = $ha['name'];
            $m->hauler_agent_phone = $ha['phone'];
            if(preg_match('/^mayf[0-9]+/i',$gbl)){
                $m->completed = 1;
                $m->missing_items = "sent";
                $m->sent = 1;
                $m->manually_sent_to_base = 1;
            }else{
                $m->missing_items = "GBL-RATED WEIGHTTICKETS HOUSEHOLD $this->dd619";
                $m->completed = 0;
                $m->sent = 0;
                $m->manually_sent_to_base = 0;
            }
            $m->create();
        }catch(Exception $e){
            echo $e->getMessage();
        }
        return $this;
    }
    private function _getAgentInfo($agentId){
        $data = array();
        $results = $GLOBALS['db']
            ->driver(self::DBDRIVER)
            ->database(self::DBDB)
            ->table(self::AGENTTABLE)
            ->select("agent_name,phone_number,c2_contact3_email_name")
            ->where("agentid_number","=",$agentId)
            ->get();
        if(!sqlsrv_num_rows($results)){
            $data["name"] = "";
            $data["phone"] = "";
            $data["email"] = "";
        }else{
            while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
                $data["name"] = (empty($row["agent_name"]) || is_null($row["agent_name"]) ? "" : $row["agent_name"]);
                $data["phone"] = (empty($row["phone_number"]) || is_null($row["phone_number"]) ? "" : $row["phone_number"]);
                $data["email"] = (empty($row["c2_contact3_email_name"]) || is_null($row["c2_contact3_email_name"]) ? "" : $row["c2_contact3_email_name"]);
            }
        }
        return $data;
    }
    private function _updateXferComplete(){
        $this->currentXfer->status_id = 0;
        $this->currentXfer->updated_by = "ScannerXfer.php";
        $this->currentXfer->update();
        return $this;
    }
    private function _verifyGbl($gbl){
        try{
            $s = $this->api->getShipment($gbl);
        }catch(Exception $e){
            return false;
        }
        return true;
    }
}