<?php

require_once __DIR__ . '/../db_record_php_7/record.php';

class OaPaperwork extends Record{
    const DRIVER = 'mssql';
    const DB = 'Sandbox';
    const TABLE = 'ref_oapaperwork';
    const PRIMARYKEY = 'id';

    public $id;
    public $guid;
    public $gbl_dps;
    public $form_name_1;
    public $form_location_1;
    public $form_entered_date_1;
    public $form_name_2;
    public $form_location_2;
    public $form_entered_date_2;
    public $form_name_3;
    public $form_location_3;
    public $form_entered_date_3;
    public $form_name_4;
    public $form_location_4;
    public $form_entered_date_4;
    public $form_completed_date;
    public $created_by;
    public $created_date;
    public $updated_by;
    public $updated_date;
    public $status_id;

    public function __construct($id = null)
    {
        parent::__construct(self::DRIVER,self::DRIVER,self::DB,self::TABLE,self::PRIMARYKEY,$id);
    }
    public static function get($key,$value){
        $data = array();
        $ids = array();
        $results = $GLOBALS['db']
            ->suite(self::DRIVER)
            ->driver(self::DRIVER)
            ->database(self::DB)
            ->table(self::TABLE)
            ->select(self::PRIMARYKEY)
            ->where($key,"=",$value)
            ->get();
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $ids[] = $row[self::PRIMARYKEY];
        }
        foreach($ids as $id){
            $data[] = new self($id);
        }
        return $data;
    }
}