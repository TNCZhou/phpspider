<?php
#sleep(72000);
ini_set('memory_limit', -1);

class spider{
	
	private $fids = [2=>'寒山闻钟',38=>'闻钟漫画',39=>'闻钟评谈',40=>'闻钟书苑'];
	private $mongoCollection = 'db1.hswz';
    private $mongoCollectionPostThread = 'db1.hswzthread';
	private $mongo = '';
    private $redis = '';
	
	public function __construct(){
		$this->mongo = new MongoDB\Driver\Manager('mongodb://localhost:27017');
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1','6379');
        $this->redis->select(0);
        echo $this->redis->ping()."\n";
	}
	
	public function start(){
        $this->redis->delete('agents');
        $this->redis->delete('disabled');
        $this->getHttpAgents();
		swoole_timer_tick(5000, function(){
            $this->getHttpAgents();
		});
        $this->getHSWZConetents();
        //swoole_timer_tick(7200000, function(){
        //    foreach($this->fids as $fid => $fname){
        //        for($i=1;$i<=10;$i++){
        //            $this->getOneContent("http://www.12345.suzhou.gov.cn/bbs/forum.php?mod=forumdisplay&fid=".$fid."&page=".$i, $fid);
        //        }
        //    }
		//});
        
	}
    
    private function getHttpAgents(){
        $ipnums = $this->redis->scard('agents');
        $disabled = $this->redis->scard('disabled');
        echo "ippool:".$ipnums."\n";
        //if($ipnums < 3000) {
            //$this->getHttpAgents1();
            //$this->getHttpAgents2();
            $this->getHttpAgents3();
            //$this->getHttpAgents4();
        //}
    }
    
    private function getHttpAgents1(){
        for($i=1;$i<=3;$i++){
            $this->getWebContentAsync("http://www.xicidaili.com/nn/".$i,false,function($html){
                preg_match_all('/<tr[\s\S]*?<td[\s\S]*?<\/td>\s*<td>([.\d]+)<\/td>\s*<td>(\d+)<\/td>[\s\S]*?<\/tr>/',$html,$match);
                unset($html);
                if(!empty($match[0])){
                    foreach($match[0] as $k=>$v){
                        $this->redis->sadd('agents',$match[1][$k].":".$match[2][$k]);
                    }
                }
            });
        }
    }
    
    private function getHttpAgents2(){
        for($i=1;$i<=3;$i++){
            $url = "https://www.kuaidaili.com/free/inha/".$i."/";
            $this->getWebContentAsync($url,false,function($html) use ($url){
                preg_match_all('/<tr>\s*<td\s*data\-title\="IP">(.*)<\/td>\s*<td\sdata\-title\=\"PORT\">(.*)<\/td>/',$html,$match);
                unset($html);
                if(!empty($match[0])){
                    foreach($match[0] as $k=>$v){
                        $this->redis->sadd('agents',$match[1][$k].":".$match[2][$k]);
                    }
                }
            });
        }
    }
    
