<?php

class DaPaperwork{

    const YEARPAT = "/[0-9]{4}/";
    const DATDPATTERN = '/D-At-D-/';
    const DD619PATTERN = '/DD619-Dest-/';
    const ROOT = '/scan/fPImages/';

    protected $years;
    protected $gbl_dps;

    public function __construct($gbl_dps){
        $this->gbl_dps = $gbl_dps;
        $this->_getYears();
    }
    private function _getYears(){
        $results = scandir(self::ROOT);
        foreach($results as $result){
            if(is_dir(self::ROOT . $result) && preg_match(self::YEARPAT,$result)){
                $this->years[] = self::ROOT . $result . "/GOVDOC/";
            }
        }
        return $this;
    }
    public function verifyDir(){
        foreach($this->years as $year){
            $dir = $year . $this->gbl_dps;
            if(is_dir($dir)){
                return $dir;
            }
        }
        return false;
    }
    public function verifyPpwk(){
        $dir = $this->verifyDir();
        $data = array('D-At-D','DD-619-DEST');
        if(!$dir){
            throw new \Exception('Paperwork Dir Does not Exist');
        }
        $results = scandir($dir);
        foreach($results as $result){
            if(preg_match(self::DATDPATTERN,$result)){
                unset($data[0]);
            }
            if(preg_match(self::DD619PATTERN,$result)){
                unset($data[1]);
            }
        }
        return $data;
    }
}