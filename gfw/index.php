<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');

// define('FEED_URL', $_ENV['GFW_FEED_URL']);

// define('KEY', $_ENV['GFW_KEY']);

// 基础配置文件
define('BASE_CONFIG_FILE',  __DIR__ . '/base.yaml');
// 基础配置缓存文件
define('CONFIG_FILE',  __DIR__ . '/config');
// 订阅缓存文件
define('FEED_SOURCE',  __DIR__ . '/feed.source.yaml');
// 中国规则
define('RULES_CN_CACHE',  __DIR__ . '/cn.json');
file_put_contents(CONFIG_FILE, '123');