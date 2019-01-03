<?php

// - - - - - - - - - - - - - - - - - - - -
//
// TrendTags PHP - トレンドタグ bot for PHP
//   by yakitama (Originaly written by fvh-P)
//
// main.php : 実行開始用スクリプト
//
// スクリプトの引数
//   -m : 動作モードを引数に続けて指定します。必須オプションです。
//      :   d で diff を意味する前回のトレンドスコア更新時との差分を報告します。
//      :   h で high を意味する前日のトレンドスコアのハイスコアを報告します。
//   -n : この引数を指定すると、報告せずに報告内容を標準出力に出力します。
//   -d : デバッグモードで動作します。トレンドタグの更新有無にかかわらず、最後まで動作します。
//
// - - - - - - - - - - - - - - - - - - - -

define("EXEC_MODE_DIFF", 1);
define("EXEC_MODE_HIGH", 2);
define("EXEC_MODE_UNKNOWN", 0);

// 報告するタグの数
define("REPORT_TAGS_NUM", 5);

$options = getopt("m:nd");

// 必須オプションが指定されているか検査
if ( !isset($options['m']) ) {
	fprintf(STDERR, "m オプションが指定されていません。処理を終了します。");
	exit(1);
}

// 動作モードを処理しやすい形式で保持
$execution_mode = EXEC_MODE_UNKNOWN;
switch ( $options['m'] ) {
	case 'd':
		$execution_mode = EXEC_MODE_DIFF;
		break;
	case 'h':
		$execution_mode = EXEC_MODE_HIGH;
		break;
	default:
		$execution_mode = EXEC_MODE_UNKNOWN;
		break;
}

// トレンドタグを取得する
// トレンドタグを取得する API URL はこちら /api/v1/trend_tags
// $trend_tags_result には連想配列で取得する（json_decode の第二引数に true を指定している）
$trend_tags_result = json_decode(file_get_contents("https://imastodon.blue/api/v1/trend_tags"), TRUE);

// トレンドタグ更新日時を DateTime オブジェクトにする
$trend_tags_updated_at = new DateTime($trend_tags_result['updated_at'], new DateTimeZone('UTC'));

// 前回報告したトレンドタグの更新タイミングより新しいかチェック
$lasttime_log = @file_get_contents("lasttime.txt");
if ( $lasttime_log === FALSE ) {
	// 前回報告したトレンドタグの更新タイミングが見当たらない場合は強制 0
	$lasttime_log = 0;
}
if ( !isset($options['d']) && ($lasttime_log >= $trend_tags_updated_at->getTimestamp()) ) {
	// 新しいトレンドタグ更新がないので処理終了
	fprintf(STDERR, "トレンドタグの更新がありません。処理を終了します。");
	exit(0);
}

// トレンドタグの時間を JST に変換
$trend_tags_updated_at->setTimezone(new DateTimeZone("Asia/Tokyo"));

// 前回報告したトレンドタグの一覧を取得する
$lasttags_log = @file("lasttags.txt", FILE_SKIP_EMPTY_LINES);
if ( $lasttags_log === FALSE ) {
	// 前回報告したトレンドタグの一覧が見当たらない場合は何も報告していないとして処理
	$lasttags_log = array();
}
$lasttags_array_score = array();
foreach ( $lasttags_log as $lasttags_log_oneline ) {
	list($last_tag_text, $last_tag_score, $last_tag_rank) = explode(",", rtrim($lasttags_log_oneline));
	$lasttags_array_score[$last_tag_text] = $last_tag_score;

	// ログバージョンが古い場合、last_tag_rank がへんな値になってる可能性があるので、ガード
	if ( !isset($last_tag_rank) ) {
		$last_tag_rank = 0;
	}
	else {
		$last_tag_rank = intval($last_tag_rank);
		if ( $last_tag_rank <= 0 ) {
			$last_tag_rank = 0;
		}
	}
	$lasttags_array_rank[$last_tag_text] = $last_tag_rank;
}

// トレンドタグ報告文を作成する
$report_tags = array();			// トゥート文を保存する配列
$save_tags = array();			// 内部データとして保存する配列
$current_rank = 1;				// ランク情報計算用
$rank_diff = FALSE;				// 順位変化ありフラグ
foreach ( $trend_tags_result['score'] as $current_tag_text => $current_tag_score ) {
	if ( count($report_tags) < REPORT_TAGS_NUM ) {
		$report_tag = '#️⃣ '.$current_tag_text.' ['.$current_tag_score.']';
		// トレンドタグが前回報告した一覧にも存在するかチェック
		if ( array_key_exists($current_tag_text, $lasttags_array_score) === TRUE ) {
			// 存在する場合はこちら
			// スコアが上昇したか下降したかチェック
			// スコアは 0.01 以上の変化が見られた場合にだけ上昇/下降した、と判断する
			// スコアは float で得られるので、「一致」した場合を == みたいなやつで判断できないので。
			$score_diff = $current_tag_score - $lasttags_array_score[$current_tag_text];
			if ( $score_diff > 0.01 ) {
				$score_movement = '↗️ '.$score_diff;
			}
			else if ( $score_diff < -0.01 ) {
				$score_movement = '↘️ '.$score_diff;
			}
			else {
				$score_movement = '➡️ '.$score_diff;
			}

			// 前回一覧に存在するなら、ランクの変化をチェック
			if ( $lasttags_array_rank[$current_tag_text] != $current_rank ) {
				$rank_diff = TRUE;
			}
		}
		else {
			// 存在しない場合はこちら
			$score_movement = 'NEW';
			// 新しいタグがトレンドに新規掲載になったので、トゥート必要
			$rank_diff = TRUE;
		}
		$report_tag .= '['.$score_movement.']';
		$report_tags[] = $report_tag;
	}

	// 保存用のデータを作成する
	$save_tags[] = $current_tag_text.','.$current_tag_score.','.$current_rank;
	$current_rank++;
}

// 作成したトレンドタグ報告文に、1 つ以上のハッシュタグが含まれる場合、かつ
// 順位変化がある場合、トゥートする
if ( (count($report_tags) > 0) && ($rank_diff === TRUE) ) {
	// トレンドタグの更新時刻を作成
	$updated_at_text = $trend_tags_updated_at->format("H:i")." 現在のトレンドタグ".PHP_EOL.PHP_EOL;
	$toot_text = $updated_at_text.implode("\n", $report_tags);

	if ( isset($options['n']) ) {
		echo $toot_text.PHP_EOL;
	}
	else {
		require_once("MastodonClient/MastodonClient.php");
		$mc = new MastodonClient();
		$mc->init();
		$mc->post_statuses(MastodonClient::VISIBILITY_UNLISTED, $toot_text);
	}
}

// ここまで処理成功したら、前回のタグ一覧とかを保存する
$trend_tags_updated_at->setTimezone(new DateTimeZone("UTC"));
file_put_contents("lasttags.txt", implode("\n", $save_tags));
file_put_contents("lasttime.txt", $trend_tags_updated_at->getTimestamp());
