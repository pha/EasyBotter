<?php
//============================================================
//EasyBotter Ver2.1.2
//updated 2013/01/08
//============================================================
class EasyBotter
{    
        private $_screen_name;
        private $_consumer_key;
        private $_consumer_secret;
        private $_access_token;
        private $_access_token_secret;        
        private $_replyLoopLimit;
        private $_footer;
        private $_dataSeparator;        
        private $_tweetData;        
        private $_replyPatternData;        
        private $_logDataFile;
        private $_latestReply;
        
    function __construct()
    {                        
        //$dir = getcwd();
        //$path = $dir."/PEAR";
        $path = dirname(__FILE__) . "/PEAR";        
        set_include_path(get_include_path() . PATH_SEPARATOR . $path);
        $inc_path = get_include_path();
        chdir(dirname(__FILE__));
        date_default_timezone_set("Asia/Tokyo");        
        
        require_once("setting.php");
        $this->_screen_name = $screen_name;
        $this->_consumer_key = $consumer_key;
        $this->_consumer_secret = $consumer_secret;
        $this->_access_token = $access_token;
        $this->_access_token_secret = $access_token_secret;        
        $this->_replyLoopLimit = $replyLoopLimit;
        $this->_footer  = $footer;
        $this->_dataSeparator = $dataSeparator;        
        $this->_logDataFile = "log.dat";
        $this->_log = json_decode(file_get_contents($this->_logDataFile),true);
        $this->_latestReply = $this->_log["latest_reply"];
        $this->_latestReplyTimeline = $this->_log["latest_reply_tl"];                

        require_once("HTTP/OAuth/Consumer.php");  
		$this->OAuth_Consumer_build();
        $this->printHeader();
    }
       
    function __destruct(){
        $this->printFooter();        
    }

    //つぶやきデータを読み込む
    function readDataFile($file){
        if(preg_match("@\.php$@", $file) == 1){
            require_once($file);
            return $data;
        }else{
            $tweets = trim(file_get_contents($file));
            $tweets = preg_replace("@".$this->_dataSeparator."+@",$this->_dataSeparator,$tweets);
            $data = explode($this->_dataSeparator, $tweets);
            return $data;
        }
    }    
    //リプライパターンデータを読み込む
    function readPatternFile($file){
        $data = array();
        require_once($file);
        if(count($data) != 0){
            return $data;
        }else{
            return $reply_pattern;            
        }
    }    
    //どこまでリプライしたかを覚えておく
    function saveLog($name, $data){
        $this->_log[$name] = $data;
        file_put_contents($this->_logDataFile,json_encode($this->_log));        
    }        
    //表示用HTML
    function printHeader(){
        $header = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        $header .= '<html xmlns="http://www.w3.org/1999/xhtml" lang="ja" xml:lang="ja">';
        $header .= '<head>';
        $header .= '<meta http-equiv="content-language" content="ja" />';
        $header .= '<meta http-equiv="content-type" content="text/html; charset=UTF-8" />';
        $header .= '<title>EasyBotter</title>';
        $header .= '</head>';
        $header .= '<body><pre>';
        print $header;
    }
    //表示用HTML
    function printFooter(){
        echo "</body></html>";
    }

//============================================================
//bot.phpから直接呼び出す、基本の５つの関数
//============================================================

    //ランダムにポストする
    function postRandom($datafile = "data.txt"){        
        $status = $this->makeTweet($datafile);                
        if(empty($status)){
            $message = "投稿するメッセージがないようです。<br />";
            echo $message;
            return array("error"=> $message);
        }else{                
            //idなどの変換
            $status = $this->convertText($status);
            //フッターを追加
            $status .= $this->_footer;
            return $this->showResult($this->setUpdate(array("status"=>$status)), $status);            
        }    
    }    
    
