<?php

require_once __DIR__ . '/../msApi.php';
require_once __DIR__ . '/../db_record_php_7/record.php';
require_once __DIR__ . '/../models/missingItem.php';


class RequestEmail{

    const MSSQL = 'mssql';
    const MYSQL = 'mysql';
    const MISSINGITEMS = 'dc_missing_items';
    const AGENTS = 'tbl_agents';
    const SANDBOX = 'Sandbox';
    const DAILY = 'daily';
    const REQUESTLETTER = '../letters/document_request.html.og';
    const STRUCTURE = 'structure';
    const AGENTEMAILS = 'vagent_email';
    const FROMADDR = 'documentcontrol@allamericanmoving.com';

    private $api;
    private $missingItemId;
    private $agentId;
    private $missingItem;

    public $template;
    public $agentName;
    public $recipients = array();

    public function __construct($missingItemId,$agentId)
    {
        $this->api = new MSAPI();
        if($this->_verifyId($missingItemId)){
            $this->missingItemId = $missingItemId;
        }else{
            die("Invalid Id");
        }
        if($this->_verifyAgent($agentId)){
            $this->agentId = $agentId;
        }else{
            die("Invalid Agent\n");
        }
        $this->gatherRecipients();
    }
    private function _verifyId($id){
        $results = $GLOBALS['db']
            ->driver(self::MYSQL)
            ->database(self::DAILY)
            ->table(self::MISSINGITEMS)
            ->select("*")
            ->where("id","=",$id)
            ->get();
        if(!mysqli_num_rows($results)){
            return false;
        }
        $this->missingItem = new DCMissingItem($id);
        return true;
    }
    private function _verifyAgent($agentId){
        $results = $GLOBALS['db']
            ->driver(self::MSSQL)
            ->database(self::SANDBOX)
            ->table(self::AGENTS)
            ->select("*")
            ->where("agentid_number","=",$agentId)
            ->get();
        if(!sqlsrv_num_rows($results)){
            return false;
        }
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $this->agentName = $row['full_legal_name'];
        }
        return true;
    }
    public function buildTemplate(){
        if($this->missingItem->g11_origin_date != 'U'){
            $g11Str = "(G11)";
        }else{
            $g11Str = "";
        }
        $this->template = file_get_contents(self::REQUESTLETTER);
        $this->template = preg_replace('/\{DATE\}/', date("m/d/Y"), $this->template);
        $this->template = preg_replace('/\{TIME\}/', date("H:i:s"), $this->template);
        $this->template = preg_replace('/\{G11\}/', $g11Str, $this->template);
        $this->template = preg_replace('/\{AGENT_ID\}/', $this->agentId, $this->template);
        $this->template = preg_replace('/\{AGENT_NAME\}/', $this->agentName, $this->template);
        $this->template = preg_replace('/\{GBL\}/', $this->missingItem->gbl, $this->template);
        $this->template = preg_replace('/\{ORDER_NUMBER\}/', $this->missingItem->order_number, $this->template);
        $this->template = preg_replace('/\{MEMBER_NAME\}/', $this->missingItem->member_name, $this->template);
        $this->template = preg_replace('/\{MISSING_ITEMS\}/', $this->missingItem->missing_items, $this->template);
        return $this;
    }
    private function gatherRecipients(){
        $results = $GLOBALS['db']
            ->driver(self::MYSQL)
            ->database(self::STRUCTURE)
            ->table(self::AGENTEMAILS)
            ->select("id,agentid,email")
            ->where("agentid","=",$this->agentId)
            ->get();
        if(!mysqli_num_rows($results)){
            $exceptionStr = "No Addresses found for Agent: " . $this->agentId;
            throw new Exception($exceptionStr);
        }
        while($row = mysqli_fetch_assoc($results)){
            $this->recipients[] = $row['email'];
        }
        return $this;
    }
    public function appendRecipients($recipients){
        $i = 0;
        foreach($recipients as $recipient){
            if(!in_array($recipient,$this->recipients)){
                $this->recipients[] = $recipient;
            }
        }
        foreach($this->recipients as $recipient){
            if(!in_array($recipient,$recipients)){
                unset($this->recipients[$i]);
            }
            $i++;
        }
        return $this;
    }
    public function send(){
        $msg = new stdClass();
        $msg->send_to = $this->recipients;
        $msg->send_from = self::FROMADDR;
        $msg->subject = $this->missingItem->member_name . ' ' . $this->missingItem->gbl . ' Paperwork Request';
        $msg->body = $this->template;
        $response = $this->api->sendMessage($msg);
        return $this;
    }
}