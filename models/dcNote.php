<?php

require_once __DIR__ . '/../db_record_php_7/record.php';

class DcNote extends Record{

    const DRIVER = 'mysql';
    const DB = 'daily';
    const TABLE = 'dc_notes';
    const PRIMARYKEY = 'id';

    public $id;
    public $guid;
    public $gbl;
    public $flag;
    public $response;
    public $contact;
    public $note;
    public $followup_date;
    public $followup_time;
    public $email_body;
    public $email_recipients;
    public $sent;
    public $created_date;
    public $created_by;
    public $updated_date;
    public $updated_by;

    public function __construct($id = null)
    {
        parent::__construct(self::DRIVER,self::DB,self::TABLE,self::PRIMARYKEY,$id);
    }
}