/** 
 * update         {updateDate}
 * white-ip       {whiteIpsCount}
 * white-domain   {whiteDomainsCount}
 * white-suffix   {whiteDomainSuffixCount}
 * white-keywords {whiteKeywordsCount}
 */

var PROXY = "{proxy};DIRECT";
var DIRECT = "DIRECT";
var maskMap = {maskMap};
var whiteIps = {whiteIps};
var whiteDomains = {whiteDomains};
var whiteDomainSuffix = {whiteDomainSuffix};
var whiteKeywords = {whiteKeywords};
var cacheMap = {};

function getCache(host) {
	return cacheMap[host];
}

function setCache(host, proxyType) {
	var cacheSize = Object.keys(cacheMap).length;
	if (cacheSize > 3000) {
		cacheMap = {};
	}
	cacheMap[host] = proxyType;
	return proxyType;
}

function isIPv4(ip) {
	// http://home.deds.nl/~aeron/regex/
	var regex = /^\d+\.\d+\.\d+\.\d+$/g;
	return regex.test(ip);
}

function isInNetEX(host) {
	for (var i = 0; i < whiteIps.length; i++) {
		var item = whiteIps[i];
		var ip = item[0];
		var mid = item[1];
		var mask = maskMap[mid];
		if(mask && isInNet(host, ip, mask)){
			return true;
		}
	}
}

function FindProxyForURL(url, host) {

	// 查询缓存
	var cacheValue = getCache(host);
	if (cacheValue) return cacheValue;

	// 简单域名
	if (isPlainHostName(host)) {
		return setCache(host, DIRECT);
	}

	// 域名白名单
	for (var i = 0; i < whiteDomains.length; i++) {
		if (host == whiteDomains[i]) {
			return setCache(host, DIRECT);
		}
	}

	// 域名后缀白名单
	for (var i = 0; i < whiteDomainSuffix.length; i++) {
		if (host == whiteDomainSuffix[i] || dnsDomainIs(host, '.' + whiteDomainSuffix[i])) {
			return setCache(host, DIRECT);
		}
	}

	// 域名关键字白名单
	for (var i = 0; i < whiteKeywords.length; i++) {
		if (shExpMatch(host, '*' + whiteKeywords[i] + '*')) {
			return setCache(host, DIRECT);
		}
	}

	// IP白名单
	if (isIPv4(host)) {
		if (isInNetEX(host)) {
			return setCache(host, DIRECT);
		}
	} else {
		var ip = dnsResolve(host)
		if (ip && isInNetEX(ip)) {
			return setCache(ip, DIRECT);
		}
	}

	return PROXY;
}