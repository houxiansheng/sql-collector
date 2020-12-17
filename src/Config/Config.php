<?php
if (isset($_SERVER['SITE_ENV']) && $_SERVER['SITE_ENV'] == "production") {
    $zkHosts = $_SERVER['SITE_JINRONG_ZOOKEEPER'];
    $msgCenterUrl = "http://message-manage.youxinjinrong.com";
} else {
    $zkHosts = $_SERVER['SITE_JINRONG_ZOOKEEPER_TEST'];
//     $msgCenterUrl = "http://message_manage.dev.youxinjinrong.com";
    $msgCenterUrl = "http://message_manage.test.youxinjinrong.com";
}
return [
    'kafka' => [
        'zk_hosts' => $zkHosts,
        'zk_timeout' => 1000000,
        'send_timeout' => 100000,
        'partition_hash_open' => 0,
        'partition_hash' => 10,
        'project' => 'project1',
        'msg_center_url' => $msgCenterUrl,
        'topic' => 'sqltool.youxinjinrong.com_sql_collect_topic'
    ],
    'sql' => [
        'max_num' => 100,
        'off_set' => 10000,
        'row_count' => 1000,
        'list_max' => 2000
    ]
];
