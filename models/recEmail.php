<?php

require_once __DIR__ . '/../db_record_php_7/record.php';

class RecEmail extends Record{

    const DRIVER = 'mysql';
    const DB = 'daily';
    const TABLE = 'rec_email';
    const PRIMARYKEY = 'id';

    public $id;
    public $gbl;
    public $doc_type;
    public $doc_path;
    public $released;
    public $completed;
    public $date_completed;
    public $guid;
    public $created_by;
    public $create_date;
    public $updated_by;
    public $updated_date;
    public $status_id;

    public function __construct($id = null)
    {
        parent::__construct(self::DRIVER,self::DRIVER,self::DB,self::TABLE,self::PRIMARYKEY,$id);
    }
}