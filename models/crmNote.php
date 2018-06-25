<?php

require_once __DIR__ . '/../db_record_php_7/record.php';

class CrmNote extends Record{

    const DRIVER = 'mssql';
    const DB = 'Sandbox';
    const TABLE = 'tbl_crm_notes';
    const PRIMARYKEY = 'id';

    public $id;
    public $date_entered;
    public $date_modified;
    public $modified_user_id;
    public $created_by;
    public $name;
    public $filename;
    public $file_mime_type;
    public $parent_type;
    public $parent_id;
    public $contact_id;
    public $portal_flag;
    public $description;
    public $deleted;
    public $reg_contact_name;
    public $reg_contact_email;
    public $reg_contact_phone;
    public $category;
    public $subcategory;
    public $gbl_dps;

    public function __construct($id = null)
    {
        parent::__construct(self::DRIVER,self::DRIVER,self::DB,self::TABLE,self::PRIMARYKEY,$id);
    }

}