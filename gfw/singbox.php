<?php
class SingBox{
  public function __construct(array $base=[]) {
    $config = $this->make_config($base);
    if(KEY && KEY!=''){
      $content = encrypt($config, KEY);
      // 写入缓存
      file_put_contents(DIST_DIR . '/singbox.json', $content);
    }
  }

  public function make_config($base = []) {
    // 转换日志
    $config = ['log' => $this->make_log($base)];
    // 配置DNS
    $config['dns'] = $this->make_dns($base);
    // 配置入站
    $config['inbounds'] = $this->make_inbounds($base);
    // 配置出站
    $config['outbounds'] = $this->make_outbounds($base);
    // 配置规则
    $config['route'] = $this->make_route($config, $base);
    // 配置接口
    $config['experimental'] = $this->make_experimental($base);
    // 输出json格式
    return Array2json($config);
  }

  public function proxies2outbounds($proxies) {
    return array_map(function($proxy){
      switch ($proxy['type']) {
        case 'vmess':
          $proxy = [
            "tag" => $proxy['name'],
            "type" => $proxy['type'],
            "server" => $proxy['server'],
            "server_port" => $proxy['port'],
            "uuid" => $proxy['uuid'],
            "security" => 'auto'
          ];
          break;
        case 'socks':
        case 'socks5':
          $proxy = [
            "tag" => $proxy['name'],
            "type" => 'socks',
            "server" => $proxy['server'],
            "server_port" => $proxy['port']
          ];
          break;
      }
      return $proxy;
    },$proxies);
  }
  public function make_log($base = []) {
    $level = $base['log-level'];
    $disabled = false;
    // trace debug info warn error fatal panic
    // info / warning / error / debug / silent
    switch ($level) {
      case 'warning':
        $level = 'warn';
        break;
      case 'silent':
        $level = 'panic';
        $disabled = true;
        break;
    }
    return ['disabled' => $disabled,'level' => $level];
  }
  // 配置DNS
  public function make_dns($base) {
    // DNS上游服务
    $servers = [
      [
        'tag' => 'dns_proxy',
        'address' => 'https://1.1.1.1/dns-query',
        'address_resolver' => 'dns_resolver',
        'strategy' => 'ipv4_only',
        'detour' => DICT['auto'],
      ],[
        'tag' => 'dns_direct',
        'address' => 'h3://dns.alidns.com/dns-query',
        'address_resolver' => 'dns_resolver',
        'strategy' => 'ipv4_only',
        'detour' => DICT['direct'],
      ],[
        'tag' => 'dns_success',
        'address' => 'rcode://success'
      ],[
        'tag' => 'dns_block',
        'address' => 'rcode://refused'
      ],[
        'tag' => 'dns_resolver',
        'address' => '223.5.5.5',
        'strategy' => 'ipv4_only',
        'detour' => DICT['direct']
      ]
    ];
    // DNS分流规则
    $rules = [
      [
        'outbound' => 'any',
        'server' => 'dns_resolver'
      ],[
        'clash_mode' => 'direct',
        'server' => 'dns_direct'
      ],[
        'clash_mode' => 'global',
        'server' => 'dns_proxy'
      ]
    ];
    
    return [
      'servers' => $servers,
      'rules' => $rules,
      'final' => 'dns_direct',
      'fakeip' => [
        'enabled' => true,
        'inet4_range' => '198.18.0.0/15',
        'inet6_range' => 'fc00::/18'
      ],
      'strategy' => 'ipv4_only'
    ];
  }

  public function make_inbounds($base) {
    return [
      [
        'type' => 'mixed',
        'tag' => 'mixed-in',
        'listen'=>'::',
        'listen_port'=> isset($base['mixed-port']) ? $base['mixed-port'] : 7890,
        'sniff' => true
      ],
      [
        'type' => 'vmess',
        'tag' => 'vmess-in',
        'listen'=>'::',
        'listen_port'=> isset($base['vmess-port']) ? $base['vmess-port'] : 7891,
        'sniff' => true,
        'users'=>[
          [
            'name'=>'vmess-server',
            'uuid'=> $base['vmess-uuid']
          ]
        ]
      ],
      [
        'type' => 'tun',
        'tag' => 'tun-in',
        'interface_name'=>'stun',
        'auto_route'=> true,
        'sniff' => true,
        'address'=>['172.18.0.1/30','fdfe:dcba:9876::1/126']
      ]
    ];
  }

