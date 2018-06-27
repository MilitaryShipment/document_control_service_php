<?php

require_once __DIR__ . '/api.php';
require_once __DIR__ . '/processes/sendOaPaperWork.php';
require_once __DIR__ . '/processes/verifyPaperWork.php';
require_once __DIR__ . '/processes/combinePpwk.php';
require_once __DIR__ . '/processes/scannerFiles.php';
require_once __DIR__ . '/processes/scannerXfer.php';

class EndPoint extends API{

    public function __construct($request,$origin)
    {
        parent::__construct($request);
    }
    protected function example(){
        return array("endPoint"=>$this->endpoint,"verb"=>$this->verb,"args"=>$this->args,"request"=>$this->request);
    }
    protected function scanner(){
        $data = null;
        if($this->method != 'GET'){
            throw new \Exception('Resource only available through GET');
        }
        if(!isset($this->verb)){
            throw new \Exception('Resource requires an action be specified');
        }
        switch ($this->verb){
            case "read":
                $process = new ScannerFiles(false);
                $data = $process->scannerFiles;
                break;
            case "transfer":
                $process = new ScannerXfer();
                $data = 'success';
                break;
            default:
                throw new \Exception('Invalid Action');
        }
        return $data;
    }
    protected function paperwork(){
        $data = null;
        if($this->method != 'GET'){
            throw new \Exception('Resource only available through GET');
        }
        if(!isset($this->verb)){
            throw new \Exception('Resource requires an action be specified');
        }
        if(!isset($this->args[0])){
            throw new \Exception('Resource requires at least one argument');
        }
        switch ($this->verb){
            case "combine":
                $process = new CombinePpwk($this->args[0]);
                $data = 'success';
                break;
            case "send":
                $process = new OaResend($this->args[0]);
                $data = $process->sentMsg;
                break;
            case "verify":
                $process = new VerifyPaperWork($this->args[0]);
                $process->verifyPpwk();
                $data = $process->targetFiles;
                break;
            default:
                throw new \Exception('Invalid action');
        }
        return $data;
    }
    protected function note(){}
    protected function crmNote(){}
    protected function missingItem(){}
    protected function refOaPaperwork(){}
    protected function recemail(){}
    protected function scannerAudit(){}
    protected function scannerForm(){}
    protected function scannerRule(){}
    protected function webImage(){}



}
