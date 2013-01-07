<?php
//設定
$screen_name = ""; //botのid名
$consumer_key = "WtNt5W6js77cFHvKyVgf6Q"; // Consumer keyの値
$consumer_secret = "h3In28YiiRxtg4PTpkhPmrlJSGOCTG2oh3hslQ"; // Consumer secretの値
$access_token = ""; // Access Tokenの値
$access_token_secret = ""; // Access Token Secretの値

//高度な設定
$replyLoopLimit = 3; //リプライのループを防ぐための設定です。大体3回くらいで会話が止まります（多分……）
$footer = ""; //ここにフッターを設定すると発言するときいつも末尾に追加されます
$dataSeparator = "\n"; //data.txtの区切り文字です。改行が無視されるときはここに,（コンマ）などを設定してそれで区切ってください