    //順番にポストする
    function postRotation($datafile = "data.txt", $lastPhrase = FALSE){        
        $status = $this->makeTweet($datafile,0);                
        if($status !== $lastPhrase){
            $this->rotateData($datafile);        
            if(empty($status)){
                $message = "投稿するメッセージがないようです。<br />";
                echo $message;
                return array("error"=> $message);
            }else{                
                //idなどの変換
                $status = $this->convertText($status);    
                //フッターを追加
                $status .= $this->_footer;                       
                return $this->showResult($this->setUpdate(array("status"=>$status)), $status);            
            }
        }else{
            $message = "終了する予定のフレーズ「".$lastPhrase."」が来たので終了します。<br />";
            echo $message;
            return array("error"=> $message);
        }
    }    
    
    //リプライする
    function reply($cron = 2, $replyFile = "data.txt", $replyPatternFile = "reply_pattern.php"){
        $replyLoopLimit = $this->_replyLoopLimit;
        //リプライを取得
        $response = $this->getReplies($this->_latestReply);    
        $response = $this->getRecentTweets($response, $cron * $replyLoopLimit * 3);
        $replies = $this->getRecentTweets($response, $cron);
        $replies = $this->selectTweets($replies);
        if(count($replies) != 0){                           
            //ループチェック
            $replyUsers = array();
            foreach($response as $r){
                $replyUsers[] = $r["user"]["screen_name"];                
            }
            $countReplyUsers = array_count_values($replyUsers);
            $replies2 = array();
            foreach($replies as $rep){
                $userName = $rep["user"]["screen_name"];
                if($countReplyUsers[$userName] < $replyLoopLimit){
                    $replies2[] = $rep;
                }
            }            
            //古い順にする
            $replies2 = array_reverse($replies2);                   
            if(count($replies2) != 0){            
                //リプライの文章をつくる
                $replyTweets = $this->makeReplyTweets($replies2, $replyFile, $replyPatternFile);                
                $repliedReplies = array();
                foreach($replyTweets as $rep){
                    $response = $this->setUpdate(array("status"=>$rep["status"],'in_reply_to_status_id'=>$rep["in_reply_to_status_id"]));
                    $results[] = $this->showResult($response, $rep["status"]);            
                    if($response["in_reply_to_status_id_str"]){
                        $repliedReplies[] = $response["in_reply_to_status_id_str"];
                    }
                }
            }
        }else{
            $message = $cron."分以内に受け取った未返答のリプライはないようです。<br /><br />";
            echo $message;
            $results[] = $message;
        }
        
        //ログに記録
        if(!empty($repliedReplies)){
            rsort($repliedReplies);
            $this->saveLog("latest_reply",$repliedReplies[0]);
        }
        return $results;
    }
    
    //タイムラインに反応する
    function replyTimeline($cron = 2, $replyPatternFile = "reply_pattern.php"){
        //タイムラインを取得
        $timeline = $this->getFriendsTimeline($this->_latestReplyTimeline,100);       
        $timeline2 = $this->getRecentTweets($timeline, $cron);   
        $timeline2 = $this->selectTweets($timeline2);
        $timeline2 = array_reverse($timeline2);        
                
        if(count($timeline2) != 0){
            //リプライを作る        
            $replyTweets = $this->makeReplyTimelineTweets($timeline2, $replyPatternFile);
            if(count($replyTweets) != 0){
                $repliedTimeline = array();
                foreach($replyTweets as $rep){
                    $response = $this->setUpdate(array("status"=>$rep["status"],'in_reply_to_status_id'=>$rep["in_reply_to_status_id"]));
                    $result = $this->showResult($response, $rep["status"]);                    
                    $results[] = $result;
                    if(!empty($response["in_reply_to_status_id_str"])){
                        $repliedTimeline[] = $response["in_reply_to_status_id_str"];
                    }
                }
            }else{
                $message = $cron."分以内のタイムラインに未反応のキーワードはないみたいです。<br /><br />";
                echo $message;
                $results = $message;
            }
        }else{
            $message = $cron."分以内のタイムラインに未反応のキーワードはないみたいです。<br /><br />";
            echo $message;
            $results = $message;        
        }

        //ログに記録        
        if(!empty($repliedTimeline[0])){
            $this->saveLog("latest_reply_tl",$repliedTimeline[0]);
        }
        return $results;        
    }

