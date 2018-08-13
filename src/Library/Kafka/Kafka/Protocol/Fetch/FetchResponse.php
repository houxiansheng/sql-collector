<?php
/**
 * Created by PhpStorm.
 * User: wutishun
 * Date: 2017/2/23
 * Time: 10:32
 */
namespace USQL\Library\Kafka\Kafka\Protocol\Fetch;

use \USQL\Library\Kafka\Kafka\Protocol\Decoder;

class FetchResponse{
    //有效数据的存放数组
    public $value = array();
    //此次获取到的数据长度
    public $size;
    
    public function __construct($msg, $topic)
    {
        $msgTopic = substr($msg, 0, strlen($topic));
        if($msgTopic != $topic){
            //记录日志@todo
            return false;
        }

        $msgLength = strlen($msg);
        //去掉无用的头部
        $msg = substr($msg,strlen($topic) + 22, $msgLength - strlen($topic) - 22);

        while(true){
            if(strlen($msg) < 22){
                break;
            }
            //截掉无用部分
            $msg = substr($msg, 22, strlen($msg) - 22);
            //获取数据长度
            $msgLength = Decoder::unpack(Decoder::BIT_B32, substr($msg, 0, 4));
            $msgLength = array_shift($msgLength);
            //截掉数据长度数值
            $msg = substr($msg, 4, strlen($msg) - 4);
            //获取数据
            $this->value[] = substr($msg, 0, $msgLength);
            $msg = substr($msg, $msgLength, strlen($msg) - $msgLength);
        }
        $this->size = count($this->value);
    }

    public function getValue(){
        return $this->value;
    }

    public function getSize(){
        return $this->size;
    }

}