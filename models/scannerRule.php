<?php

require_once __DIR__ . '/../db_record_php_7/record.php';


class ScannerRule extends Record{

    const DRIVER = 'mssql';
    const DB = 'Sandbox';
    const TABLE = 'tbl_scan_rules';
    const PRIMARYKEY = 'id';
    const DOMAIN = '@allamericanmoving.com';

    public $id;
    public $guid;
    public $rule_name;
    public $form_name;
    public $action;
    public $recipients;
    public $message;
    public $expiration_date;
    public $created_by;
    public $created_date;
    public $updated_by;
    public $updated_date;
    public $status_id;



    public function __construct($id = null)
    {
        parent::__construct(self::DRIVER,self::DRIVER,self::DB,self::TABLE,self::PRIMARYKEY,$id);
        if(!is_null($this->recipients)){
            $this->_parseRecipients();
        }
    }
    private function _parseRecipients($toArray = true){
        if($toArray){
            $str = $this->recipients;
            $this->recipients = array();
            $pieces = explode(";",$str);
            foreach($pieces as $piece){
                $this->recipients[] = $piece .= self::DOMAIN;
            }
        }else{
            $str = '';
            foreach($this->recipients as $recipient){
                $pieces = explode('@',$recipient);
                $str .= $pieces[0] . ";";
            }
        }
        return $this;
    }
    public function create(){
        $this->_parseRecipients(false);
        $reflection = new \ReflectionObject($this);
        $data = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $upData = array();
        foreach($data as $obj){
            $key = $obj->name;
            if($key == 'created_date' || $key == 'updated_date'){
                $upData[$key] = date("m/d/Y H:i:s");
            }elseif(!is_null($this->$key) && !empty($this->$key)){
                $upData[$key] = $this->$key;
            }
        }
        unset($upData['id']);
        $results = $GLOBALS['db']
            ->suite(self::DRIVER)
            ->driver(self::DRIVER)
            ->database(self::DB)
            ->table(self::TABLE)
            ->data($upData)
            ->insert()
            ->put();
        $this->_buildId()->_build();
        return $this;
    }
    public function update(){
        $this->_parseRecipients(false);
        $reflection = new \ReflectionObject($this);
        $data = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $upData = array();
        foreach($data as $obj){
            $key = $obj->name;
            if($key == 'updated_date'){
                $upData[$key] = date("m/d/Y H:i:s");
            }elseif(!is_null($this->$key) && !empty($this->$key)){
                $upData[$key] = $this->$key;
            }
        }
        if(isset($upData['created_date'])){
            unset($upData['created_date']);
        }
        unset($upData['id']);
        unset($upData['guid']);
        $results = $GLOBALS['db']
            ->suite(self::DRIVER)
            ->driver(self::DRIVER)
            ->database(self::DB)
            ->table(self::TABLE)
            ->data($upData)
            ->update()
            ->where(self::PRIMARYKEY,"=",$this->id)
            ->put();
        return $this;
    }
}
class ScannerRuleHistory extends Record{

    const DRIVER = 'mssql';
    const DB = 'Sandbox';
    const TABLE = 'tbl_scan_rules_history';
    const PRIMARYKEY = 'id';

    public $id;
    public $guid;
    public $rule_name;
    public $form_name;
    public $action;
    public $recipients;
    public $message;
    public $expiration_date;
    public $created_by;
    public $created_date;
    public $updated_by;
    public $updated_date;
    public $status_id;

    public function __construct($id = null)
    {
        parent::__construct(self::DRIVER,self::DB,self::TABLE,self::PRIMARYKEY,$id);
    }
}