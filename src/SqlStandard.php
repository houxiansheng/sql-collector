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
     * 仅收集sql不分析
     * 
     * @param string $dbName            
     * @param string $sql            
     * @param array $extraInfo
     *            [
     *            'pname' => '项目名字（gap.youxinjinrong.com）',
     *            'host' => '域名（test.youxinjinrong.com）',
     *            'uri' => '访问路径（/test/redis）'
     *            ]
     * @return boolean
     */
    public function collect($dbName, $query, $bindings, array $extraInfo = [])
    {
        $data = [
            'db' => $dbName,
            'query' => $query,
            'bindings' => $bindings
        ];
        HistorySql::write($data);
        $this->extraInfo = $extraInfo;
        return true;
    }

    /**
     * 收集和分析sql
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
    public function handler($dbName, $query, $bindings, array $extraInfo = [])
    {
        $data = [
            'db' => $dbName,
            'query' => $query,
            'bindings' => $bindings
        ];
        HistorySql::write($data);
        $this->extraInfo = $extraInfo;
        try {
            $sql = str_replace("?", "'%s'", $query);
            $sql = vsprintf($sql, $bindings);
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
                $res = $this->sendCurl($data);
                // $producerAdapt = new producerAdapt();
                // $res = $producerAdapt->send($topicName, $data);
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
        if (isset($_SERVER['DOCUMENT_ROOT']) || isset($_SERVER['PWD'])) {
            $root = $_SERVER['DOCUMENT_ROOT'] ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];
            $dir = explode('/', __DIR__);
            $root = explode('/', $root);
            $array_intersect = array_intersect($dir, $root);
            $projectName = array_pop($array_intersect);
        } else {
            $projectName = '';
        }
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
        } elseif ($_SERVER['PHP_SELF'] == 'artisan') {
            $uri = '/' . implode('/', $_SERVER['argv']);
        } else {
            $uri = '';
        }
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            $host = 'script';
        }
        $uriArr = explode('?', $uri);
        $this->extraInfo['pname'] = isset($this->extraInfo['pname']) ? $this->extraInfo['pname'] : $projectName;
        $this->extraInfo['host'] = isset($this->extraInfo['host']) ? $this->extraInfo['host'] : $host;
        $this->extraInfo['uri'] = isset($this->extraInfo['uri']) ? $this->extraInfo['uri'] : $uriArr[0];
        return $this->extraInfo;
    }
}