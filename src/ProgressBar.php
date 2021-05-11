<?php
namespace PL;

/**
 * PHP 控制台彩色文字和进度条绘制
 */
class ProgressBar {

	// 文字颜色
	// 黑
	const BLACK = 30;
	// 红
	const RED = 31;
	// 绿
	const GREEN = 32;
	// 黄
	const YELLOW = 33;
	// 蓝
	const BLUE = 34;
	// 紫
	const PURPLE = 35;
	// 深绿
	const DARKGREEN = 36;
	// 白
	const WHITE = 37;

	// 背景色
	// 黑
	const BG_BLACK = 40;
	// 红
	const BG_RED = 41;
	// 绿
	const BG_GREEN = 42;
	// 黄
	const BG_YELLOW = 43;
	// 蓝
	const BG_BLUE = 44;
	// 紫
	const BG_PURPLE = 45;
	// 深绿
	const BG_DARKGREEN = 46;
	// 白
	const BG_WHITE = 47;

	// 样式
	// 高亮（加粗）
	const _LIGHT = 1;
	// 弱化
	const _DIM = 2;
	// 斜体
	const _ITALIC = 3;
	//下划线
	const _UNDERLINE = 4;
	// 闪烁
	const _TWINKLE = 5;
	// 快速闪烁
	const _TWINKLE_QUICK = 6;
	// 反转
	const _INVERSE = 7;
	// 隐藏
	const _HIDE = 8;
	// 删除线
	const _STRIKETHROUGH = 9;

	// 指令
	const _CLEAR = '2J';

	/**
	 * 带有颜色样式的文字
	 * @author   idiotbaka
	 * @dateTime 2020-11-30
	 * @param    string           $text       字符串
	 * @param    integer|array    $style      样式
	 * @param    boolean          $line_break 是否换行
	 * @return   string                       结果字符串
	 */
	public static function text($text, $style = [], $line_break = false) {
		if(is_array($style)) {
			$style = implode(';', $style);
		}
		$style .= "m";
		$text = "\033[".$style.$text."\033[0m";
		if($line_break) {
			$text .= PHP_EOL;
		}
		return $text;
	}

	/**
	 * 清空屏幕
	 * @author   idiotbaka
	 * @dateTime 2020-11-30
	 * @return   string                 结果字符串
	 */
	public static function clear() {
		return "\033[".kalor::_CLEAR."m".$text."\033[0m";
	}

	/**
	 * 百分比进度条
	 * @author   idiotbaka
	 * @dateTime 2020-11-30
	 * @param    string|array     $title      标题 或 标题和描述
	 * @param    float            $progress   百分进度（0~100，支持两位小数）
	 * @param    integer          $bar_length 进度条绘制长度
	 * @param    array            $bar_style  进度条样式
	 * @return   string                       结果字符串
	 */
	public static function progressBarPercent($title, $progress = 0, $bar_length = 50, $bar_style = []) {
		if($progress > 100) {
			$progress = 100;
		}
		$description = '';
		if(is_array($title)) {
			$description = $title[1];
			$title = $title[0];
		}
		if(!$bar_style) {
			$bar_style = ['█', '▂'];
		}
		$progress_position = (int)($bar_length * $progress/100);
		$progress_before = str_repeat($bar_style[0], $progress_position);
		$progress_after = str_repeat($bar_style[1], $bar_length - $progress_position);
		$text = "\033[9999D".$title." ".$progress_before.$progress_after." [".sprintf('%.2f', $progress)."%] ".$description."\033[0m";
		if($progress == 100) {
			$text .= PHP_EOL;
		}
		return $text;
	}

	/**
	 * 步骤进度条
	 * @author   idiotbaka
	 * @dateTime 2020-11-30
	 * @param    string|array     $title        标题 或 标题和描述
	 * @param    array            $progress     当前步骤 和 总计步骤
	 * @param    integer          $bar_length   进度条绘制长度
	 * @param    array            $bar_style    进度条样式
	 * @return   string                         结果字符串
	 */
	public static function progressBarStep($title, $progress = [], $bar_length = 50, $bar_style = []) {
		$description = '';
		if(is_array($title)) {
			$description = $title[1];
			$title = $title[0];
		}
		if(!$bar_style) {
			$bar_style = ['█', '▂'];
		}
		$progress_position = (int)($bar_length * $progress[0]/$progress[1]);
		$progress_before = str_repeat($bar_style[0], $progress_position);
		$progress_after = str_repeat($bar_style[1], $bar_length - $progress_position);
		$text = "\033[9999D".$title." ".$progress_before.$progress_after." [".$progress[0]."/".$progress[1]."] ".$description."\033[0m";
		if($progress[0] >= $progress[1]) {
			$text .= PHP_EOL;
		}
		return $text;
	}
}