    //自動フォロー返しする
    function autoFollow(){    
        $followers = $this->getFollowers();
        $friends = $this->getFriends();        
        $followlist = array_diff($followers["ids"], $friends["ids"]);        
        if($followlist){
            foreach($followlist as $id){    
                $response = $this->followUser($id);
                if(empty($response["errors"])){
                    echo $response["name"]."(@<a href='https://twitter.com/".$response["screen_name"]."'>".$response["screen_name"]."</a>)をフォローしました<br /><br />";
                }
            }
        }            
    }

//============================================================
//上の５つの関数から呼び出す関数
//============================================================
    
    //発言を作る
    function makeTweet($file, $number = FALSE){    
        //txtファイルの中身を配列に格納
        if(empty($this->_tweetData[$file])){
            $this->_tweetData[$file] = $this->readDataFile($file);        
        }        
        //発言をランダムに一つ選ぶ場合
        if($number === FALSE){
            $status = $this->_tweetData[$file][array_rand($this->_tweetData[$file])];
        }else{
        //番号で指定された発言を選ぶ場合
            $status = $this->_tweetData[$file][$number];            
        }       
        return $status;
    }    
    
    //リプライを作る
    function makeReplyTweets($replies, $replyFile, $replyPatternFile){
        if(empty($this->_replyPatternData[$replyPatternFile]) && !empty($replyPatternFile)){
            $this->_replyPatternData[$replyPatternFile] = $this->readPatternFile($replyPatternFile);
        }        
        $replyTweets = array();
        
        foreach($replies as $reply){        
            $status = "";
            //指定されたリプライパターンと照合
            if(!empty($this->_replyPatternData[$replyPatternFile])){
                foreach($this->_replyPatternData[$replyPatternFile] as $pattern => $res){
                    if(preg_match("@".$pattern."@u",$reply["text"], $matches) === 1){                                        
                        $status = $res[array_rand($res)];
                        for($i=1;$i <count($matches);$i++){
                            $p = "$".$i;  //エスケープ？
                            $status = str_replace($p,$matches[$i],$status);
                        }
                        break;
                    }
                }            
            }
                         
            //リプライパターンにあてはまらなかった場合はランダムに
            if(empty($status) && !empty($replyFile)){
                $status = $this->makeTweet($replyFile);
            }
            if(empty($status) || stristr($status,"[[END]]")){
                continue;
            }            
            //idなどを変換
            $status = $this->convertText($status, $reply);
            //フッターを追加
            $status .= $this->_footer;
            //リプライ相手、リプライ元を付与
            $re["status"] = "@".$reply["user"]["screen_name"]." ".$status;
            $re["in_reply_to_status_id"] = $reply["id_str"];
            
            //応急処置
            if(!stristr($status,"[[END]]")){
                $replyTweets[] = $re;
            } 
        }                        
        return $replyTweets;    
    }
    
    //タイムラインへの反応を作る
    function makeReplyTimelineTweets($timeline, $replyPatternFile){
        if(empty($this->_replyPatternData[$replyPatternFile])){
            $this->_replyPatternData[$replyPatternFile] = $this->readPatternFile($replyPatternFile);
        }
        $replyTweets = array();        
        foreach($timeline as $tweet){
            $status = "";
            //リプライパターンと照合
            foreach($this->_replyPatternData[$replyPatternFile] as $pattern => $res){
                if(preg_match("@".$pattern."@u",$tweet["text"], $matches) === 1 && !preg_match("/\@/i",$tweet["text"])){                                        
                    $status = $res[array_rand($res)];
                    for($i=1;$i <count($matches);$i++){
                        $p = "$".$i;
                        $status = str_replace($p,$matches[$i],$status);
                    }
                    break;                    
                }                
            }
            if(empty($status)){
                continue;
            }
            //idなどを変換
            $status = $this->convertText($status, $tweet);
            //フッターを追加
            $status .= $this->_footer;

            //リプライ相手、リプライ元を付与
            $rep = array();
            $rep["status"] = "@".$tweet["user"]["screen_name"]." ".$status;
            $rep["in_reply_to_status_id"] = $tweet["id_str"];      
            //応急処置
            if(!stristr($status,"[[END]]")){
                $replyTweets[] = $rep;
            }
        }                        
        return $replyTweets;    
    }        
    
