<?php
namespace USQL\Library\Kafka;

use USQL\Library\Config;
use USQL\Library\Kafka\Kafka\Produce;

class producerAdapt
{

    public function send($topic, $msg, $hashKey = false)
    {
        if (is_array($msg)) {
            $msg = json_encode($msg);
        }
        
        $producer = Config::get('kafka.project');
        $logDir = $_SERVER['SITE_LOG_DIR'];
        if (! file_exists($logDir)) {
            mkdir($logDir);
        }
        
        $kafkaManagerHost = Config::get('kafka.msg_center_url');
        // 获取该消息的uuid
        $uuidResponse = $this->httpGet($kafkaManagerHost . "/open/getuuid?topic_name=$topic", 3);
        $uuidResponse = json_decode($uuidResponse, true);
        if (! $uuidResponse || $uuidResponse['code'] != 200) {
            // 记录接口调用失败日志
            error_log(date("Y-m-d H:i:s", time()) . " topic:$topic project:$producer message:$msg\n", 3, $logDir . "kafka_http_fail.log");
            return false;
        }
        $uuid = $uuidResponse['message'];
        // 记录消息备份日志
        // error_log(date("Y-m-d H:i:s", time()) . " topic:$topic project:$producer uuid:$uuid message:$msg\n", 3, $logDir . "kafka_message_backup.log");
        // 是否启用hash分区
        $sendMsg = [
            $msg
        ];
        if (Config::get('kafka.partition_hash_open') == 1) {
            if ($hashKey !== false) {
                $rs = service_kafka_producer::send($topic, $sendMsg, $uuid, Producer::PARTITION_HASH, $hashKey);
            } else {
                $rs = service_kafka_producer::send($topic, $sendMsg, $uuid, Producer::PARTITION_HASH, Config::get('kafka.partition_hash'));
            }
        } else {
            $rs = service_kafka_producer::send($topic, $sendMsg, $uuid);
        }
        return $rs;
    }

    private function httpPost($url, $postData, $retry = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $output = curl_exec($ch);
        curl_close($ch);
        
        if ($retry != 0 && ! $output) {
            $this->httpPost($url, $postData, $retry --);
        }
        
        return $output;
    }

    private function httpGet($url, $retry = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
        
        if ($retry != 0 && ! $output) {
            $this->httpGet($url, $retry --);
        }
        
        return $output;
    }
}

class service_kafka_producer
{

    protected static $_producer = null;

    public static function send($topic, $aData, $uuid, $type = Producer::PARTITION_RAND, $key = 0)
    {
        try {
            $aMsg = array(
                'uuid' => $uuid,
                'data' => $aData
            );
            $msg = json_encode($aMsg);
            if (self::$_producer === null) {
                self::$_producer = new Producer();
            }
            
            if ($type == Producer::PARTITION_RAND) {
                // 随机方式
                $bRet = self::$_producer->produce($topic, $msg, Producer::PARTITION_RAND, array(), - 1);
            } else {
                // hash方式
                $bRet = self::$_producer->produce($topic, $msg, Producer::PARTITION_HASH, array(
                    'key' => $key
                ), - 1);
            }
            return $bRet;
        } catch (\Exception $e) {
            log_error("Kafka", 'errno:' . KAFKA_EXCEPTION . ' Info: ' . $e->getMessage());
            return false;
        }
    }
}

class Producer
{

    /**
     * @def
     * 定义partition的选择方式,也可直接指定partion，不推荐
     */
    const PARTITION_HASH = - 1;

    // hash方式
    const PARTITION_RAND = - 2;

    // 随机方式
    
    /**
     *
     * @var zookeeper host list
     */
    private $_zk_hosts = '';

    /**
     *
     * @var broker host list
     */
    private $_broker_hosts = '';

    /**
     *
     * @var zk 的连接timeout
     */
    private $_zk_timeout = 1000;

    /**
     *
     * @var send msg的超时时间
     */
    private $_send_timeout = 100;

    /**
     * @note
     * 构造函数用于加载相关的配置
     */
    private $_ci;

    public function __construct()
    {
        $this->_zk_hosts = Config::get('kafka.zk_hosts');
        // $this->_broker_hosts = Config::get('kafka.broker_hosts'); //可选
        $this->_zk_timeout = Config::get('kafka.zk_timeout');
        $this->_send_timeout = Config::get('kafka.send_timeout');
        
        if (empty($this->_broker_hosts)) {
            $this->_broker_hosts = null;
        }
    }

    /**
     * @note
     * 获取发送消息的paritionid
     *
     * @param obj $produce
     *            获取到的生产者对象句柄
     * @param string $topic
     *            Kafka消息的topic名
     * @param int $partitionType
     *            定义选择的partion的方式
     * @param array $params
     *            用于hash方式的选择的附加参数主要是key ie: array('key' => oid), 必须将key值转为int
     *            
     * @return int|false
     */
    private function _getPartitionID(Produce $produce, $topic, $partitionType, $params, $reload = false)
    {
        // 获取指定topic的partition信息
        $partitions = $produce->getAvailablePartitions($topic, $reload);
        if (empty($partitions)) {
            return false;
        }
        $p_cnt = count($partitions);
        switch ($partitionType) {
            case self::PARTITION_HASH:
                if (! isset($params['key'])) {
                    return false;
                }
                return $partitions[intval($params['key']) % $p_cnt];
            case self::PARTITION_RAND:
                return $partitions[array_rand($partitions)];
            default:
                return $partitions[array_rand($partitions)];
        }
        return false;
    }

    /**
     * @note
     * 用于生产消息
     *
     * @param string $topic
     *            Kafka消息的topic名
     * @param mixed $msg
     *            将要发送的消息
     * @param int $partitionId
     *            散列规则or指定的partitionId
     * @param
     *            string hkey 散列key值,具体分布到哪个partition
     * @param
     *            int requiredAck 是否等待response default 0:不等待server response;-1:等待全部replica commit locate log后response;>0等待不超过全部replica comit locate log后response
     * @param int $timeout
     *            zk超时时间
     * @param int $retry
     *            可以重试的次数
     *            
     * @return mixed
     */
    public function produce($topic, $msg, $partitionType, $hkey, $requiredAck, $timeout = 0, $retry = 2, $reload = true)
    {
        $partitionId = false;
        if (empty($topic) || empty($msg) || 0 > $retry) {
            return false;
        }
        try {
            $produce = Produce::getInstance($this->_zk_hosts, $this->_zk_timeout, $this->_broker_hosts);
            $produce->clearPayload();
            $produce->setRequireAck($requiredAck);
            if (false === ($partitionId = $this->_getPartitionID($produce, $topic, $partitionType, $hkey, $reload))) {
                return false;
            }
            $timeout = empty($timeout) ? $this->_send_timeout : $timeout;
            $produce->setTimeOut($timeout);
            $messages = is_array($msg) ? $msg : array(
                $msg
            );
            foreach ($messages as $m) {
                if (! is_string($m)) {
                    return false;
                }
                $tmp = [
                    $m
                ];
                $produce->setMessages($topic, $partitionId, $tmp);
            }
            $res = $produce->send($reload);
            
            if ($requiredAck != 0) {
                if (empty($res) || $res[$topic][$partitionId]['errCode'] != 0) {
                    return self::produce($topic, $msg, $partitionId, $hkey, $requiredAck, $timeout, $retry - 1, true);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            return self::produce($topic, $msg, $partitionId, $hkey, $requiredAck, $timeout, $retry - 1, true);
        }
    }
}


