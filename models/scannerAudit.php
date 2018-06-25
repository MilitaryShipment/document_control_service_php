<?php

require_once __DIR__ . '/../db_record_php_7/record.php';


class ScannerAuditFile extends Record{


    const DRIVER = 'mssql';
    const DB = 'Sandbox';
    const TABLE = 'tbl_scanner_audit';
    const PRIMARYKEY = 'id';

    public $id;
    public $guid;
    public $govshp;
    public $govdoc;
    public $gbl_dps;
    public $form_name;
    public $target_location;
    public $source_location;
    public $entered_date;
    public $created_by;
    public $created_date;
    public $updated_by;
    public $updated_date;
    public $status_id;

    public function __construct($id = null)
    {
        parent::__construct(self::DRIVER,self::DRIVER,self::DB,self::TABLE,self::PRIMARYKEY,$id);
    }
    private function _append($dailyMail = false){
        $appendage = "a";
        for($i = 0; $i < 10; $i++){
            if(!$dailyMail){
                $fileName = "/scan/fPImages/" . date("Y") . "/GOVDOC/" . $this->gbl_dps . "/" . $this->form_name . "-" . date("m") . "-" . date("d") . $appendage++ . ".pdf";
            }else{
                $this->dailyMailDest = "/scan/fPImages/" . date("Y") . "/DAILY_MAIL/" . $this->govshp . "/" . date("Y-m-d") . "/" . $this->gbl_dps . "/" . $this->form_name . "-" . date("m") . "-" . date("d") . $appendage++ . ".pdf";
            }
            if(!file_exists($fileName)){
                return $fileName;
            }
        }
        return false;
    }
    private function _switchDailyMail(){
        $dailyMailDest = "/scan/fPImages/" . date("Y") . "/DAILY_MAIL/" . $this->govshp . "/" . date("Y-m-d") . "/" . $this->gbl_dps . "/" . $this->form_name . "-" . date("m") . "-" . date("d") . ".pdf";
        switch ($this->govshp){
            case "Bucket":
                break;
            case "GovShp":
                break;
            case "Billing":
                break;
            case "Claims":
                if(!is_dir(dirname($dailyMailDest)) && !mkdir(dirname($dailyMailDest), 0777, true)){
                    $exceptionStr = 'Failed to mkdir ' . $dailyMailDest;
                    $error = error_get_last();
                    $exceptionStr .= "\n" . $error['message'];
                    throw new Exception($exceptionStr);
                }elseif(!copy($this->target_location,$dailyMailDest)){
                    $exceptionStr = 'Failed moving ' . $this->target_location . " to " . $dailyMailDest;
                    throw new Exception($exceptionStr);
                }
                break;
            default:
                throw new Exception('Invalid GOVSHP Type');
        }
        switch (strtolower($this->form_name)){
            case "d-at-d":
                break;
            case "d-at-d-copy":
                $rootDir = "/scan/fPImages/" . date("Y") . "/DAILY_MAIL/CLAIMS/";
                $todayDir = $rootDir . date("Y-m-d") . "/";
                $gblDir = $todayDir . $this->gbl_tops . "/";
                $dest_daily_mail_claims = $gblDir . $this->form_name . "-" . date("m") . "-" . date("d") . ".pdf";
                if(!is_dir($todayDir) && !mkdir($todayDir)){
                    $exceptionStr = 'Failed to mkdir ' . $todayDir;
                    $error = error_get_last();
                    $exceptionStr .= "\n" . $error['message'];
                    throw new Exception($exceptionStr);
                }elseif(!is_dir($gblDir) && !mkdir($gblDir)){
                    $exceptionStr = 'Failed to mkdir ' . $dest_daily_mail_claims;
                    $error = error_get_last();
                    $exceptionStr .= "\n" . $error['message'];
                    throw new Exception($exceptionStr);
                }elseif(!copy($this->target_location,$dest_daily_mail_claims)){
                    $exceptionStr = 'Failed to copy ' . $this->target_location . " " . $dest_daily_mail_claims;
                    $error = error_get_last();
                    $exceptionStr .= "\n" . $error['message'];
                    throw new Exception($exceptionStr);
                }
                break;
        }
        return $this;
    }
    private function _copyToShortGbl($source_file){
        $destination_file = "/scan/fPImages/" . date("Y") . "/GOVDOC/" . $this->gbl_tops . "/" . $this->form_name . "-" . date("m") . "-" . date("d") . ".pdf";
        if(!is_dir(dirname($destination_file)) && !mkdir(dirname($destination_file))){
            $error = error_get_last();
            $exceptionStr = 'Failed to mkdir ' . dirname($destination_file) . "\n" . $error['message'];
            throw new Exception($exceptionStr);
        }
        if(!copy($source_file,$destination_file)){
            $error = error_get_last();
            $exceptionStr = 'Failed To Copy' . $source_file . " to " . $destination_file . "\n" . $error['message'];
            throw new Exception($exceptionStr);
        }
        return $this;
    }
    public function xfer(){
        $source_file = preg_replace('/W:/', '/scan', $this->source_location);
        $source_file = preg_replace('/[\\\]/', '/', trim($source_file));
        $this->target_location = "/scan/fPImages/" . date("Y") . "/GOVDOC/" . $this->gbl_dps . "/" . $this->form_name . "-" . date("m") . "-" . date("d") . ".pdf";
        if(!is_dir(dirname($this->target_location))){
            if(!mkdir(dirname($this->target_location), 0777, true)){
                $exceptionStr = "Failed to mkdir " . $this->target_location;
                $error = error_get_last();
                $exceptionStr .= "\n" . $error['message'];
                throw new Exception($exceptionStr);
            }
        }
        if(file_exists($this->target_location)){
            $this->target_location = $this->_append();
            if(!$this->target_location){
                throw new Exception('Failed to generate Valid Destination File Name');
            }
        }
        if(!copy($source_file,$this->target_location)){
            $exceptionStr = 'Failed to move ' . $source_file;
            $error = error_get_last();
            $exceptionStr .= "\n" . $error['message'];
            throw new Exception($exceptionStr);
        }
        if(!unlink($source_file)){
            $exceptionStr = "Failed to unlink source doc " . $source_file;
            $error = error_get_last();
            $exceptionStr .= "\n" . $error['message'];
            throw new Exception($exceptionStr);
        }
        $this->_switchDailyMail()
            ->update();
        //No More Short GBLs on 1-9-2018
            //->_copyToShortGbl($this->target_location)
        return $this;
    }
}