    //ログの順番を並び替える
    function rotateData($file){
        $tweetsData = file_get_contents($file);
        $tweets = explode("\n", $tweetsData);
        $tweets_ = array();
        for($i=0;$i<count($tweets) - 1;$i++){
            $tweets_[$i] = $tweets[$i+1];
        }
        $tweets_[] = $tweets[0];
        $tweetsData_ = "";
        foreach($tweets_ as $t){
            $tweetsData_ .= $t."\n";
        }
        $tweetsData_ = trim($tweetsData_);        
        $fp = fopen($file, 'w');
        fputs($fp, $tweetsData_);
        fclose($fp);            
    }
    
    //つぶやきの中から$minute分以内のものと、最後にリプライしたもの以降のものだけを返す
    function getRecentTweets($tweets,$minute){    
        $tweets2 = array();
        $now = strtotime("now");
        $limittime = $now - $minute * 70; //取りこぼしを防ぐために10秒多めにカウントしてる    
        foreach($tweets as $tweet){
            $time = strtotime($tweet["created_at"]);    
            if($limittime <= $time){                    
                $tweets2[] = $tweet;                
            }else{
                break;                
            }
        }    
        return $tweets2;    
    }
    
    //取得したつぶやきを条件で絞る
    function selectTweets($tweets){    
        $tweets2 = array();
        foreach($tweets as $tweet){
            //自分自身のつぶやきを除外する
            if($this->_screen_name == $tweet["user"]["screen_name"]){
                continue;
            }                        
            //RT, QTを除外する
            if(strpos($tweet["text"],"RT") != FALSE || strpos($tweet["text"],"QT") != FALSE){
                continue;
            }                        
            $tweets2[] = $tweet;                                        
        }    
        return $tweets2;    
    }                            
    
    //文章を変換する
    function convertText($text, $reply = FALSE){        
        $text = str_replace("{year}",date("Y"),$text);
        $text = str_replace("{month}",date("n"),$text);
        $text = str_replace("{day}",date("j"),$text);
        $text = str_replace("{hour}",date("G"),$text);
        $text = str_replace("{minute}",date("i"),$text);
        $text = str_replace("{second}",date("s"),$text);    
              
        //タイムラインからランダムに最近発言した人のデータを取る
        if(strpos($text,"{timeline_id}") !== FALSE){
            $randomTweet = $this->getRandomTweet();
            $text = str_replace("{timeline_id}", $randomTweet["user"]["screen_name"],$text);
        }
        if(strpos($text, "{timeline_name}") !== FALSE){
            $randomTweet = $this->getRandomTweet();
            $text = str_replace("{timeline_name}",$randomTweet["user"]["name"],$text);
        }

        //使うファイルによって違うもの
        //リプライの場合は相手のid、そうでなければfollowしているidからランダム
        if(strpos($text,"{id}") !== FALSE){
            if(!empty($reply)){
                $text = str_replace("{id}",$reply["user"]["screen_name"],$text);                
            }else{
                $randomTweet = $this->getRandomTweet();
                $text = str_replace("{id}",$randomTweet["user"]["screen_name"],$text);        
            }
        }
        if(strpos($text,"{name}") !== FALSE){
            if(!empty($reply)){
                $text = str_replace("{name}",$reply["user"]["name"],$text);                
            }else{
                $randomTweet = $this->getRandomTweet();
                $text = str_replace("{name}",$randomTweet["user"]["name"],$text);        
            }
        }
                
        //リプライをくれた相手のtweetを引用する
        if(strpos($text,"{tweet}") !== FALSE && !empty($reply)){
            $tweet = preg_replace("@\.?\@[a-zA-Z0-9-_]+\s@u","",$reply["text"]); //@リプライを消す        
            $text = str_replace("{tweet}",$tweet,$text);                                   
        }            
                
        return $text;
    }    

