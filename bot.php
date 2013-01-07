<?php
//=============================
//EasyBotterを呼び出します
//=============================
require_once("EasyBotter.php");
$eb = new EasyBotter();

//=============================
//botの動作をこの下に記述していきます。
//PHPでは先頭に//がある行はコメント扱いなので実行しません。実行したい行の先頭の//を削除してください。
//=============================
//$response = $eb->autoFollow();
//$response = $eb->postRandom("data.txt"); 
//$response = $eb->postRotation("data.txt","おわり");
//$response = $eb->reply(2,"data.txt","reply_pattern.php");
//$response = $eb->replyTimeline(2,"reply_pattern.php");


/*
//===================================================
//EasyBotter 簡易マニュアル
//===================================================
////ここから下は作者による解説です。
////cronなどでこのbot.phpを実行するわけですが、動作の指定の仕方はこんな感じです。

//用意したデータをランダムにポストしたい
$response = $eb->postRandom("データを書き込んだファイル名"); 

//用意したデータを順番にポストしたい
$response = $eb->postRotation("データを書き込んだファイル名"); 

//@で話しかけられたときにリプライしたい
$response = $eb->reply(cronで実行する間隔（単位：分）, "データを書き込んだファイル名", "パターン反応を書き込んだファイル名"); 

//タイムラインの単語に反応してリプライしたい
$response = $eb->replyTimeline(cronで実行する間隔（単位：分）,"パターン反応を書き込んだファイル名"); 

//自動でフォロー返ししたい
$response = $eb->autoFollow();


//===================================================
//// cronを実行するたびに毎回実行するのではなく、
//// 実行する頻度を変えたい場合の例は以下のとおりです。
//// PHPのdata()の応用の仕方は以下のURLを参照ください。
//// http://php.net/manual/ja/function.date.php

//bot.phpを実行したときに毎回実行される
$response = $eb->postRandom("data.txt");

//bot.phpを実行したときに、5回に1回ランダムに実行される
if(rand(0,4) == 0){
    $response = $eb->postRandom("data.txt");
}

//bot.phpを実行したときに、0分、15分、30分、45分だったら実行される
if(date("i") % 15 == 0){
    $response = $eb->postRandom("data.txt");
}

//bot.phpを実行したときに、午前だったらgozen.txtのデータを、午後だったらgogo.txtのデータを使う
if(date("G") < 12){
    $response = $eb->postRandom("gozen.txt");
}else{
    $response = $eb->postRandom("gogo.txt");    
}

//bot.phpを実行したときに、2月14日のみvalentine.txtのデータを、それ以外はdata.txtのデータを使う
if(date("n") == 2 && date("j") == 14){
    $response = $eb->postRandom("valentine.txt");
}else{
    $response = $eb->postRandom("data.txt");    
}

//準備したテキストを順番にポストしていって、準備した中から「めでたしめでたし」が投稿されたらbotの投稿をそこで止める
$response = $eb->postRotation("data.txt","めでたしめでたし");    
*/
