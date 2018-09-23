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
//
// - - - - - - - - - - - - - - - - - - - -

define("EXEC_MODE_DIFF", 1);
define("EXEC_MODE_HIGH", 2);
define("EXEC_MODE_UNKNOWN", 0);

$options = getopt("m:n");

// 必須オプションが指定されているか検査
if ( !isset($options['m']) ) {
	fprintf(STDERR, "m オプションが指定されています。処理を終了します。");
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