    //タイムラインの最近30件の呟きからランダムに一つを取得
    function getRandomTweet($num = 30){
        $response = $this->getFriendsTimeline(NULL, $num);         
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }else{           
            for($i=0; $i < $num;$i++){             
                $randomTweet = $response[array_rand($response)];
                if($randomTweet["user"]["screen_name"] != $this->_screen_name){
                    return $randomTweet;                
                }
            }
        }
        return false;
    }
    
    //結果を表示する
    function showResult($response, $status = NULL){    
        if(empty($response["errors"])){
            $message = "Twitterへの投稿に成功しました。<br />";
            $message .= "@<a href='http://twitter.com/".$response["user"]["screen_name"]."' target='_blank'>".$response["user"]["screen_name"]."</a>";
            $message .= "に投稿したメッセージ：".$response["text"];
            $message .= " <a href='http://twitter.com/".$response["user"]["screen_name"]."/status/".$response["id_str"]."' target='_blank'>http://twitter.com/".$response["user"]["screen_name"]."/status/".$response["id_str"]."</a><br /><br />";
            echo $message;
            return array("result"=> $message);
        }else{
            $message = "「".$status."」を投稿しようとしましたが失敗しました。<br />";
            echo $message;
            echo $response["errors"][0]["message"];               
            echo "<br /><br />";
            return array("error" => $message);
        }
    }


//============================================================
//基本的なAPIを叩くための関数
//============================================================
    function _setData($url, $value = array()){
		$this->OAuth_Consumer_build();//ここでHTTP_OAuth_Consumerを作り直し
        return json_decode($this->consumer->sendRequest($url, $value, "POST")->getBody(), true);
    }    

    function _getData($url){
		$this->OAuth_Consumer_build();//ここでHTTP_OAuth_Consumerを作り直し
        return json_decode($this->consumer->sendRequest($url, array(), "GET")->getBody(), true);
    }    

	function OAuth_Consumer_build(){
        $this->consumer = new HTTP_OAuth_Consumer($this->_consumer_key, $this->_consumer_secret);    
        $http_request = new HTTP_Request2();  
        $http_request->setConfig('ssl_verify_peer', false);  
        $consumer_request = new HTTP_OAuth_Consumer_Request;  
        $consumer_request->accept($http_request);  
        $this->consumer->accept($consumer_request);  
        $this->consumer->setToken($this->_access_token);  
        $this->consumer->setTokenSecret($this->_access_token_secret);
		return;                
	}

    function setUpdate($value){        
        $url = "http://api.twitter.com/1.1/statuses/update.json";
        return $this->_setData($url,$value);
    }            

    function getReplies($since_id = NULL){
        $url = "http://api.twitter.com/1.1/statuses/mentions_timeline.json?";        
        if ($since_id) {
            $url .= 'since_id=' . $since_id ."&";
        }
        $url .= "count=100";
        $response = $this->_getData($url);
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }                   
        return $response;
    }        

    function getFriendsTimeline($since_id = 0, $num = 100){
        $url = "https://api.twitter.com/1.1/statuses/home_timeline.json?";
        if ($since_id) {
            $url .= 'since_id=' . $since_id ."&";
        }        
        $url .= "count=" .$num ;
        $response = $this->_getData($url);
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }                   
        return $response;
    }

    function followUser($id)
    {    
        $url = "https://api.twitter.com/1.1/friendships/create.json";
        $value = array("user_id"=>$id, "follow"=>"true");
        return $this->_setData($url,$value);
    }
    
    function getFriends($id = null)
    {
        $url = "https://api.twitter.com/1.1/friends/ids.json";
        $response = $this->_getData($url);
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }                   
        return $response;
    }    

    function getFollowers()
    {
        $url = "https://api.twitter.com/1.1/followers/ids.json";
        $response = $this->_getData($url);
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }                   
        return $response;
    }
        
    function checkApi($resources = "statuses"){
        $url = "https://api.twitter.com/1.1/application/rate_limit_status.json";
        if ($resources) {
            $url .= '?resources=' . $resources;
        }
        $response = $this->_getData($url);    
        var_dump($response);
    }    
}
?>