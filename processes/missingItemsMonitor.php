<?php

require_once __DIR__ . '/../db_record_php_7/record.php';
require_once __DIR__ . '/../models/missingItem.php';
require_once __DIR__ . '/verifyPaperWork.php';
require_once __DIR__ . '/sendOaPaperWork.php';
require_once __DIR__ . '/combinePpwk.php';


class MissingItemsMonitor{

    private $debug;

    public function __construct($debug = true)
    {
        $this->debug = $debug;
        $inc = $this->_getIncomplete();
        $i = 0;
        foreach($inc as $i){
            $ppwk = new VerifyPaperWork($i->gbl);
            $ppwk->verifyPpwk();
            if(count($ppwk->targetFiles)){
                echo $i->gbl .  " not Complete\n";
                $i->missing_items = $this->_stringifyDocs($ppwk->targetFiles);
                if(!$this->debug){
                    $i->update();
                }else{
                    echo $i->missing_items . "\n";
                }
            }else{
                echo $i->gbl .  " Complete\n";
                if(!$this->debug && $this->_combinePaperwork($i->gbl)){
                    sleep(1);
                    echo $i->gbl .  " Ppwk Combined\n";
                    try{
                        $ref = new RefPpwkVerification($i->gbl);
                        echo $i->gbl . " refOaPpwk verified\n";
                    }catch(Exception $e){
                        echo $e->getMessage() . "\n";
                    }
                    try{
                        $o = new OaResend($i->gbl);
                        echo $i->gbl .  " email sent\n";
                    }catch(Exception $e){
                        echo $e->getMessage() . "\n";
                    }
                    $i->missing_items = 'Sent';
                    $i->unscanned_documents = 0;
                    $i->completed = 1;
                    $i->sent = 1;
                    $i->update();
                    echo $i->gbl . " updated complete\n";
                }
            }
        }
    }
    private function _getIncomplete(){
        $rows = array();
        $now = date("Y-m-d");
        $results = $GLOBALS['db']
			->suite("mysql")
            ->driver("mysql")
            ->database("daily")
            ->table("dc_missing_items")
            ->select("id")
            ->where("completed","=",0)
            ->andWhere("canceled","=",0)
            ->andWhere("ignored","=",0)
            ->andWhere("haul_only","=",0)
            ->andWhere("on_hold","=",0)
            ->andWhere("sent","=",0)
            ->andWhere("gbl","not like","mayf%")
            ->andWhere("pu_date","<",$now)
            ->orWhere("(early_pickup","=",1)
            ->andWhere("completed","=","0)")
            ->get();
        if(!mysqli_num_rows($results)){
            throw new Exception('No Incomplete Shipments');
        }
        while($row = mysqli_fetch_assoc($results)){
            $rows[] = new DCMissingItem($row['id']);
        }
        if($this->debug){echo count($rows) . " records returned\n";}
        return $rows;
    }
    private function _stringifyDocs($docs){
        $str = '';
        foreach($docs as $doc){
            $str .= $doc . " ";
        }
        return $str;
    }
    private function _combinePaperwork($gbl){
        try{
            $combine = new CombinePpwk($gbl);
        }catch(\Exception $e){
            return false;
        }
        return true;
    }
}


/*Version 1 DO NOT USE. Left in case any methods are valuable*/
class MissingItemsMonitor2{

    const GOVDOC = '/scan/fPImages/*/GOVDOC/';
    const OAPPWK = '/scan/silo/DocCon/oapaperwork/';
    const REMOTESRV = 'http://10.25.33.146/combineOaPaperWork.php?gbl_dps=';


