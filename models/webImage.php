<?php

require_once __DIR__ . '/../db_record_php_7/record.php';

class WebImage extends Record{

    const DRIVER = 'mssql';
    const DB = 'Sandbox';
    const TABLE = 'tbl_web_images';
    const PRIMARYKEY = 'id';

    public $id;
    public $gbl_dps;
    public $claim_number;
    public $form_name;
    public $target_location;
    public $is_web_enabled;
    public $created_by;
    public $created_date;
    public $updated_by;
    public $updated_date;
    public $status_id;

    public function __construct($id = null)
    {
        parent::__construct(self::DRIVER,self::DRIVER,self::DB,self::TABLE,self::PRIMARYKEY,$id);
    }
}