  public function make_outbounds($base) {
    $outbounds = [
      [
        'tag' => 'dns-out',
        'type' => 'dns'
      ],[
        'tag' => DICT['direct'],
        'type' => 'direct'
      ],[
        'tag' => DICT['reject'],
        'type' => 'block'
      ]
    ];
    // 节点
    $urltest = [];
    $nodes = [];
    foreach ($base['feed_proxies'] as $key => $proxies) {
      $proxies = $this->proxies2outbounds($proxies);
      if(count($proxies)===1){
        $proxies[0]['tag'] = $key;
      }else{
        $outs = array_map(function ($item){
          return $item['tag'];
        }, $proxies);
        array_push($urltest, [
          "tag" => $key,
          "type" => 'urltest',
          "outbounds" => $outs
        ]);
      }
      array_push($nodes, ...$proxies);
    }
    $nodes = array_merge($this->proxies2outbounds($base['proxies']), $nodes);

    $base_outbounds = array_map(function ($item) {
      if($item['type']==='select'){
        $item['type'] = 'selector';
      }elseif ($item['type']==='url-test'){
        $item['type'] = 'urltest';
      }
      $item['proxies'] = array_map(function ($item) {
        if($item==='DIRECT'){
          $item = DICT['direct'];
        }else if($item==='REJECT'){
          $item = DICT['reject'];
        }
        return $item;
      },$item['proxies']);

      $outbounds =  [
        'tag' => $item['name'],
        'type' => $item['type'],
        'outbounds' => $item['proxies']
      ];

      if(isset($item['default'])){
        $outbounds['default'] = $item['default'];
      }
      return $outbounds;
    },$base['proxy-groups']);

    return array_merge($outbounds, $base_outbounds, $urltest, $nodes);
  }

  // 配置规则
  public function make_route(&$config, $base) {
    $rules = [
      [
        'protocol' => 'dns',
        'outbound' => 'dns-out'
      ],
      [
        'protocol' => 'quic',
        'outbound' => DICT['reject']
      ],
      [
        'ip_is_private' => true,
        'outbound' => DICT['direct']
      ],
      [
        'clash_mode' => 'direct',
        'outbound' => DICT['direct']
      ],
      [
        'clash_mode' => 'global',
        'outbound' => DICT['selector']
      ]
    ];

    $rule_set = [];

    $maps=[];

    foreach ($base['rules'] as $name => $items) {
      $rs = [];
      foreach ($items as $item) {
        $type = $item[0];
        switch ($type) {
          case 'DOMAIN':
            $type = 'domain';
            break;
          case 'DOMAIN-SUFFIX':
            $type = 'domain_suffix';
            break;
          case 'DOMAIN-KEYWORD':
            $type = 'domain_keyword';
            break;
          case 'IP-CIDR':
          case 'IP-CIDR6':
            $type = 'ip_cidr';
            break;
          default:
            $type = null;
            break;
        }
        if($type){
          $item['type'] = $type;
          $rs[] = $item;
        }
      }
      if (array_key_exists($name, $maps)) {  
        $maps[$name][] = $rs;
      }else{
        $maps[$name] = [$rs];
      }
    }
    
    foreach ($maps as $key => $subArray) {
      $groupedData = [];  
      foreach ($subArray as $item) { 
        foreach ($item as $rule) {
          list($type, $value) = $rule;  
          if (!isset($groupedData[$type])) {  
            $groupedData[$type] = [];  
          }  
          $groupedData[$type][] = $value;  
        }
      }

      $transformedData = [];  
      foreach ($groupedData as $type => $contents) {  
        $transformedData[] = [$type => $contents];  
      }
      $rule_set[] = [  
        'type' => 'inline',  
        'tag' => $key,  
        'rules' => $transformedData  
      ];  
      $dict=[
        "selector"  => DICT['selector'],
        "direct" => DICT['direct'],
        'reject' => DICT['reject'],
        'private' => DICT['private']
      ];
      if(isset($dict[$key])){
        $rules[] = [
          'rule_set' => $key,
          'outbound' => $dict[$key]
        ];
      }
      // 指定DNS查询节点
      $dict_dns=[
        "selector" => "dns_proxy",
        "direct" => "dns_direct",
        'reject' => "dns_block",
        'private'=>'dns_proxy'
      ];
      if(isset($dict_dns[$key])){
        $config['dns']['rules'][] = [
          'rule_set' => $key,
          'server' => $dict_dns[$key]
        ];
      } 
    }
    return [
      'rules' => $rules,
      'rule_set' => $rule_set,
      'final' => DICT['final'],
      'auto_detect_interface' => true
    ];
  }

  public function make_experimental($base) {
    return [
      'cache_file' => [
        'enabled' => true,
        'path' => 'cache.db'
      ],
      'clash_api' => [
        'external_controller' => $base['external-controller'],
        'external_ui' => $base['external-ui'],
        'external_ui_download_detour' => DICT['auto'],
        'secret' => $base['secret'],
        'default_mode' => $base['mode']
      ]
    ];
  }
}
