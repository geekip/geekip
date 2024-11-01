<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');
date_default_timezone_set('Asia/Shanghai');

// 加密方式
if (!defined("EN_TYPE")) define("EN_TYPE", 'aes-256-cbc');

// 基础配置文件
define('BASE_CONFIG_FILE',  __DIR__ . '/base.yaml');

define('PAC_TPL_FILE', __DIR__ . '/pac.js');

// 节点国家
define('COUNTRIES', [
  '香港','台湾','澳门','美国','日本','新加坡','韩国','泰国','柬埔寨','越南','印尼',
  '马来西亚','哥伦比亚','希腊','埃及','法国','澳大利亚','南非','阿塞拜疆','罗马尼亚',
  '孟加拉','加拿大','捷克','荷兰','印度','爱尔兰','智利','阿拉伯','英国','德国','土耳其',
  '俄罗斯','阿根廷','巴西','意大利','挪威','巴基斯坦','保加利亚','拉脱维亚','菲律宾'
]);

// 规则映射
define('DICT', [
  'auto'     => '自动选择',
  'selector' => '节点选择',
  'tor'      => '洛杉矶TOR',
  'direct'   => '全球直连',
  'reject'   => '全球拦截',
  'final'    => '漏网之鱼',
  'other'    => '其他节点',
  'private'  => '自建服务'
]);

// 转换容量单位
function ConvertSize($size) {
  if ($size >= 1073741824) {
    return round($size / 1073741824, 0) . 'GB';  
  } elseif ($size >= 1048576) {
    return round($size / 1048576, 0) . 'MB';  
  } elseif ($size >= 1024) {
    return round($size / 1024, 0) . 'KB';  
  } else {
    return $size . 'B';  
  }
}

// 转换订阅信息
function Extract_subscription_info($userinfo) {
  $total = $userinfo['total'] ?? 0;
  $used = ($userinfo['upload'] ?? 0) + ($userinfo['download'] ?? 0);
  $percent = $total > 0 ? round(($total - $used) / $total * 100, 2) : 0;
  $expire = '';
  $updateTime = '';
  // 计算订阅期限
  if (isset($userinfo['expire'])) {
    $diffInDays = floor(($userinfo['expire'] - time()) / 86400);
    $dayStatus = $diffInDays > 0 ? '剩余' : '过期';
    $expire = date('Y/m/d', $userinfo['expire']) . "($dayStatus" . abs($diffInDays) . '天)';
  }
  // 获取更新时间
  if (isset($userinfo['update'])) {
    $updateTime = date('Y/m/d H:i', $userinfo['update']) . '更新';
  }
  return ConvertSize($used) . '/' . ConvertSize($total) . "($percent%)" . $expire . $updateTime;
}

// 清除emoji
function Trim_emoji($str) {
  return preg_replace(['/\\\\U[0-9A-Fa-f]{8}/','/[️♻⚓Ⓜ]/u','/\s+/'], '', preg_replace_callback('/./u', function ($match) {
    return strlen($match[0]) >= 4 ? '' : $match[0];
  }, $str));
}

function Array2json($arr) {
  return json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function YamlOrJsonFile2array($file) {
  return yaml_parse_file($file);
}

function YamlOrJson2array($str) {
  return yaml_parse($str);
}

function Clash2json($rules) {
  $result = [];
  foreach ($rules as $item) {
    $type = $item[0];
    $value = $item[1];
    switch ($type) {
      case 'DOMAIN':
        $type = 'domain';
        break;
      case 'DOMAIN-SUFFIX':
        $type = 'domain-suffix';
        break;
      case 'DOMAIN-KEYWORD':
        $type = 'domain-keyword';
        break;
      case 'IP-CIDR':
        $type = 'ip-cidr';
        break;
      case 'IP-CIDR6':
        $type = 'ip-cidr6';
        break;
      default:
        $type = null;
        break;
    }
    if($type && $value!=''){
      if (array_key_exists($type, $result)) {  
        $result[$type][] = $value;
      }else{
        $result[$type] = [$value];
      }
    }
  }
  return $result;
}

function Formate_rules($src, $key=null) {
  $maps = [];
  foreach ($src as $item) {
    $arr = explode(',', $item);
    $type = $arr[0];
    $value = null;
    $name = null;
    if($type=='MATCH'){
      $name = $arr[1];
    }else{
      $value = $arr[1];
      $name = $key ?? $arr[2];
    }
    $name = Trim_emoji($name);
    switch ($name) {
      case 'direct':
      case 'DIRECT':
      case 'my_direct':
      case '哔哩哔哩':
      case '苹果服务':
      case '微软服务':
      case '全球直连':
        $name = 'direct';
        break;
      case '全球拦截':
      case 'reject':
      case 'my_reject':
        $name = 'reject';
        break;
      case 'AdBlock':
      case '应用净化':
        $name = null;
        break;
      case 'private':
      case 'openai':
        $name = 'private';
        break;
      case 'tor':
        $name = 'tor';
        break;
      case 'proxy':
      case 'selector':
      case '国外媒体':
      case '谷歌FCM':
      case '电报信息':
      case '节点选择':
        $name = 'selector';
        break;
      default:
        $name = 'final';
        break;
    }
    if($name){
      $node = [$type, $value];
      if (array_key_exists($name, $maps)) { 
        $maps[$name][] =  $node;
      }else{
        $maps[$name] = [$node];
      }
    }
  }
  return $maps;
}

function Get_cn_rules() {
  if(!file_exists(RULES_CN_CACHE)) return [];
  $src = YamlOrJsonFile2array(RULES_CN_CACHE);
  $rules = [];
  foreach ($src as $type=>$item) {
    switch ($type) {
      case 'domain':
        $type = 'DOMAIN';
        break;
      case 'domain_suffix':
        $type = 'DOMAIN-SUFFIX';
        break;
      case 'keyword':
        $type = 'DOMAIN-KEYWORD';
        break;
      case 'ip':
        $type = 'IP-CIDR';
        break;
      default:
        $type = null;
        break;
    }
    if($type){
      foreach ($item as $value) {
        if($type == 'IP-CIDR') {
          $value = implode('/', $value);
        }
        $rules[] = [$type, $value];
      }
    }
  }
  return ['direct' => $rules];
}

function MergeRules(...$rules) {  
  $result = [];  
  foreach ($rules as $array) {  
    $result = _MergeRule($result, $array);  
  }  
  return $result;  
}  

function _MergeRule($rules1, $rules2) {  
  $merged = $rules1;  
  foreach ($rules2 as $key => $value) {  
    if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])){  
      $merged[$key] = array_merge($merged[$key], $value);
    } else {
      $merged[$key] = $value;  
    }  
  }
  return $merged;  
}  