    private function getHttpAgents3(){
        $url = "http://www.66ip.cn/nmtq.php?getnum=500&isp=0&anonymoustype=0&start=&ports=&export=&ipaddress=&area=0&proxytype=0&api=66ip";
        $this->getWebContentAsync($url,false,function($html) use ($url){
            preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\:\d+)/',$html,$match);
            unset($html);
            if(!empty($match[0])){
                foreach($match[0] as $k=>$v){
                    //if(!$this->redis->sismember('disabled',$match[1][$k])){
                        $this->redis->sadd('agents',$match[1][$k]);
                    //}
                }
            }
        });
    }
    
    private function getHttpAgents4(){
        $url = "http://api.ip.data5u.com/dynamic/get.html?order=ec23d097943f20d899d3d4084499a23e&sep=3";
        $this->getWebContentAsync($url,false,function($html) use ($url){
            echo trim($html)."\n";
            $ip = explode("\n",trim($html));
            foreach($ip as $v){
                if(preg_match('/[\d\.\:]+/',$v)){
                    $this->redis->sadd('agents',$v);
                }
            }
        });
    }
    
    private function getHSWZConetents(){
        file_put_contents("/data/www/logs/startJob.log",date('Y-m-d H:i:s')."\n",FILE_APPEND);
        foreach($this->fids as $fid=>$fname){
            $this->getOneContent("http://www.12345.suzhou.gov.cn/bbs/forum.php?mod=forumdisplay&fid=".$fid."&page=1", $fid, true);
        }
    }
    
    private function getOneContent($url, $fid, $useAgent = true){
        $agentip = $agentport = $agent ='';
        if($useAgent){
            $agent = $this->redis->srandmember('agents');
            if($agent){
                list($agentip, $agentport) = explode(':',$agent);
            } else {
                //echo "waiting for agents...\n";
                swoole_timer_after(1000, function() use ($url, $fid){
                    $this->getOneContent($url, $fid);
                });
                return;
            }
        }
        $this->getWebContentAsync($url, $useAgent?[$agentip,$agentport]:false, function($html) use ($url, $agent, $fid, $useAgent) {
            preg_match('/[\s\S]*?<span\s*id="fd_page_bottom">[\s\S]*?共(.*?)页[\s\S]*/',$html,$match);
            $maxpage = isset($match[1])?intval(trim($match[1])):0;
            if(!$maxpage) {
                //file_put_contents('/data/www/logs/error'.date('Y-m-d').'.log',date('Y-m-d H:i:s')."\t".$url."\n".$html,FILE_APPEND);
                //echo "retry:".$url.",invalid agent:".$agent."\n";
                if($this->redis->sismember('agents',$agent)){
                    $this->redis->srem('agents',$agent);
                   // $this->redis->sadd('disabled',$agent);
                }
                $this->getOneContent($url, $fid, $useAgent);
                return;
            }
            preg_match_all('/<tbody\s+id="(.+)thread_(\d+)">[\s\S]*?<th[\s\S]*?<em>\[<a.*?>(.*?)<\/a>\]<\/em>[\s\S]*?(?=<em>\[(.*?)\]<\/em>)?[\s\S]*?<a.*?>(.*?)<\/a>\s*(?=<img.*alt="(已关注|已处理)".*\/>)?[\s\S]*?<\/th>\s*<td[\s\S]*?<a.*?uid=(\d+)".*?>(.*?)<\/a>[\s\S]*?<span[^>]*>(.*)<\/span>[\s\S]*?<a.*?>(\d+)<\/a><em>(\d+)<\/em>[\s\S]*?<a.*?>(.*)<\/a>[\s\S]*?<a.*?>(.*)<\/a>[\s\S]*?<\/tbody>/',$html,$match);
            
            unset($html);
            
            $bulk = new MongoDB\Driver\BulkWrite();
            foreach($match[0] as $k=>$v){
                $data = [
                    '_id' => intval($match[2][$k]),
                    'isstick' => $match[1][$k] == 'stick',
                    'type' => $match[3][$k],
                    'area' => $match[4][$k],
                    'title' => $match[5][$k],
                    'status' => $match[6][$k],
                    'authorid' => intval($match[7][$k]),
                    'author' => $match[8][$k],
                    'pubdate' => $this->formatDate($match[9][$k]),
                    'reply' => intval($match[10][$k]),
                    'view' => intval($match[11][$k]),
                    'lastreplyuser' => $match[12][$k],
                    'lastreplydata' => $this->formatDate($match[13][$k]),
                    'forum' => $this->fids[$fid]
                ];
                if(!$data['_id'])
                    continue;
                
                $this->getThreadDetail($data['_id']);
                
                $bulk->update(
                    ['_id' => $data['_id']],
                    ['$set' => $data],
                    ['multi' => false, 'upsert' => true]
                );
            }
            
            unset($match);
            
            if($bulk->count()){
                $result = $this->mongo->executeBulkWrite($this->mongoCollection, $bulk);
                file_put_contents('/data/www/logs/success-'.date('Y-m-d').'.log',date('Y-m-d H:i:s')."\t".$url."\t"."total:".$bulk->count()."\t"."insert:".$result->getUpsertedCount()."\t"."update:".$result->getModifiedCount()."\n",FILE_APPEND);
                
                echo $url."..............done,ip:".$agent."\n";
                
                if(!$useAgent) {
                    $this->sendNextPagesRequest($fid, $maxpage);
                }
            }
        });
    }
    
    private function getThreadDetail($id, $page=1){
        $agent = $this->redis->srandmember('agents');
        if($agent){
            list($agentip, $agentport) = explode(':',$agent);
        } else {
            //echo "waiting for agents...\n";
            swoole_timer_after(1000, function() use ($id){
                $this->getThreadDetail($id);
            });
            return;
        }
        
        $this->getWebContentAsync("http://www.12345.suzhou.gov.cn/bbs/forum.php?mod=viewthread&tid=".$id."&page=".$page, [$agentip,$agentport], function($html) use ($id, $agent, $page) {
            preg_match('/<span\s+id="scrolltop".*>/',$html,$match);
            if(empty($match)){
                file_put_contents("/data/www/logs/error_page.log",$match,FILE_APPEND);
                if($this->redis->sismember('agents',$agent)){
                    $this->redis->srem('agents',$agent);
                    //$this->redis->sadd('disabled',$agent);
                }
                $this->getThreadDetail($id, $page);
                return;
            }
            if($page == 1) {
                preg_match('/<div\s+class="pg">[\s\S]*?共(.*?)页[\s\S]*/',$html,$match);
                $maxpage = isset($match[1])?intval(trim($match[1])):0;
                $this->sendThreadDetailNextPages($id, $maxpage);
            }
            preg_match_all('/<div\s+id="post\_(\d+)"[\s\S]*?<div\s+id="favatar\1"[\s\S]*?<a\s+href=".*?uid=(\d+)".*?class="xw1">(.*?)<\/a>[\s\S]*?<a.*?uid=\2"\s+class="avtm".*?><img\s+src="(.*?)"\s\/>[\s\S]*?<em\s+id="authorposton\1">发表于(.*?)<\/em>[\s\S]*?<td.*?id="postmessage\_\1">([\s\S]*?)<\/td>/',$html,$match);
            
            unset($html);
            
            $bulk = new MongoDB\Driver\BulkWrite();
            foreach($match[0] as $k=>$v){
                $data = [
                    '_id' => intval($match[1][$k]),
                    'uid' => intval($match[2][$k]),
                    'username' => trim($match[3][$k]),
                    'avatar' => trim($match[4][$k]),
                    'pubdate' => $this->formatDate($match[5][$k]),
                    'content' => trim($match[6][$k]),
                    'threadid' => $id,
                ];
                if(!$data['_id'])
                    continue;
                    
                $bulk->update(
                    ['_id' => $data['_id']],
                    ['$set' => $data],
                    ['multi' => false, 'upsert' => true]
                );
            }
            
            unset($match);
            
            if($bulk->count()){
                $result = $this->mongo->executeBulkWrite($this->mongoCollectionPostThread, $bulk);
                file_put_contents('/data/www/logs/thread-success-'.date('Y-m-d').'.log',date('Y-m-d H:i:s')."\t".$id."\t".$page."\t"."total:".$bulk->count()."\t"."insert:".$result->getUpsertedCount()."\t"."update:".$result->getModifiedCount()."\n",FILE_APPEND);
                echo "http://www.12345.suzhou.gov.cn/bbs/forum.php?mod=viewthread&tid=".$id."&page=".$page."..............done,ip:".$agent."\n";
            }
        });
        
    }
    
    private function formatDate($date){
        if(preg_match('/<span\s*title="(.*)">/',$date,$match)){
            $date = $match[1];
        }
        $timestamp = strtotime($date);
        return $timestamp?$timestamp:$date;
    }
    
    private function sendNextPagesRequest($fid, $maxpage) {
        for($i=2;$i<=$maxpage;$i++){
            $this->getOneContent("http://www.12345.suzhou.gov.cn/bbs/forum.php?mod=forumdisplay&fid=".$fid."&page=".$i, $fid);
        }
    }
    
    private function sendThreadDetailNextPages($id, $maxpage) {
        for($i=2;$i<=$maxpage;$i++){
            $this->getThreadDetail($id, $i);
        }
    }
	
	private function getWebContentAsync($url, $agent, $callback, $cookie=[]) {
        //echo "send request:".$url.",ip:".$agent[0].":".$agent[1]."\n";
		$parseUrl = parse_url($url);
		Swoole\Async::dnsLookup($parseUrl['host'], function ($domainName, $ip) use ($url, $agent, $callback, $cookie) {
            $parseUrl = parse_url($url);
            if(!$ip || !$domainName) {
                $this->getWebContentAsync($url, $agent, $callback, $cookie);
                return;
            }
            
			//$clientip1 = mt_rand(11, 191).".".mt_rand(1, 240).".".mt_rand(1, 240).".".mt_rand(1, 240);
			//$clientip2 = mt_rand(11, 191).".".mt_rand(1, 240).".".mt_rand(1, 240).".".mt_rand(1, 240);
            /*
			$userAgents = [
				"Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.57 Safari/536.11",
				"Mozilla/5.0 (Windows; U; Windows NT 6.1; en-us) AppleWebKit/534.50 (KHTML, like Gecko) Version/5.1 Safari/534.50",
				"Mozilla/5.0 (Windows NT 10.0; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0",
				"Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)",
				"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)",  
				"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)",
				"Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:2.0.1) Gecko/20100101 Firefox/4.0.1",
				"Mozilla/5.0 (Windows NT 6.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1",
				"Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; en) Presto/2.8.131 Version/11.11",
				"Opera/9.80 (Windows NT 6.1; U; en) Presto/2.8.131 Version/11.11",
				"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_0) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.56 Safari/535.11",
				"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Maxthon 2.0)",
				"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; TencentTraveler 4.0)",
				"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)",
				"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; The World)",
				"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; 360SE)",
				"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SE 2.X MetaSr 1.0; SE 2.X MetaSr 1.0; .NET CLR 2.0.50727; SE 2.X MetaSr 1.0)",
				"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Avant Browser)",
				"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)"
			];
            */
			$cli = new Swoole\Http\Client($ip, isset($parseUrl['port'])?:80);
            $setting = [
				'timeout' => 5
			];
            
            if($agent) {
                $setting['http_proxy_host'] = $agent[0];
                $setting['http_proxy_port'] = $agent[1];
            }
            
			$cli->set($setting);
			$cli->setHeaders([
				'Host' => $domainName,
				////'CLIENT-IP' => $clientip1,
				//'X-FORWARDED-FOR' => $clientip2,
				//'User-Agent' => $userAgents[array_rand($userAgents,1)],
				'Accept' => 'text/html,application/xhtml+xml,application/xml',
				'Accept-Encoding' => 'gzip,deflate',
			]);
            $cli->setCookies($cookie);
			$cli->get($parseUrl['path'].(isset($parseUrl['query'])?"?".$parseUrl['query']:''), function ($cli) use ($callback, $url, $agent) {
                if(preg_match('/location\.reload\(\)/',$cli->body)){
                    //echo $url."   ".$agent[0].":".$agent[1]."\n";
                    $this->getWebContentAsync($url, $agent, $callback, $cli->cookies);
                }else{
                    call_user_func($callback, $cli->body, $url);
                }
			});
		});	
	}
}

$spider = new spider();
$spider->start();
?>