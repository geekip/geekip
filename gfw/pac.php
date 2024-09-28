<?php
class Pac{
  public function __construct(array $base=[]) {
    $config = $this->make_config($base);
    file_put_contents(__DIR__ . '/pac', $config);
  }
  // 将ip和子网掩码拆分为数组
  public function getIps($ips) {
    $result = [];
    if(is_array($ips)){
      $result = array_map(function($item){
        list($ip, $mask) = explode('/', $item);
        return [$ip, (int)$mask];
      }, $ips);
    }
    return $result;
  }
  public function getMaskMap() {
    $maps = [];
    // 子网掩码表
    for ($i = 0; $i <= 32; $i++) {
      $mask = long2ip(-1 << (32 - $i)); 
      $maps[] = $mask;
    }
    return json_encode($maps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  public function get_rules($maps=[], $key='') {
    $rules = [];
    if(isset($maps[$key])){
      $rules = $maps[$key];
    }
    $count = count($rules);
    $rules = json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return [$rules, $count];
  }

  public function clash2json($rules) {
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

  public function make_config($base = []) {
    // 白名单规则
    $direct = $base['rules']['direct'];
    $rules = $this->clash2json($direct);
    // 子网掩码表
    $maskMap = $this->getMaskMap();
    // 域名白名单
    list($whiteDomains, $whiteDomainsCount) = $this->get_rules($rules, 'domain');
    // 域名后缀白名单
    list($whiteDomainSuffix, $whiteDomainSuffixCount) = $this->get_rules($rules, 'domain-suffix');
    // 域名关键字白名单
    list($whiteKeywords, $whiteKeywordsCount) = $this->get_rules($rules, 'domain-keyword');
    // IP段白名单
    if(isset($rules['ip-cidr'])){
      $rules['ip-cidr'] = $this->getIps($rules['ip-cidr']);
    }
    list($whiteIps, $whiteIpsCount) = $this->get_rules($rules, 'ip-cidr');
    // 更新时间
    $updateDate = date("Y-m-d H:i:s");
    // pac模板
    $tpl = file_get_contents(PAC_TPL_FILE);
    $result = str_replace(
      [
        '{updateDate}','{maskMap}',
        '{whiteDomains}','{whiteDomainsCount}',
        '{whiteDomainSuffix}','{whiteDomainSuffixCount}',
        '{whiteKeywords}','{whiteKeywordsCount}',
        '{whiteIps}','{whiteIpsCount}'
      ],
      [
        $updateDate, $maskMap, 
        $whiteDomains,$whiteDomainsCount,
        $whiteDomainSuffix,$whiteDomainSuffixCount,
        $whiteKeywords,$whiteKeywordsCount,
        $whiteIps,$whiteIpsCount
      ], $tpl
    );
    return $result;
  }
}

