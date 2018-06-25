<?php

require_once __DIR__ . '/../db_record_php_7/record.php';

class DCMissingItem extends Record{

    const DRIVER = 'mysql';
    const DB = 'daily';
    const TABLE = 'dc_missing_items';
    const PRIMARYKEY = 'id';

    public $id;
    public $gbl;
    public $missing_items;
    public $completed;
    public $date_completed;
    public $transdocs;
    public $hold_weight_tickets;
    public $demand_email_sent_today;
    public $pointer;
    public $locked;
    public $ignored;
    public $sent;
    public $date_sent;
    /*Dunno about form here:*/
    public $manually_sent_to_base;
    public $driverMsg;
    public $msg_sent_driver;
    public $msg_sent_driver_date;
    public $msg_sent_to;
    public $msg_sent;
    public $message;
    public $base_email_body;
    /*To here*/
    public $created_date;
    public $created_by;
    public $updated_date;
    public $updated_by;
    public $status_id;

    public function __construct($id = null)
    {
        parent::__construct(self::DRIVER,self::DRIVER,self::DB,self::TABLE,self::PRIMARYKEY,$id);
    }
    public static function get($key,$value,$option){
        $data = array();
        $ids = array();
        $GLOBALS['db']
            ->suite(self::DRIVER)
            ->driver(self::DRIVER)
            ->database(self::DB)
            ->table(self::TABLE)
            ->select(self::PRIMARYKEY)
            ->where($key,"=",$value);
        switch(strtolower($option)){
            case 'all':
                $results = $GLOBALS['db']->get();
                break;
            case 'active':
                $results = $GLOBALS['db']->andWhere("completed","=",0)->orderBy("pu_date,gbl")->get();
                break;
            case 'complete':
                $results = $GLOBALS['db']->andWhere("completed","=",1)->orderBy("pu_date,gbl")->get();
                break;
            default:
                throw new Exception('Invalid Missing Items Option');
        }
        while($row = mysqli_fetch_assoc($results)){
            $ids[] = $row[self::PRIMARYKEY];
        }
        foreach($ids as $id){
            $data[] = new self($id);
        }
        return $data;
    }
}