    public function __construct($debug = true)
    {
        $this->debug = $debug;
        $inc = $this->_getIncomplete();
        foreach($inc as $i){
            $missingDocs = $this->_parseMissingItems($i->missing_items);
            if($this->_verifyOaPpwkDir($i->gbl) && $this->_verifyOaPdf($i->gbl)){
                echo $i->gbl . " Combined Ppwk Exists\n";
                continue;
            }else{
                $docs = $this->_verifyGovDocDir($i->gbl);
                if(!$docs){
                    echo "Dir Does not exist\n";
                    continue;
                }else{
                    if($this->debug){
                        echo "Comparing: \n";
                        echo $i->gbl . "\n";
                        echo "**************\n";
                    }
                    $missingDocs = $this->_compareDocs($missingDocs,$docs);
                    if(!count($missingDocs)){
                        if($this->debug){echo "Compare Docs Returned empty\n";}
                        if(!$this->debug && $this->_combinePaperwork($i->gbl)){
                            sleep(1);
                            $o = new OaResend($i->gbl);
                            $i->missing_items = 'Sent';
                            $i->unscanned_documents = 0;
                            $i->completed = 1;
                            $i->sent = 1;
                            $i->update();
                        }else{
                            if(!$this->debug){echo "Fatal Error\n";}
                        }
                    }else{
                        if($this->debug){echo "Compare Docs returned array\n";print_r($missingDocs);}
                        $i->missing_items = $this->_stringifyDocs($missingDocs);
                        if(!$this->debug){$i->update();}else{echo $i->missing_items . "\n";}
                    }
                }
            }
        }
    }
    private function _getIncomplete(){
        $rows = array();
        $now = date("Y-m-d");
        $results = $GLOBALS['db']
            ->driver("mysql")
            ->database("daily")
            ->table("dc_missing_items")
            ->select("id")
            ->where("!completed")
            ->andWhere("!canceled")
            ->andWhere("!ignored")
            ->andWhere("!haul_only")
            ->andWhere("!on_hold")
            ->andWhere("!sent")
            ->andWhere("gbl not like 'mayf%'")
            ->andWhere("pu_date < '$now'")
            ->orWhere("(early_pickup = 1 and !completed)")
            ->get();
        if(!mysql_num_rows($results)){
            throw new Exception('No Incomplete Shipments');
        }else{
            while($row = mysql_fetch_assoc($results)){
                $rows[] = new DCMissingItem($row['id']);
            }
        }
        if($this->debug){echo count($rows) . " records returned\n";}
        return $rows;
    }
    private function _parseMissingItems($missingStr){
        $pieces = explode(' ',$missingStr);
        $docs = array();
        foreach($pieces as $piece){
            if(!empty($piece) && !is_null($piece) && $piece != ''){
                $docs[] = $piece;
            }
        }
        return $docs;
    }
    private function _verifyGovDocDir($gbl){
        $dir = self::GOVDOC . $gbl;
        $cmd = "ls " . $dir;
        $output = shell_exec("ls " . $dir);
        $docs = explode("\n",$output);
        if(count($docs) <= 1){
            return false;
        }
        return $docs;
    }
    private function _verifyOaPpwkDir($gbl){
        $dir = self::OAPPWK . $gbl;
        if(is_dir($dir)){
            return true;
        }
        return false;
    }
    private function _verifyOaPdf($gbl){
        $file = self::OAPPWK . $gbl . "/" . $gbl . "_Docs.pdf";
        if(is_file($file)){
            return true;
        }
        return false;
    }
    private function _compareDocs($missingDocs,$foundDocs){
        $indexes = array();
        foreach($missingDocs as $missing){
            $patt = "/" . $missing . "/i";
            foreach($foundDocs as $found){
                if($missing == 'WEIGHTTICKETS'){
                    if(preg_match('/HOLD/',$found)){
                        continue;
                    }elseif(preg_match('/NTS/',$found)){
                        continue;
                    }elseif(preg_match($patt,$found)){
                        $indexes[] = array_search($missing,$missingDocs);
                    }
                }elseif(preg_match($patt,$found)){
                    $indexes[] = array_search($missing,$missingDocs);
                }
            }
        }
        foreach($indexes as $index){
            unset($missingDocs[$index]);
        }
        return $missingDocs;
    }
    private function _stringifyDocs($docs){
        $str = '';
        foreach($docs as $doc){
            $str .= $doc . " ";
        }
        return $str;
    }
    private function _combinePaperwork($gbl){
        $url = self::REMOTESRV . $gbl;
        $results = file_get_contents($url);
        if($results){
            print_r($results);
            return false;
        }
        return true;
    }
}