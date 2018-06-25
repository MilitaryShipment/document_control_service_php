<?php


class CombinePpwk{

    protected $gbl_dps;
    protected $pythonFile;

    public function __construct($gbl_dps){
        $this->gbl_dps = $gbl_dps;
        $this->pythonFile = __DIR__ . '/python/oaPaperWork.py';
        $this->_combinePpwk();
    }
    protected function _combinePpwk(){
        $output = shell_exec("python " . $this->pythonFile . " " . escapeshellarg($this->gbl_dps));
        if($output){
            die(print_r(error_get_last()));
        }
    }
}
