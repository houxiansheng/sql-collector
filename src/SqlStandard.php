<?php
namespace USQL;

use USQL\Library\SqlRestraint\Restraint;
use USQL\Library\GoogleSqlParser\PHPSQLParser;
use USQL\Library\SqlRestraint\Common\HistorySql;
use USQL\Library\Kafka\producerAdapt;
use USQL\Library\Config;

class SqlStandard
{

    private static $self;

    private $phpSqlParser = null;

    private $restraint = null;

    private $extraInfo = [];

    private function __construct()
    {
        $this->phpSqlParser = new PHPSQLParser(false, false);
        $this->restraint = new Restraint();
    }

    public static function instance()
    {
        if (is_object(self::$self)) {
            return self::$self;
        }
        self::$self = new self();
        return self::$self;
    }
    /**
     * 收集项目信息
     *
     * @param string $dbName
     *            库名
     * @param string $query
     *            SQL语句（如select id from table_a where id=? 格式，如果不能拆分参数可以写整个sql语句）
     * @param string $bindings
     *            $query对应的绑定信息json格式,如果没有填写{}
     * @param int $takeTime
     *            执行sql消耗时间
     * @param int $execTime
     *            程序执行时间
     * @param string $pname
     *            项目名称
     * @param string $host
     *            访问域名，若脚本填写cript
     * @param string $uri
     *            访问域名，若脚本填写脚本相关标识
     * @param string $request
     *            请求参数$_REQUEST,json格式，若脚本传{}
     * @return boolean
     */
    public function collect($dbName, $query, $bindings, $takeTime, $execTime, $pname, $host, $uri, $request)
    {
        $data = [
            'db' => $dbName,
            'query' => $query,
            'bindings' => $bindings,
            'take_time' => $takeTime,
            'exec_time' => $execTime
        ];
        $extraInfo = [
            'pname' => $pname,
            'host' => $host,
            'uri' => $uri,
            'request' => $request
        ];
        HistorySql::write($data);
        $this->extraInfo = $extraInfo;
        return true;
    }

    /**
     * 直接分析sql，返回结果
     *
     * @param string $dbName            
     * @param string $sql            
     * @param array $extraInfo
     *            [
     *            'pname' => '项目名字（gap.youxinjinrong.com）',
     *            'host' => '域名（test.youxinjinrong.com）',
     *            'uri' => '访问路径（/test/redis）'
     *            ]
     * @return array [
     *         'code' => '错误码0:正常1：SQL语句异常',
     *         'errMsg' => '错误信息',
     *         'data' => [
     *         'parser' => 'sql解析后的结构',
     *         'msg' => [
     *         '不符合规范地方'
     *         ]
     *         ]
     *         ]
     */
    public function parser($sql)
    {
        try {
            $parser = $this->phpSqlParser->parse($sql, true);
            $res = $this->restraint->hander($parser);
            $return = [
                'code' => 0,
                'errMsg' => 'success',
                'data' => [
                    'parser' => $parser,
                    'msg' => $res
                ]
            ];
        } catch (\Exception $e) {
            $return = [
                'code' => 1,
                'errMsg' => $e->getMessage(),
                'data' => []
            
            ];
        }
        return $return;
    }
    public function delConst(&$parser){
        if(is_array($parser)){
            foreach ($parser as $key=>&$val){
                if(is_array($val)){
                    $this->delConst($parser[$key]);
                }else
                    if($key=='expr_type' && $val=='const'){
                        $parser['base_expr']='?';
                }
            }
        }
        return true;
    }
    public function __destruct()
    { // 发送统计好的sql信息
        $topicName = Config::get('kafka.topic');
        $sql = HistorySql::get();
        if (is_array($sql) && $sql) {
            try {
                $data = [
                    'extra' => json_encode($this->getExtraInfo()),
                    'sql' => json_encode($sql)
                ];
                // 临时替换为curl方式
                //$res = $this->sendCurl($data);
                 $producerAdapt = new producerAdapt();
                 $res = $producerAdapt->send($topicName, $data);
            } catch (\Exception $e) {}
        }
    }

    private function sendCurl($data)
    {
        $url = 'http://mysqlparser.com/api/kafka/sql';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private function getExtraInfo()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
        } elseif ($_SERVER['PHP_SELF'] == 'artisan') {
            $uri = '/' . implode('/', $_SERVER['argv']);
        } else {
            $uri = '';
        }
        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            $host = 'script';
        }
        $request = isset($_GET) ? $_GET : [];
        $request = isset($_POST) ? array_merge($request, $_POST) : $request;
        $uriArr = explode('?', $uri);
        $this->extraInfo['host'] = isset($this->extraInfo['host']) ? $this->extraInfo['host'] : $host;
        $this->extraInfo['request'] = json_encode($request);
        return $this->extraInfo;
    }
}