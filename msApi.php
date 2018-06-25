<?php

class MSAPI{

    const MSSHIPMENTS = 'http://tonnage.militaryshipment.com:6660/';
    const LOCALSHIPMENTS = 'http://tonnage.militaryshipment.com:6661/';
    const MSGSERVICE = 'http://tonnage.militaryshipment.com:6662/';

    public function __construct(){}

    protected function _get($url){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $output = json_decode(curl_exec($ch));
        curl_close($ch);
        if(isset($output->error)){
            throw new \Exception($output->error);
        }
        return $output;
    }
    protected function _put(){}
    protected function _parsePostData($data){
        for($i = 0; $i < count($data['attachments']); $i++){
            $data['file' . $i] = new CURLFile(realpath($data['attachments'][$i]),$this->_getMimetype(realpath($data['attachments'][$i])));
        }
        $keys = [];
        if(is_array($data['send_to'])){
            $keys[] = 'send_to';
        }
        if(is_array($data['cc'])){
            $keys[] = 'cc';
        }
        if(is_array($data['bcc'])){
            $keys[] = 'bcc';
        }
        foreach($keys as $key){
            for($i = 0; $i < count($data[$key]); $i++){
                $data[$key . $i] = $data[$key][$i];
            }
        }
        return $data;
    }
    protected function _post($url,$data){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        if(is_array($data) && isset($data['attachments'])){
            $data = $this->_parsePostData($data);
            curl_setopt($ch,CURLOPT_POSTFIELDS, $data);
        }else{
            curl_setopt($ch,CURLOPT_POST, strlen(json_encode($data)));
            curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($data)))
            );
        }
        $output = json_decode(curl_exec($ch));
        curl_close($ch);
        if(isset($output->error)){
            throw new \Exception($output->error);
        }
        return $output;
    }
    protected function _getMimeType($file){
        $finfo = new finfo;
        return $finfo->file($file, FILEINFO_MIME);
    }

    public function getShipment($gbl){
        return $this->_get(self::LOCALSHIPMENTS . 'shipment/' . $gbl);
    }
    /*You can send messages as ASOC array or as objects.
    However, to send attachments your message MUST be ASOC array*/
    public function sendMessage($msgAssocArr){
        return $this->_post(self::MSGSERVICE . 'send/',$msgAssocArr);
    }
}