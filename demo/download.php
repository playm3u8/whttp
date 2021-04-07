<?php
require_once '../autoload.php';
use PL\Whttp;

/*
	php download.php
 */

$urls = [

	'https://api.mvgao.com:999/music/files/b29afe332bada824/074e52f9b6ba1baaf97542f9dc7c314e/584457/001-刘嘉亮 - 爱我就别伤害我.mp3',
	'https://api.mvgao.com:999/music/files/4427a34e305fdc8e/d699779349f155d3929dc56e89c07ac9/39595154/002-南北组合(吉萍) - 明月夜.mp3',
	'https://api.mvgao.com:999/music/files/d816c1959bf99603/09cbdaf669de97295e652772d86f98fa/972658/003-动力火车 - 第一滴泪.mp3',
	'https://api.mvgao.com:999/music/files/33cfd2a51ba90e89/56332217f4f862b8a3bdd6b233581039/36451950/004-艾岩 - 美丽的遗憾.mp3',
	'https://api.mvgao.com:999/music/files/4ab09f6551c8f0e2/13f46083e098d57e50ce04300a3b8180/42921336/005-等什么君 - 难渡.mp3',
	'https://api.mvgao.com:999/music/files/181970f754f06ab0/605ee0833ffd1488ef87b5a71e2c9d79/39013065/006-张鑫雨 - 爱难求情难断.mp3',
	'https://api.mvgao.com:999/music/files/6dcd18b672b8f14e/19f31b2ceb41ff8eba8bab8d431cfc4f/39010476/007-金池 - 谁不是.mp3',
	'https://api.mvgao.com:999/music/files/5665b64eaf583a96/040f7966fdcf11b4eed35b67f159aaee/42989466/008-Bell玲惠 - 爱就一个字 (治愈版).mp3',
	'https://api.mvgao.com:999/music/files/ff3cd3018fbdea72/a96c2a30a3c203d9a31f1ea8df3d05db/38672208/009-张茜 - 用力活着.mp3',
	'https://api.mvgao.com:999/music/files/16c37845b2aaeb1c/0b78e8b3bcfb73e50df1c1e2879c8fe4/42860234/010-摩登兄弟刘宇宁 - 天问.mp3'
];


$result = Whttp::get($urls)->savepath("./mp3")->getDownload();

foreach ($result as $value) {
	p($value['download']);
}
