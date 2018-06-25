<?php

require_once __DIR__ . '/../msApi.php';
require_once __DIR__ . '/../db_record_php_7/record.php';
require_once __DIR__ . '/../models/scannerRule.php';

class ApplyScannerRules{

    const MSSQL = 'mssql';
    const SANDBOX = 'Sandbox';
    const RULES = 'tbl_scan_rules';
    const FROM = 'RuleEnforcement@allamericanmoving.com';
    const WEBADMIN = 'webadmin@allamericanmoving.com';

    private $form_name;
    private $gbl_dps;
    private $rules;
    private $shipment;
    public $noRules;

    public function __construct($form_name,$gbl_dps)
    {
        $this->api = new MSAPI();
        $this->noRules = false;
        $this->form_name = $form_name;
        $this->gbl_dps = $gbl_dps;
        $this->_findRules();
        if(!$this->noRules){
            $this->_notify();
        }
    }
    private function _findRules(){
        $results = $GLOBALS['db']
			->suite(self::MSSQL)
            ->driver(self::MSSQL)
            ->database(self::SANDBOX)
            ->table(self::RULES)
            ->select("id")
            ->where("form_name","like","%" . $this->form_name . "%")
//            ->where("gbl_dps like '%" . $this->gbl_dps . "%' or form_name like '%" . $this->form_name . "%'")
            ->andWhere("expiration_date > cast(getdate() as date)")
            ->andWhere("status_id = 1")
            ->get();
        if(!mssql_num_rows($results)){
            $this->noRules = true;
        }else{
            $this->shipment = $this->api->getShipment($this->gbl_dps);
            while($row = mssql_fetch_assoc($results)){
                $this->rules[] = new ScannerRule($row['id']);
            }
        }
        return $this;
    }
    private function _emptyWeight(){
        $gross = $this->shipment->gross_weight;
        $tare = $this->shipment->tare_weight;
        if((empty($gross) || is_null($gross)) && (empty($tare) || is_null($tare))){
            return true;
        }
        return false;
    }
    private function _notify(){
        foreach($this->rules as $rule){
            $msg = "A document you requested was scanned by document control.\n\n";
            $msg .= "GBL: " . $this->gbl_dps . "\n";
            $msg .= "Rule: " . $rule["rule_name"] . "\n";
            $msg .= "Form: " . $this->form_name . "\n";
            $msg .= "Expires: " . $rule["expiration_date"] . "\n\n";
            $msg .= $rule->message;
            $message = new stdClass();
            $message->send_to = $rule->recipients;
            $message->send_from = self::FROM;
            $message->fromName = self::FROM;
            $message->cc = self::WEBADMIN;
            $message->subject = $rule->rule_name . " - " . $this->gbl_dps . " " . $this->shipment->full_name;
            $message->body = $msg;
            $this->api->sendMessage($message);
            foreach($rule->recipients as $recipient){
                $this->_appendHistory($msg,$recipient,$rule->expiration_date);
            }
        }
        return $this;
    }
    private function _appendHistory($msg,$recipient,$expiration_date){
        $history = new ScannerRuleHistory();
        $history->action = 'Email';
        $history->recipients = $recipient;
        $history->message = $msg;
        $history->form_name = $this->form_name;
        $history->gbl_dps = $this->gbl_dps;
        $history->rule_name = $recipient;
        $history->expiration_date = $expiration_date;
        $history->create();
        return $this;
    }
}