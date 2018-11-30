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
    public function replaceConst($sql)
    {
        $sql = ' ' . $sql . ' ';
        // 替换换行，空格
        $symbolsReplace = $this->specialSymbolsReplace();
        $sql = preg_replace(array_keys($symbolsReplace), array_values($symbolsReplace), $sql);
        try {
            $parser = $this->phpSqlParser->parse($sql, true);
        } catch (\Exception $e) {
            $parser = [];
        }
        $constArr = [];
        if (is_array($parser)) {
            $this->getConst($parser, $constArr);
            if ($constArr) {
                $pattern = '/(.*)' . implode('(.*)?', $constArr) . '(.*)?/';
                preg_match($pattern, $sql, $match);
                array_shift($match);
                $sql = implode(' ? ', $match);
            }
        }
        $sql = preg_replace(array_keys($symbolsReplace), array_values($symbolsReplace), $sql);
        $return = [
            'sql' => trim($sql),
            'list' => $constArr
        ];
        return $return;
    }
    /**
     * 删除常量
     *
     * @param unknown $sql
     * @return array [
     *         'sql' => '替换后sql',
     *         'list' => [
     *         '匹配的常量']
     *         ]
     */
    public function delConst($sql)
    {
        $sql = ' ' . $sql . ' ';
        // 替换换行，空格
        $symbolsReplace = $this->specialSymbolsReplace();
        $sql = preg_replace(array_keys($symbolsReplace), array_values($symbolsReplace), $sql);
        try {
            $parser = $this->phpSqlParser->parse($sql, true);
        } catch (\Exception $e) {
            $parser = [];
        }
        $constArr = [];
        if (is_array($parser)) {
            $this->getConst($parser, $constArr);
            $pattern = '/(.*)' . implode('(.*)?', $constArr) . '(.*)?/';
            preg_match($pattern, $sql, $match);
            array_shift($match);
            $sql = implode('', $match);
        }
        $compare = array_merge($this->operatorReplace(), $this->specialSymbolsReplace());
        $sql = preg_replace(array_keys($compare), array_values($compare), $sql);
        $sql = trim(implode(' ', array_unique(explode(' ', $sql))));
        $return = [
            'sql' => $sql,
            'list' => array_unique($constArr)
        ];
        return $return;
    }
    
    /**
     * 删除保留字段
     *
     * @param unknown $sql
     * @return array [
     *         'sql' => '替换后sql',
     *         'list' => [
     *         '匹配到保留字'
     *         ]
     *         ]
     */
    public function delReservedField($sql)
    {
        $sql = ' ' . $sql . ' ';
        $pattern = array_merge($this->reservedFieldReplace(), $this->specialSymbolsReplace());
        $newSql = preg_replace(array_keys($pattern), array_values($pattern), $sql);
        $sqlArr = explode(' ', $sql);
        $newSqlArr = explode(' ', $newSql);
        $diff = array_diff($sqlArr, $newSqlArr);
        $return = [
            'sql' => trim($newSql),
            'list' => array_unique($diff)
        ];
        return $return;
    }
    
    private function operatorReplace()
    {
        $fields = [
            '/,/' => ' ',
            '/(`|;|\*)/' => '',
            '/( ){1,}and( ){1,}/' => ' ',
            '/( ){1,}or( ){1,}/' => ' ',
            '/\(/' => '',
            '/\)/' => '',
            '/\?/' => '',
            '/_/' => '',
            '/\./' => '',
            '/( ){0,}>( ){1,}=/' => 'ggttee ',
            '/( ){0,}<( ){1,}=/' => 'llttee ',
            '/( ){0,}!( ){1,}=/' => 'nneeqq ',
            '/( ){0,}<( ){1,}>/' => 'nneeqq ',
            // '/( ){1,}=/' => '= ',
            '/( ){0,}=/' => 'eeqq ',
            '/( ){0,}>/' => 'gggtt ',
            '/( ){0,}</' => 'llee ',
            '/( ){1,}is( ){1,}null/' => 'isnull ',
            '/( ){1,}is( ){1,}not( ){1,}null/' => 'isnotnull ',
            '/( ){1,}between/' => 'between ',
            '/( ){1,}not( ){1,}in/' => 'notin ',
            '/( ){1,}in( ){1,}/' => 'in ',
            '/( ){1,}not( ){1,}like/' => 'notlike ',
            '/( ){1,}like/' => 'like ',
            '/( ){1,}regexp/' => 'regexp '
        ];
        return $fields;
    }
    
    private function specialSymbolsReplace()
    {
        $fields = [
            '/\n/' => ' ',
            '/( ){2,}/' => ' '
        ];
        return $fields;
    }
    
    private function reservedFieldReplace()
    {
        $fields = [
            '/( ){1,}desc( ){1,}/' => ' ',
            '/( ){1,}group( ){1}by( ){1,}/' => ' ',
            '/( ){1,}record( ){1,}/' => ' ',
            '/( ){1,}show( ){1,}/' => ' ',
            '/( ){1,}values( ){1,}/' => ' ',
            '/( ){1,}columndefinition( ){1,}/' => ' ',
            '/( ){1,}describe( ){1,}/' => ' ',
            '/( ){1,}having( ){1,}/' => ' ',
            '/( ){1,}referencedefinition( ){1,}/' => ' ',
            '/( ){1,}sqlchunk( ){1,}/' => ' ',
            '/( ){1,}where( ){1,}/' => ' ',
            '/( ){1,}column( ){1,}/' => ' ',
            '/( ){1,}drop( ){1,}/' => ' ',
            '/( ){1,}index( ){1,}/' => ' ',
            '/( ){1,}indexes( ){1,}/' => ' ',
            '/( ){1,}rename( ){1,}/' => ' ',
            '/( ){1,}sql( ){1,}/' => ' ',
            '/( ){1,}createdefinition( ){1,}/' => ' ',
            '/( ){1,}duplicate( ){1,}/' => ' ',
            '/( ){1,}insert( ){1,}/' => ' ',
            '/( ){1,}replace( ){1,}/' => ' ',
            '/( ){1,}table( ){1,}/' => ' ',
            '/( ){1,}create( ){1,}/' => ' ',
            '/( ){1,}explain( ){1,}/' => ' ',
            '/( ){1,}into( ){1,}/' => ' ',
            '/( ){1,}select( ){1,}/' => ' ',
            '/( ){1,}union( ){1,}/' => ' ',
            '/( ){1,}default( ){1,}/' => ' ',
            '/( ){1,}on( ){1,}/' => ' ',
            '/( ){1,}limit( ){1,}/' => ' ',
            '/( ){1,}select( ){1,}/' => ' ',
            '/( ){1,}update( ){1,}/' => ' ',
            '/( ){1,}delete( ){1,}/' => ' ',
            '/( ){1,}from( ){1,}/' => ' ',
            '/( ){1,}order( ){1}by( ){1,}/' => ' ',
            '/( ){1,}set( ){1,}/' => ' ',
            '/( ){1,}using( ){1,}/' => ''
        ];
        return $fields;
    }
    
    private function getConst($parser, &$constArr)
    {
        if (is_array($parser)) {
            foreach ($parser as $key => &$val) {
                if ($key === 'LIMIT') {
                    is_numeric($val['offset']) && array_push($constArr, $val['offset']);
                    is_numeric($val['rowcount']) && array_push($constArr, $val['rowcount']);
                } elseif (is_array($val)) {
                    $this->getConst($parser[$key], $constArr);
                } else if ($key == 'expr_type' && $val == 'const') {
                    array_push($constArr, $parser['base_expr']);
                }
            }
        }
        return true;
    }
    public function __destruct()
    { // 发送统计好的sql信息
        $sql = HistorySql::get();
        if (is_array($sql) && $sql) {
            $topicName = Config::get('kafka.topic');
            try {
                $data = [
                    'extra' => json_encode($this->extraInfo),
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
}