<?php

require_once __DIR__ . '/../msApi.php';
require_once __DIR__ . '/../db_record_php_7/record.php';


class OaResend{

    const PATH = '/scan/silo/DocCon/oapaperwork/';
    const FROM = 'DocumentControl@allamericanmoving.com';

    public $sentMsg;

    private $api;
    private $gbl;
    private $dir;
    private $file;
    private $dirExsists;
    private $fileExists;
    private $template;
    private $recipient;
    private $pickupType;
    private $recipients = array(
        "DocumentControl@allamericanmoving.com",
        "webadmin@allamericanmoving.com"
    );

    public function __construct($gbl)
    {
        $this->api = new MSAPI();
        $this->gbl = $gbl;
        $this->dir = self::PATH . $this->gbl . "/";
        $this->file = $this->dir . $this->gbl . '_Docs.pdf';
        $this->template = new MessageTemplate();
        $this->recipient = new EmailInfo();
        $this->checkFile();
        if($this->fileExists){
            $this->getRecipient()
                ->getPickupType()
                ->getTemplate()
                ->send();
        }
    }
    private function checkFile(){
        if(is_dir($this->dir)){
            $this->dirExsists = true;
        }else{
            $this->dirExsists = false;
        }
        if(file_exists($this->file)){
            $this->fileExists = true;
        }else{
            $this->fileExists = false;
        }
        return $this;
    }
    private function getRecipient(){
        $results = $GLOBALS['db']
			->suite("mssql")		
            ->driver("mssql")
            ->database("sandbox")
            ->table("ref_oapaperwork o")
            ->select("o.gbl_dps,o.full_name,c.company_name,c.scac,c.phone,c.phone_fax,g.e_mail_billing,g.gbloc")
            ->innerJoin("sandbox.dbo.tbl_company c", "o.scac = c.scac")
            ->innerJoin("sandbox.dbo.tbl_gbloc g", "o.ogbloc = g.gbloc")
            ->where("o.gbl_dps","=",$this->gbl)
            ->get();
        if(!sqlsrv_num_rows($results)){
            $exceptionStr = 'Unable to find recipients for ' . $this->gbl;
            throw new Exception($exceptionStr);
        }
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            foreach($row as $key=>$value){
                $this->recipient->$key = $value;
            }
        }
        return $this;
    }
    private function getTemplate(){
        $results = $GLOBALS['db']
			->suite("mssql")		
            ->driver("mssql")
            ->database("sandbox")
            ->table("ctl_outbound_template")
            ->select("msg_from,msg_to,id,msg_cc,msg_bcc,msg_subject,msg_body")
            ->where("msg_name","=","oapaperwork_bases")
            ->get();
        if(!sqlsrv_num_rows($results)){
            throw new Exception('Unable to find message Template');
        }
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            foreach($row as $key=>$value){
                $this->template->$key = $value;
            }
        }
        if(!preg_match('/NTS/', $this->pickupType)){
            $nts = '4) One copy of DD Form 619 itemizing the accessorial services performed at origin.';
        }else{
            $nts = '';
        }
        $this->template->msg_subject = $this->_buildSubject();
        $this->template->msg_body = preg_replace('/{FULL_NAME}/s', ucwords(strtolower($this->recipient->full_name)), $this->template->msg_body);
        $this->template->msg_body = preg_replace('/{GBL_DPS}/s', $this->gbl, $this->template->msg_body);
        $this->template->msg_body = preg_replace('/{COMPANY_NAME}/s', ucwords(strtolower($this->recipient->company_name)), $this->template->msg_body);
        $this->template->msg_body = preg_replace('/{SCAC}/s', ucwords($this->recipient->scac), $this->template->msg_body);
        $this->template->msg_body = preg_replace('/{PHONE}/s', $this->gbl, $this->template->msg_body);
        $this->template->msg_body = preg_replace('/{PHONE_FAX}/s', $this->recipient->phone_fax, $this->template->msg_body);
        $this->template->msg_body = preg_replace('/{TYPE_NTS}/s', $nts, $this->template->msg_body);
        return $this;
    }
    private function _buildSubject(){
        $shipment= $this->api->getShipment($this->gbl);
        $subject = $this->template->msg_subject;
        $subject = preg_replace("/{LAST_NAME}/",$shipment->last_name,$subject);
        $subject = preg_replace("/{FIRST_INI}/",strtoupper($shipment->first_name[0]),$subject);
        $subject = preg_replace("/{GBL_DPS}/",strtoupper($shipment->gbl_dps),$subject);
        $subject = preg_replace("/{SCAC}/",strtoupper($shipment->scac),$subject);
        $subject = preg_replace("/{PU_DATE}/",date('m/d/Y',strtotime($shipment->pickup_date->date)),$subject);
        return $subject;
    }
    private function send(){
        $this->recipients[] = $this->recipient->e_mail_billing;
        $msg = array(
            "send_to"=>$this->recipients,
            "send_from"=>self::FROM,
            "fromName"=>self::FROM,
            "replyTo"=>self::FROM,
            "cc"=>array(),
            "bcc"=>array(),
            "subject"=>$this->template->msg_subject,
            "body"=>$this->template->msg_body,
            "attachments"=>array($this->file),
        );
        try{
            $this->sentMsg = $this->api->sendMessage($msg);
            die(print_r($this->sentMsg));
        }catch(Exception $e){
            throw new Exception($e->getMessage());
        }
        return $this;
    }
    private function getPickupType(){
        $results = $GLOBALS['db']
			->suite("mssql")		
            ->driver('mssql')
            ->database('Sandbox')
            ->table('tbl_shipment_primary')
            ->select('pickup_type')
            ->where("gbl_dps","=",$this->gbl)
            ->get();
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $this->pickupType = $row['pickup_type'];
        }
        return $this;
    }
}
class MessageTemplate{
    public $msg_body;
    public $msg_from;
    public $msg_to;
    public $id;
    public $msg_cc;
    public $msg_bcc;
    public $msg_subject;
}
class EmailInfo{
    public $gbl_dps;
    public $full_name;
    public $company_name;
    public $scac;
    public $phone;
    public $phone_fax;
    public $e_mail_billing;
    public $gbloc;
    public $area;
    public $fromName;
    public $fromScac;
}