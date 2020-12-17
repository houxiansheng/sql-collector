<?php
namespace USQL;

use USQL\Library\SqlRestraint\Restraint;
use USQL\Library\GoogleSqlParser\PHPSQLParser;
use USQL\Library\SqlRestraint\Common\HistorySql;
use USQL\Library\Kafka\producerAdapt;
use USQL\Library\Config;
use USQL\Library\SqlRestraint\Common\GlobalVar;

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
     * @param string $projectName
     *            项目名称
     * @return boolean
     */
    public function collect($dbName, $query, $bindings)
    {
        $data = [
            'db' => $dbName,
            'query' => $query,
            'bindings' => $bindings,
            'take_time' => 0,
            'exec_time' => time()
        ];
        HistorySql::write($data);
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
            $symbolsReplace = $this->specialSymbolsReplace();
            $sql = preg_replace(array_keys($symbolsReplace), array_values($symbolsReplace), $sql);
            $parser = $this->phpSqlParser->parse($sql, false);
            $res = $this->restraint->hander($sql,$parser);
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
     * 替换sql中备注
     * @param unknown $sql
     * @return unknown
     * */    
    public function replaceSqlRemark($sql){
        $pattern='/(.*)\/\*(.*)\*\/(.*)/';
        preg_match($pattern, $sql,$match);
        if($match){
            return $match[1].$match[3];
        }else{
            return $sql;
        }
    } 
    /**
     * 替换常量为问号
     * @param unknown $sql
     * @return mixed
     * */
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
        $constArr = [
            'match'=>[],//替换过信息
            'real'=>[]//真实const
        ];
        if (is_array($parser)) {
            $hasLimitOff=$this->hasLimitOffset($sql);
            $this->getConst($parser, $constArr,$hasLimitOff);
            if ($constArr['match']) {
                $pattern = '/(.*)' . implode('(.*)?', $constArr['match']) . '(.*)?/';
                preg_match($pattern, $sql, $match);
                array_shift($match);
                if($match){
                    $sql = implode(' ? ', $match);
                }else{
                    $constArr['match']=[];//替换过信息
                    $constArr['real']=[];//真实const
                }
            }
        }
        $sql = preg_replace(array_keys($symbolsReplace), array_values($symbolsReplace), $sql);
        $return = [
            'sql' => trim($sql),
            'list' => $constArr['real']
        ];
        return $return;
    }
    
    /**
     * 连续?合并为一个
     * @param unknown $sql
     * @return mixed
     * */
    public function cutSql($sql){
        $cut = [
            '/(\s{0,}\?\s{0,},){0,}\s{0,}\?/' => ' ? ',
        ];
        $symbolsReplace = array_merge($cut,$this->specialSymbolsReplace());
        $sql = preg_replace(array_keys($symbolsReplace), array_values($symbolsReplace), $sql);
        $return=[
            'sql'=>trim($sql)
        ];
        return $return;
    }
    public function delConstss($sql)
    {
        $sql = ' ' . $sql . ' ';
        // 替换换行，空格
        $symbolsReplace = $this->specialSymbolsReplace();
        $symbolsReplace['/`/']='';
        $sql = preg_replace(array_keys($symbolsReplace), array_values($symbolsReplace), $sql);
        try {
            $parser = $this->phpSqlParser->parse($sql, true);
        } catch (\Exception $e) {
            $parser = [];
        }
        $classArr = [];
        $data=[];
        if (is_array($parser)) {
            $this->classiFy($parser, $classArr);
            //'SELECT','UPDATE','DELETE','FROM','WHERE','SET'
            $data['select']=isset($classArr['SELECT'])?implode(' ', $classArr['SELECT']):'';
            $data['update']=isset($classArr['UPDATE'])?implode(' ', $classArr['UPDATE']):'';
            $data['delete']=isset($classArr['DELETE'])?implode(' ', $classArr['DELETE']):'';
            $data['from']=isset($classArr['FROM'])?implode(' ', $classArr['FROM']):'';
            $data['set']=isset($classArr['SET'])?implode(' ', $classArr['SET']):'';
            //组装所有
            $data['where']=[];
            if(isset($classArr['WHERE'])){
                foreach ($classArr['WHERE'] as $whereTmp){
                    if(isset($classArr['WHERE_OPERATOR'])){
                        foreach ($classArr['WHERE_OPERATOR'] as $operTmp){
                            $data['where'][]=$whereTmp.$operTmp;
                        }
                    }
                }
            }
            $data['where']=implode(' ', array_unique($data['where']));
            //过滤别称
            $alias=[];
            $pattern=[];
            if(isset($classArr['ALIAS'])){
                foreach ($classArr['ALIAS'] as $tableTmp => $aliasTmp){
                    $pattern['/'.$aliasTmp.'\./']='';
                }
            }
            $reservedFieldReplace=$this->operatorReplace();
            $reservedFieldReplace=array_merge($pattern,$reservedFieldReplace);
            $data['select']=preg_replace(array_keys($reservedFieldReplace), array_values($reservedFieldReplace), $data['select']);
            $data['where']=preg_replace(array_keys($reservedFieldReplace), array_values($reservedFieldReplace), $data['where']);
        }
        return $data;
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
        $constArr = [
            'match'=>[],//替换过信息
            'real'=>[]//真实const
        ];
        if (is_array($parser)) {
            $hasLimitOff=$this->hasLimitOffset($sql);
            $this->getConst($parser, $constArr,$hasLimitOff);
            $pattern = '/(.*)' . implode('(.*)?', $constArr['match']) . '(.*)?/';
            preg_match($pattern, $sql, $match);
            array_shift($match);
            $sql = implode('', $match);
        }
        $compare = array_merge($this->operatorReplace(), $this->specialSymbolsReplace());
        $sql = preg_replace(array_keys($compare), array_values($compare), $sql);
        $sql = trim(implode(' ', array_unique(explode(' ', $sql))));
        $return = [
            'sql' => $sql,
            'list' => array_unique($constArr['real'])
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

    private function pregMatchReserveWord()
    {
        $word = [
            '/\//'=>'\/',
            '/\^/' => '\\^',
            '/\\\\\\A/' => '\\\\A',
            '/\$/' => '\\$',
            '/\\\\Z/' => '\\\\Z',
            '/\\\\b/' => '\\\\b',
            '/\\\\B/' => '\\\\B',
            '/\\\\</' => '\\\\<',
            '/\\\\>/' => '\\\\>',
            '/\*/' => '\\*',
            '/\+/' => '\\+',
            '/\?/' => '\\?',
            '/\{/' => '\\{',
            '/\}/' => '\\}',
            '/\(/' => '\\(',
            '/\)/' => '\\)',
            '/\[/' => '\\[',
            '/\]/' => '\\]',
            '/\|/' => '\\|',
            '/\\\\n/' => '\\\\n',
            '/\\\\c /' => '\\\\c ',
            '/\\\\s/' => '\\\\s',
            '/\\\\S/' => '\\\\S',
            '/\\\\d/' => '\\\\d',
            '/\\\\D/' => '\\\\D',
            '/\\\\w/' => '\\\\w',
            '/\\\\W/' => '\\\\W',
            '/\\\\x/' => '\\\\x',
            '/\\\\O/' => '\\\\O',
            '/\\\\r/' => '\\\\r',
            '/\\\\t/' => '\\\\t',
            '/\\\\v /' => '\\\\v ',
            '/\\\\f /' => '\\\\f ',
            '/\\\\xxx/' => '\\\\xxx',
            '/\\\\xhh/' => '\\\\xhh'
        ];
        return $word;
    }
    private function classiFy($parser,array &$classArr,$module=null){
        if (is_array($parser)) {
            foreach ($parser as $key => $val) {
                if(in_array($key, array('SELECT','UPDATE','DELETE','FROM','WHERE','SET'),true)){
                    $module=$key;
                }
                if(is_null($module)){
                    continue;
                }
                if(is_array($val)){
                    $this->classiFy($parser[$key], $classArr,$module);
                }
                if($module=='UPDATE' && $key == 'expr_type' && $val=='table'){
                    $classArr[$module][]=$parser['table'];
                }
                if($module=='SELECT' && $key == 'expr_type' && $val=='colref'){
                    $classArr[$module][]=$parser['base_expr'];
                }
                if($module=='DELETE' && $key=='TABLES'){
                    $classArr[$module]=$val;
                }
                if($module=='FROM' && $key == 'expr_type' && $val=='table'){
                    $classArr[$module][]=$parser['table'];
                    $classArr['ALIAS'][$parser['table']]=$parser['table'];
                }
                if($module=='WHERE' && $key == 'expr_type' && $val=='colref'){
                    $classArr[$module][]=$parser['base_expr'];
                }
                if($module=='WHERE' && $key == 'expr_type' && $val=='operator'){
                    if(!in_array($parser['base_expr'], ['and','or'])){
                        $classArr['WHERE_OPERATOR'][]=$parser['base_expr'];
                    }
                }
                if($module=='SET' && $key == 'expr_type' && $val=='colref'){
                    $classArr[$module][]=$parser['base_expr'];
                }
                if($key == 'expr_type' && $val=='table' && $parser['alias']){
                    $classArr['ALIAS'][$parser['table']]=$parser['alias']['name'];
                }
            }
        }
        return true;
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
    private function replaceOperate(){
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
        array(
            '/( ){2,}/' => ' ',
            "<( ){1,}=( ){1,}>"=>'',
            "!="=>"!=",
            ">="=>">=",
            "<="=>"<=",
            "<>"=>"<>",
            "<<"=>"<<",
            ">>"=>">>",
            ":="=>":=",
            "\\"=>"\\",
            "&&",
            "||",
            ":=",
            "/*",
            "*/",
            "--",
            ">",
            "<",
            "|",
            "=",
            "^",
            "(",
            ")",
            "\t",
            "\n",
            "'",
            "\"",
            "`",
            ",",
            "@",
            " ",
            "+",
            "-",
            "*",
            "/",
            ";"
        );
        return $fields;
    }
    private function specialSymbolsReplace()
    {
        $fields = [
            "/\r\n/"=>' ',
            '/\n/' => ' ',
            '/( ){2,}/' => ' ',
            '/!( )=/'=>'!=',
            "/>( )=/"=>">=",
            "/<( )=/"=>"<=",
            "/<( )>/"=>"<>",
            "/<( )</"=>"<<",
            "/!( )</"=>"!<",
            "/!( )>/"=>"!>",
            
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
    private function hasLimitOffset($sql){
        $pattern='/(?i)(.*)limit(.*)offSet(.*)/';
        $res=preg_match($pattern, $sql,$match);
        return $res?true:false;
    }
    private function getConst($parser, &$constArr,$hasLimitOff)
    {
        $limitC=['offset'];
        if($hasLimitOff){
            array_unshift($limitC, 'rowcount'); 
        }else{
            array_push($limitC, 'rowcount');  
        }
        $matchRWord=$this->pregMatchReserveWord();
        $matchRWordKey=array_keys($matchRWord);
        $matchRWordVal=array_values($matchRWord);
        if (is_array($parser)) {
            foreach ($parser as $key => $val) {
                if ($key === 'LIMIT') {
                    foreach ($limitC as $limiV){
                        if(is_numeric($val[$limiV])){
                            array_push($constArr['real'], $val[$limiV]);
                            $val[$limiV] = preg_replace($matchRWordKey, $matchRWordVal, $val[$limiV]);
                            array_push($constArr['match'], $val[$limiV]);
                        }
                    }
                } elseif (is_array($val)) {
                    $this->getConst($parser[$key], $constArr,$hasLimitOff);
                } else if ($key == 'expr_type' && $val == 'const') {
                    array_push($constArr['real'], $parser['base_expr']);
                    $parser['base_expr'] = preg_replace($matchRWordKey, $matchRWordVal, $parser['base_expr']);
                    array_push($constArr['match'], $parser['base_expr']);
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
    private function getExtraInfo(){
        if(strtolower(php_sapi_name())=='cli'){
            global $argv;
            $tmp=$argv;
            array_shift($tmp);
            $host='script';
            $uri=implode('/', $tmp);
            $request='[]';
        }else{
            $host=$_SERVER['HTTP_HOST'];
            $uriArr=explode('?', $_SERVER['REQUEST_URI']);
            $uri=$uriArr[0];
            $request=json_encode($_REQUEST);
        }
        $dir=__DIR__;
        $str=trim(substr($dir, 0,strpos($dir, 'vendor/uxin/sql-collector')),'/');
        $strArr=explode('/', $str);
        $len=count($strArr);
        if($len>3){
            $projectName=$strArr[$len-3];
        }else{
            $projectName=array_pop($strArr);
        }
        $this->extraInfo['host'] = $host;
        $this->extraInfo['uri'] = $uri;
        $this->extraInfo['request'] = $request;
        $this->extraInfo['pname'] = $projectName;
        return $this->extraInfo;
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