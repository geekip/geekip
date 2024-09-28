<?php
class Clash{
  public function __construct(array $base=[]) {
    $config = $this->make_config($base);
    if(KEY && KEY!=''){
      $content = encrypt($config, KEY);
      // 写入缓存
      file_put_contents(DIST_DIR . '/clash.txt', $content);
    }
  }

  public function make_config($base = []) {
  
    // 保留配置项
    $keys = ['mixed-port','allow-lan','bind-address','mode','log-level','external-controller','secret','experimental','profile','tcp-concurrent','dns'];
    $config = [];
    
    foreach ($keys as $key) {
      if(isset($base[$key])) $config[$key] = $base[$key];
    }
    $config['tun'] = $base['tun'];
    $config['external-ui'] = $base['external-ui'];
    
    // 测试节点
    $urltest = [];
  
    // 服务节点
    $nodes = [];
    
    foreach ($base['feed_proxies'] as $key => $proxies) {
      if(count($proxies)===1){
        $proxies[0]['name'] = $key;
      }else{
        $urltest[] = [
          'name' => $key,
          'type' => 'url-test',
          'url' => 'http://www.gstatic.com/generate_204',
          'interval' => 300,
          'proxies' => array_map(function ($item){
            return $item['name'];
          }, $proxies)
        ];
      }
      array_push($nodes, ...$proxies);
    }
  
    $config['proxies'] = array_merge($base['proxies'], $nodes);
  
    $config['proxy-groups'] = array_merge($base['proxy-groups'], $urltest);
  
    $rules = [];
    $end_rules = [];
    foreach ($base['rules'] as $name => $items) {
      switch ($name) {
        case 'direct':
          $name = 'DIRECT';
          break;
        case 'reject':
          $name = 'REJECT';
          break;
        case 'private':
          $name = DICT['private'];
          break;
        case 'selector':
          $name = DICT['selector'];
          break;
        case 'tor':
          $name = DICT['tor'];
          break;
        default:
          $name = DICT['final'];
          break;
      }
      foreach ($items as $item) {
        $type = $item[0];
        $str= $type;
        if(isset($item[1])){
          $str.=",$item[1]";
        }
        $str.=",$name";
        
        // IP段禁止DNS解析
        if($type == 'IP-CIDR' || $type == 'IP-CIDR6' || $type == 'GEOIP'){
          $str.=',no-resolve';
        }
        // 将GEOIP和MATCH放最后
        if($type == 'GEOIP' || $type == 'MATCH'){
          $end_rules[] = $str;
        }else{
          $rules[] = $str;
        }
      } 
    }
    // 规则
    $config['rules'] = array_merge($rules, $end_rules);
    // 输出yaml格式
    return yaml_emit($config, YAML_UTF8_ENCODING, YAML_ANY_BREAK);
  }
}
