<?php

require_once __DIR__ . '/../msApi.php';
require_once __DIR__ . '/../db_record_php_7/record.php';
require_once __DIR__ . '/../models/scannerAudit.php';
require_once __DIR__ . '/../models/scannerForm.php';


class ScannerFiles{

    const UPLOADS = '/scan/';
    const AUDITTABLE = 'tbl_scanner_audit';
    const DBDRIVER = 'mssql';
    const DBDB = 'Sandbox';

    public $scannerFiles = array();
    private $csvFiles = array();
    private $debug;
    private $api;
    public $successCount;
    public $pendingCount;

    public function __construct($debug = true)
    {
        $this->debug = $debug;
        $this->api = new MSAPI();
        $this->successCount = 0;
        $this->_getUploadCsvs()
            ->_readUploadCsvs()
            ->_parseScannerFiles();
    }

    private function _getUploadCsvs(){
        $live = "/[*-]IndexLog.txt$/";
        $results = scandir(self::UPLOADS);
        foreach($results as $result){
            if($result == '.' || $result == '..'){
                continue;
            }elseif(preg_match($live,$result)){
                $this->csvFiles[] = self::UPLOADS . $result;
            }
        }
        return $this;
    }
    private function _readUploadCsvs(){
        foreach($this->csvFiles as $csvFile){
            $lines = array_values(file($csvFile));
            for($i = 0; $i < count($lines); $i++){
                $lines[$i] = mb_convert_encoding($lines[$i], 'US-ASCII', 'UTF-8');
                $this->scannerFiles[] = $lines[$i];
            }
            if(!$this->debug){
                if(file_put_contents($csvFile,'') === false){
                    $error = error_get_last();
                    throw new Exception($error['message']);
                }
            }
        }
        $this->pendingCount = count($this->scannerFiles);
        return $this;
    }
    private function _parseScannerFiles(){
        foreach($this->scannerFiles as $file){
            $fields = preg_split('/,/',$file);
            $gbl_dps = preg_replace('/["]/', '', $fields[2]);
            $gbl_dps = preg_replace('/-/', '', $gbl_dps);
            $file = new ScannerAuditFile();
            try{
                $shipment = $this->api->getShipment($gbl_dps);
                $file->reg_number = $shipment->registration_number;
            }catch(Exception $e){
                //echo $e->getMessage() . "\n";
            }
            $entered_date = preg_replace('/["]/', '', $fields[4]);
            if(!$entered_date){
                $entered_date = date('m/d/Y');
            }
            $location = preg_replace('/["]/', '', $fields[5]);
            $scanLocation = preg_replace('/W:/', '', $location);
            $scanLocation = preg_replace('/[\\\]/', '/', $scanLocation);
            $file->govshp = preg_replace('/["]/', '', $fields[0]);
            $file->govdoc = preg_replace('/["]/', '', $fields[1]);
            $file->gbl_dps = $gbl_dps;
            $file->form_name = trim(preg_replace('/["]/', '', $fields[3]));
            $file->target_location = $scanLocation;
            $file->source_location = $location;
            $file->entered_date = $entered_date;
            $file->created_by = "scannerFiles.php";
            $file->status_id = 1;
            if($this->debug){
                print_r($file);
            }else{
                $file->create();
            }
        }
        return $this;
    }
}