function Remove_multiple_rules($rules) {
  // 去重
  $caches_map = []; 
  $caches = [];
  foreach ($rules as $key => $subArray) {  
    $tempSubArray = [];
    foreach ($subArray as $item) {
      $value = $item[1];
      if (!isset($caches_map[$value])) {  
        $tempSubArray[] = $item;  
        $caches_map[$value] = true;  
      }
    }  
    $caches[$key] = $tempSubArray;  
  }
  // 排序
  $orders = ['direct', 'private', 'tor', 'reject', 'selector', 'final']; 
  $result = [];  
  foreach ($orders as $key) {
    if (isset($caches[$key])) {
      $result[$key] = $caches[$key];  
    }
  }
  return $result;
}

function Make_base_config() {
  $config = YamlOrJsonFile2array(BASE_CONFIG_FILE);
  $config['rules'] = Formate_rules($config['rules']);
  $files = glob(__DIR__ . "/rules/*.yaml");
  foreach ($files as $path) {
    $filename = pathinfo($path, PATHINFO_FILENAME);
    $rule = YamlOrJsonFile2array($path);
    if(count($rule)){
      array_push($config['rules'], Formate_rules($rule, $filename));
    } 
  }
  $config['rules'] = MergeRules(...$config['rules']);
  return $config;
}

function Get_feed_config() {
  $config = ['countries' => [],'proxies' => [],'rules' => [],'userinfo' => ['update' => time()]];
  if(!file_exists(FEED_SOURCE)) return $config;
  $feed_scource = file_get_contents(FEED_SOURCE);
  list($header, $body) = explode("\r\n\r\n", $feed_scource, 2);
  $feed = YamlOrJson2array($body);
  $config['rules'] = $feed['rules'];
  // 其他节点
  $miss = [];
  // 订阅信息
  if (preg_match('/subscription-userinfo:\s*(.*)/', $header, $matches)) {
    parse_str(str_replace('; ', '&', trim($matches[1])), $userinfo);
    $config['userinfo'] = array_merge($config['userinfo'], $userinfo);
  }else{
    return $config;
  }
  // 解析节点国家
  foreach ($feed['proxies'] as $key => $proxy) {
    $found = false;  
    $proxy['name'] = Trim_emoji($proxy['name']);
    foreach (COUNTRIES as $key => $countries) {  
      if (strpos($proxy['name'], $countries) !== false) {
        if (array_key_exists($countries, $config['proxies'])) {
          $config['proxies'][$countries][] = $proxy;
        }else{
          $config['countries'][] = $countries;
          $config['proxies'][$countries] = [$proxy];
        }
        $found = true; 
        break;  
      }
    }
    if (!$found) {  
      $miss[] = $proxy; 
    } 
  }
  if(count($miss)){
    $config['proxies'][DICT['other']] = $miss;
    $config['countries'][] = DICT['other'];
  }
  return $config;
}

// 缓存订阅
function Get_base_config() {
  $feed = Get_feed_config();
  // 获取基础配置
  $config = Make_base_config();
  $config['countries'] = $feed['countries'];
  $config['feed_proxies'] = $feed['proxies'];
  $config['userinfo'] = $feed['userinfo'];

  // 将国际节点添加到代理组
  $countries = $feed['countries'];
  $config['proxy-groups'] = array_map(function ($item) use ($countries) {
    $name = $item['name'];
    if($name === DICT['selector'] || $name === DICT['auto'] || $name === DICT['final']){
      $item['proxies'] = array_merge($item['proxies'], $countries);
    }
    return $item;
  }, $config['proxy-groups']);

  // 规则
  $rules = MergeRules($config['rules'], Get_cn_rules());

  // 合并规则
  if(isset($feed['rules']) && count($feed['rules'])){
    $rules = MergeRules($rules, Formate_rules($feed['rules']));
  }
   
  // 去重排序
  $config['rules'] = Remove_multiple_rules($rules);

  // 替换占位符
  $subscription = Extract_subscription_info($config['userinfo']);
  $json = str_replace("订阅信息", $subscription, Array2json($config));
  // if(KEY && KEY!=''){
  //   $content = encrypt($json, KEY);
  //   // 写入缓存
  //   file_put_contents(DIST_DIR . '/config.json', $content);
  // }
  return yaml_parse($json);
}

// 加密
function encrypt($data, $key) {
  return openssl_encrypt($data, EN_TYPE, $key, 0, 12345678);
}

require_once __DIR__ . '/clash.php';
require_once __DIR__ . '/singbox.php';
require_once __DIR__ . '/pac.php';
new Clash($base_config);
new SingBox($base_config);
new Pac($base_config);


