<?php


class CombinePpwk{

    protected $gbl_dps;

    public function __construct($gbl_dps){
        $this->gbl_dps = $gbl_dps;
        $this->_combinePpwk();
    }
    protected function _combinePpwk(){
        $output = shell_exec("python python/oaPaperWork.py " . escapeshellarg($this->gbl_dps));
        if($output){
            die(print_r(error_get_last()));
        }
    }
}
