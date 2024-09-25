# WORKSPACE="./"
# CACHE_DIR="./cache"
SRC_FEED=$CACHE_DIR/feed.source.yaml
SRC_CN=$CACHE_DIR/cn.txt
DIST_CN=$CACHE_DIR/cn.json
DIST_CONFIG=$WORKSPACE/config


sudo mkdir -p $CACHE_DIR
sudo chmod 777 $CACHE_DIR
echo $(date +"%Y-%m-%d %H:%M:%S")

# 局域网地址
curl -sSL "https://raw.githubusercontent.com/ACL4SSR/ACL4SSR/master/Clash/LocalAreaNetwork.list" > $SRC_CN
# 苹果中国
curl -sSL "https://raw.githubusercontent.com/ACL4SSR/ACL4SSR/master/Clash/Apple.list" >> $SRC_CN
# 谷歌中国
curl -sSL "https://raw.githubusercontent.com/ACL4SSR/ACL4SSR/master/Clash/GoogleCN.list" >> $SRC_CN
# 微软中国
curl -sSL "https://raw.githubusercontent.com/ACL4SSR/ACL4SSR/master/Clash/Microsoft.list" >> $SRC_CN
echo "">> $SRC_CN
# 中国云服务商ip段
curl -sSL "https://raw.githubusercontent.com/ACL4SSR/ACL4SSR/master/Clash/ChinaCompanyIp.list" >> $SRC_CN
# 中国媒体
curl -sSL "https://raw.githubusercontent.com/ACL4SSR/ACL4SSR/master/Clash/ChinaMedia.list" >> $SRC_CN
# 中国域名
curl -sSL "https://raw.githubusercontent.com/ACL4SSR/ACL4SSR/master/Clash/ChinaDomain.list" >> $SRC_CN
# 中国IP
curl -sSL "https://www.ipdeny.com/ipblocks/data/aggregated/cn-aggregated.zone" | perl -ne '/(.+\/\d+)/ && print "IP-CIDR,$1,no-resolve\n"' >> $SRC_CN
# 下载订阅
curl -s -H "User-Agent: clash" -i "${GFW_FEED_URL}" > $SRC_FEED

# 解析合并
declare -A files  
files[DOMAIN]="${CACHE_DIR}/domain.yaml"  
files[DOMAIN-SUFFIX]="${CACHE_DIR}/domain_suffix.yaml"  
files[DOMAIN-KEYWORD]="${CACHE_DIR}/keyword.yaml"
files[IP-CIDR]="${CACHE_DIR}/ip.yaml"  
  
: > "${files[DOMAIN]}"  
: > "${files[DOMAIN-SUFFIX]}"  
: > "${files[DOMAIN-KEYWORD]}"  
: > "${files[IP-CIDR]}"  

while IFS=',' read -r prefix value; do  
  if [[ -n "$prefix" && -n "${files[$prefix]}" ]]; then  
    file="${files[$prefix]}"
    if [[ "$prefix" == "IP-CIDR"* ]]; then  
      value=$(echo "$value" | cut -d',' -f1)
      if [[ $value =~ ([0-9.]+)/([0-9]+) ]]; then  
        ip=${BASH_REMATCH[1]}  
        prefix_length=${BASH_REMATCH[2]}  
        echo "- [$ip,$prefix_length]" >> "$file"
      fi
    else
      echo "- $value" >> "$file"
    fi     
  fi  
done < $SRC_CN

echo ""> $DIST_CN
yq -o json -I 0 -i 'load("'${files[DOMAIN]}'") as $f | .domain=$f ' $DIST_CN
yq -o json -I 0 -i 'load("'${files[DOMAIN-SUFFIX]}'") as $f | .domain_suffix=$f ' $DIST_CN
yq -o json -I 0 -i 'load("'${files[DOMAIN-KEYWORD]}'") as $f | .keyword=$f ' $DIST_CN
yq -o json -I 0 -i 'load("'${files[IP-CIDR]}'") as $f | .ip=$f ' $DIST_CN

php -r "
  define('KEY', '${GFW_KEY}');
  define('CONFIG_FILE', '${DIST_CONFIG}');
  define('FEED_SOURCE', '${SRC_FEED}');
  define('RULES_CN_CACHE', '${DIST_CN}');
  require_once '${WORKSPACE}/start.php';
"

rm -rf $CACHE_DIR