<?php
if (isset($_SERVER['SITE_ENV']) && $_SERVER['SITE_ENV'] == "production") {
    $zkHosts = $_SERVER['SITE_JINRONG_ZOOKEEPER'];
    $msgCenterUrl = "http://message_manage.youxinjinrong.com";
} else {
    $zkHosts = $_SERVER['SITE_JINRONG_ZOOKEEPER_TEST'];
    $msgCenterUrl = "http://message_manage.dev.youxinjinrong.com";
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
    ]
];
