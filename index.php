<?php

// Composerでインストールしたライブラリを一括読み込み
require_once __DIR__ . '/vendor/autoload.php';

// テーブル名を定義
define('TABLE_NAME_CONVERSATIONS', 'conversations');

  // パラメータ
  $data = array('input' => array("text" => $event->getText()));

  // 前回までの会話のデータがデータベースに保存されていれば
  if(getLastConversationData($event->getUserId()) !== PDO::PARAM_NULL) {
    $lastConversationData = getLastConversationData($event->getUserId());
    // 前回までの会話のデータをパラメータに追加
    $data["context"] = array("conversation_id" => $lastConversationData["conversation_id"],
      "system" => array("dialog_stack" => array(array("dialog_node" => $lastConversationData["dialog_node"])),
      "dialog_turn_counter" => 1,
      "dialog_request_counter" => 1));
  }

  // ConversationサービスのREST API
  $url = 'https://gateway.watsonplatform.net/conversation/api/v1/workspaces' . getenv('WATSON_WORKSPACE_ID') . '/message?version=2016-09-20';
  // 新規セッションを初期化
  $curl = curl_init($url);

  // オプション
  $options = array(
    // コンテンツタイプ
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
    ),
    // 認証用
    CURLOPT_USERPWD => getenv('WATSON_USERNAME') . ':' . getenv('WATSON_PASSWORD'),

    // POST
    CURLOPT_POST => true,
    // 内容
    CURLOPT_POSTFIELDS => json_encode($data),
    // curl_exec時にbooleanでなく取得結果を返す
    CURLOPT_RETURNTRANSFER => true,
  );

  // オプションを適用
  curl_setopt_array($curl, $options);
  // セッションを実行し結果を取得
  $jsonString = curl_exec($curl);
  // 文字列を連想配列に変換
  $json = json_decode($jsonString, true);

  // 会話データを取得
  $conversationId = $json["context"]["conversation_id"];
  $dialogNode = $json["context"]["system"]["dialog_stack"][0]["dialog_node"]

  // データベースに保存
  $conversationData = array('conversation_id' => $conversationId, 'dialog_node' => $dialogNode);
  setLastConversationData($event->getUserId(), $conversationData);

  // Conversationからの返答を取得
  $outputText =$json['output']['text'][count($json['output']['text']) - 1];

  // ユーザーに返信
  replyTextMessage($bot, $event->getReplyToken(), $outputText);
}



// 会話データをデータベースに保存
function setLastConversationData($userId, $lastConversationData) {
  $conversationId = $lastConversationData['conversation_id'];
  $dialogNode = $lastConversationData['dialog_node'];

  if(getLastConversationData($userId) === PDO::PARAM_NULL) {
    $dbh = dbConnection::getConnection();
    $sql = 'insert into ' . TABLE_NAME_CONVERSATIONS . ' (conversation_id, dialog_node, userId) values (?, ?, pgp_sym_encrypt(?, \'' . getenv('DB_ENCRYPT_PASS') . '\'))';
    $sth = $dbh->prepare($sql);
    $sth->execute(array($conversationId, $dialogNode, $userId));
  } else {
    $dbh = dbConnection::getConnection();
    $sql = 'update ' . TABLE_NAME_CONVERSATIONS . ' set conversation_id =　?, dialog_node = ? where ? = pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
    $sth = $dbh->prepare($sql);
    $sth->execute(array($conversationId, $dialogNode, $userId));
  }
}

// データベースから会話データを取得
function getLastConversationData($userId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select conversation_id, dialog_node from ' . TABLE_NAME_CONVERSATIONS . ' where ? = pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return array('conversation_id' => $row['conversation_id'], 'dialog_node' => $row['dialog_node']);
  }
}



// データベースへの接続を管理するクラス
class dbConnection {
  // インスタンス
  protected static $db;
  // コンストラクタ
  private function __construct() {

    try {
      // 環境変数からデータベースへの接続情報を取得し
      $url = parse_url(getenv('DATABASE_URL'));
      // データソース
      $dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));
      // 接続を確立
      self::$db = new PDO($dsn, $url['user'], $url['pass']);
      // エラー時例外を投げるように設定
      self::$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }
    catch (PDOException $e) {
      echo 'Connection Error: ' . $e->getMessage();
    }
  }

/*
  // シングルトン。存在しない場合のみインスタンス化
  public static function getConnection() {
    if (!self::$db) {
      new dbConnection();
    }
    return self::$db:
  }
*/
}

/*
// アクセストークンを使いCurlHTTPClientをインスタンス化
$httpClient = new ¥src¥LINEBot¥HTTPClient¥CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

// CurlHTTPClientとシークレットを使いLINEBotをインスタンス化
$bot = new ¥src¥LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);

// LINE Messaging APIがリクエストに付与した署名を取得
$signature = $_SERVER['HTTP_' . ¥src¥LINEBot¥Constant¥HTTPHeader::LINE_SIGNATURE];

// 署名が正当かチェック。正当であればリクエストをパースし配列へ
// 不正であれば例外の内容を出力
try {
  $events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
} catch(¥src¥LINEBot¥Exception¥InvalidSignatureException $e) {
  error_log('parseEventRequest failed. InvalidSignatureException => '.var_export($e, true));
} catch(¥src¥LINEBot¥Exception¥UnknownEventTypeException $e) {
  error_log('parseEventRequest failed. UnknownEventTypeException => '.var_export($e, true));
} catch(¥src¥LINEBot¥Exception¥UnknownMessageTypeException $e) {
  error_log('parseEventRequest failed. UnknownMessageTypeException => '.var_export($e, true));
} catch(¥src¥LINEBot¥Exception¥InvalidEventRequestException $e) {
  error_log('parseEventRequest failed. InvalidEventRequestException => '.var_export($e, true));
}
// 配列に格納された各イベントをループで処理
foreach ($events as $events) {
  // MessageEventクラスのインスタンスでなければ処理をスキップ
  if (!($event instanceof ¥src¥LINEBot¥Event¥MessageEvent)) {
    error_log('Non message event has come');
    continue;
  }
  // TextMessageクラスのインスタンスでなければ処理をスキップ
  if (!($event instanceof ¥src¥LINEBot¥Event¥MessageEvent¥TextMessage)) {
    error_log('Non text message has come');
    continue;
  }
}
*/
?>
