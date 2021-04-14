<?
	
	require_once(__DIR__.'/../app/apiKeys.php');

	// <router>
		if(stripos($_GET['url'],'sp500')) {
			header("HTTP/1.1 301 Moved Permanently");
			header("Location:".str_replace('sp500','spx',$_SERVER['REQUEST_URI']));
			exit;
		}
		if($_SERVER['HTTP_HOST']=='m1chart.com') {
			header("HTTP/1.1 301 Moved Permanently");
			header("Location:https://inflationchart.com".$_SERVER['REQUEST_URI']);
			exit;
		}
		if($_GET['m']) {
			$_GET['adjuster']=$_GET['m'];
		}
		if($_GET['url']) {
			$query=explode('-',str_replace('/','',$_GET['url']));
			if($query[0]) {
				$_GET['stock']=$query[0];
			}
			if($query[2]) {
				$_GET['adjuster']=$query[2];
			}
		}
		if($_GET['url']=='bitcoin-price-index') {
			$_GET['adjuster']='btc';
			$_GET['stock']='cpi';
			$_GET['logarithmic']=1;
			$_GET['show_adjuster']=1;
		}
		if(empty($_GET)) {
			$_GET['adjuster']='m1';
			$_GET['stock']='spx';
		}
	// </router>

	// <init db>
		$dbFile=__DIR__.'/../data/inflationchart.db';
		$dir = 'sqlite:/'.$dbFile;
		$db	= new PDO($dir) or exit(68); /* db erorr */
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	// </init db>

	// <add reminder for stale data>
		$query=$db->prepare("SELECT epoch FROM inflationchart WHERE epoch IS NOT NULL AND epoch IS NOT '' ORDER BY epoch DESC");
		$query->execute();
		$newestEpoch=$query->fetchAll(PDO::FETCH_ASSOC)[0]['epoch'];
		if($newestEpoch<strtotime("-45 days")) { /* alert after 1.5mo of old data */
			sendToAdminTelegram("📈 InflationChart.com: source data is ".timeAgoLong($newestEpoch)." old, time to update maybe? Thanks!");
		}
	// </add reminder for stale data>



// 20210313222719
// https://inflationchart.test/spx-in-m1

// {
//   "url": "spx-in-m1",
//   "stock": "spx",
//   "adjuster": "m1"
// }




	// <config>
		$time_selected_default='all';

		$stocks=array(
			'spx'=>'🇺🇸 S&P500',
			'dji'=>'🇺🇸 DJI',
			'nasdaq'=>'🤖 NASDAQ',
			'gdp'=>'💰 US GDP',
			'income'=>'💰 US Income',
			'minwage'=>'💰 US Min Wage',
			'home'=>'🏡 US Home',
			'rent'=>'🏠 US Rent',
			'cpi'=>'🛒 CPI',
			'oil'=>'🛢 Oil',
			'gold'=>'🏆 Gold',
			'silver'=>'🥈 Silver',
			'coffee'=>'☕️ Coffee',
			'asia'=>'🌏 Asia ex-JP',
			'china'=>'🇨🇳 China SSE',
			'food'=>'🥩 Food Price Index',
			'bigmac'=>'🍔 Big Mac Index',
			'btc'=>'🥇 BTC',
			'eth'=>'🏅 ETH',
			'tsla'=>'🚗 $TSLA'
		);

		$suffix=array(
			'us10y'=>'%',
			'rent'=>'$/mo',
			'coffee'=>'$/lbs',
		);

		$adjusters=array(
			'mb'=>'💸 M0: Cash',
			'm1'=>'💳 M1: Cash + Bank',
			'm3'=>'💰 M3: All Money',
			'us10y'=>'💲 10Y Treasury',
			'cpi'=>'🛒 CPI',
			'spx'=>'🇺🇸 S&P500',
			// 'levels'=>'🐩 Levels Inflation Index',
			'oil'=>'🛢 Oil',
			'gold'=>'🏆 Gold',
			'silver'=>'🥈 Silver',
			'coffee'=>'☕️ Coffee',
			'home'=>'🏡 US Home',
			'rent'=>'🏠 US Rent',
			'food'=>'🥩 Food Price Index',
			'bigmac'=>'🍔 Big Mac Index',
			'btc'=>'🥇 BTC',
			'eth'=>'🏅 ETH',
			'income'=>'💰 US Income',
			'minwage'=>'💰 US Min Wage',
		);

		$info=array(
			'coffee'=>'Coffee price in USD per pound. Source: https://macrotrends.net',
		);

		
		$stock_selected=$_GET['stock'];
		$adjuster_selected=$_GET['adjuster'];

		if(
			empty($_GET['stock']) || 
			!$stocks[$_GET['stock']]
		) {
			$stock_selected='spx';
		}
		if(
			empty($_GET['adjuster']) || 
			!$adjusters[$_GET['adjuster']]
		) {
			$adjuster_selected='m1';
		}
	// </config>

	// <get data>
		if($_GET['url']=='sitemap.xml') {
			$query=$db->prepare("SELECT * FROM inflationchart ORDER BY epoch ASC");
			$query->execute();
			$data=$query->fetchAll(PDO::FETCH_ASSOC);
		}
		else {
			/* make sure you check if $adjuster_selected and $stock selected are safe from $_GET[] user input above */
			$query=$db->prepare("SELECT epoch,".$adjuster_selected.",".$stock_selected." FROM inflationchart WHERE epoch>:epoch ORDER BY epoch ASC");
			if($_GET['time']=='all') {
				$query->bindValue(':epoch',strtotime("1900-01-01"));
			}
			else if($_GET['time']!=$time_selected_default && !empty($_GET['time'])) {
				$query->bindValue(':epoch',strtotime('-'.$_GET['time']));
			}
			else {
				// default
				if($time_selected_default=='all') {
					$query->bindValue(':epoch',strtotime("1900-01-01"));
				}
				else {
					$query->bindValue(':epoch',strtotime("-".$time_selected_default));
				}
			}
			$query->execute();
			$data=$query->fetchAll(PDO::FETCH_ASSOC);
		}
	// </get data>


	// <data start/end times>
		$startTimeWithCompleteData=strtotime("2100-01-01");
		$endTimeWithCompleteData=strtotime("1900-01-01");
		foreach($data as $row) {
			if(
				!empty($row[$stock_selected]) && 
				!empty($row[$adjuster_selected]) && 
				$row['epoch']<$startTimeWithCompleteData
			) {
				$startTimeWithCompleteData=$row['epoch'];
			}

			if(
				!empty($row[$stock_selected]) && 
				!empty($row[$adjuster_selected]) && 
				$row['epoch']>$endTimeWithCompleteData
			) {
				$endTimeWithCompleteData=$row['epoch'];
			}
		}


		// <remove data outside bounds>
			$newData=array();
			foreach($data as $row) {
				if($row['epoch']>$endTimeWithCompleteData) continue;
				if($row['epoch']<$startTimeWithCompleteData) continue;
				array_push($newData,$row);
			}
			$data=$newData;
		// </remove data outside bounds>


		// <get first for each data set but us startTime>
			$first=array();
			$query=$db->prepare("SELECT * FROM inflationchart WHERE epoch>=:epoch AND ".$adjuster_selected." IS NOT NULL AND ".$adjuster_selected." IS NOT '' ORDER BY epoch ASC LIMIT 1");
			$query->bindValue(':epoch',$newestStartTime);
			$query->execute();
			$first[$adjuster_selected]=$query->fetchAll(PDO::FETCH_ASSOC)[0][$adjuster_selected];

			$query=$db->prepare("SELECT * FROM inflationchart WHERE epoch>=:epoch AND ".$stock_selected." IS NOT NULL AND ".$stock_selected." IS NOT '' ORDER BY epoch ASC LIMIT 1");
			$query->bindValue(':epoch',$newestStartTime);
			$query->execute();
			$first[$stock_selected]=$query->fetchAll(PDO::FETCH_ASSOC)[0][$stock_selected];
		// </get first for each data set>

	// </data start/end times>


	// <set show/hide stock/adjusted from URL>
		if(isset($_GET['show_stock'])){
			if($_GET['show_stock']==1) {
				$show_stock=1;
			}
			else {
				$show_stock=0;
			}
		}
		else {
			// default if not set to show
			$show_stock=1;
		}

		if(isset($_GET['show_divided_by'])){
			if($_GET['show_divided_by']==1) {
				$show_divided_by=1;
			}
			else {
				$show_divided_by=0;
			}
		}
		else {
			// default if not set to show
			$show_divided_by=1;
		}


		if(isset($_GET['show_adjusted'])){
			if($_GET['show_adjusted']==1) {
				$show_adjusted=1;
			}
			else {
				$show_adjusted=0;
			}
		}
		else {
			// default if not set to not show
			$show_adjusted=0;
		}

		if(isset($_GET['show_adjuster'])){
			if($_GET['show_adjuster']==1) {
				$show_adjuster=1;
			}
			else {
				$show_adjuster=0;
			}
		}
		else {
			// default if not set to show
			$show_adjuster=0;
		}


		if(isset($_GET['logarithmic'])){
			if($_GET['logarithmic']==1) {
				$logarithmic=1;
			}
			else {
				$logarithmic=0;
			}
		}
		else {
			// default if not set to not logarithmic, e.g. linear
			$logarithmic=0;
		}
	// </set show/hide stock/adjusted from URL>


	// <sitemap>
		if($_GET['url']=='sitemap.xml') {
			echo '<?xml version="1.0" encoding="UTF-8"?>';
			foreach($adjusters as $adjuster => $label) {
				foreach($stocks as $stock => $label) {
					?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://inflationchart.com/<?=$stock?>-in-<?=$adjuster?></loc><changefreq>monthly</changefreq><priority>1</priority><lastmod><?=date('c',filemtime(__DIR__.'/../data/inflationchart.db'));?></lastmod></url></urlset><?
				}
			}	
			exit;
		}
	// </sitemap>

	// <screenshot with API Flash>
		if($_GET['action']=='screenshot') {
			$screenshotUrl='https://'.$_SERVER['SERVER_NAME'].$_GET['uri'].'&layout=screenshot&cache='.date('Y-m');
			$url='https://api.apiflash.com/v1/urltoimage?quality=80&width=1250&height=667&format=jpeg&access_key='.$config['apiFlash']['key'].'&url='.urlencode($screenshotUrl);
			$response=curl_get_contents($url);
			if(!$response) {
				echo "Can't connect to API Flash";
			}
			else {
				if(!empty(json_decode($response,true))) {
					echo $screenshotUrl;
					echo "\n\n";
					// json response
					echo $response;
					exit;
				}
				header("Content-type: image/jpeg");
				echo $response;
			}
			exit;
		}
	// </screenshot with API Flash>





	$newData=array();
	foreach($data as $row) {

		$row[$adjuster_selected.'_adj_'.$stock_selected]=$row[$stock_selected]/$row[$adjuster_selected]*$first[$adjuster_selected];
		if(
			empty($row[$adjuster_selected.'_adj_'.$stock_selected]) || 
			is_nan($row[$adjuster_selected.'_adj_'.$stock_selected]) || 
			is_infinite($row[$adjuster_selected.'_adj_'.$stock_selected]) ||
			!is_numeric($row[$adjuster_selected.'_adj_'.$stock_selected])
		) {
			unset($row[$adjuster_selected.'_adj_'.$stock_selected]);
		}

		$row[$stock_selected.'_divided_by_'.$adjuster_selected]=$row[$stock_selected]/$row[$adjuster_selected];
		if(
			empty($row[$stock_selected.'_divided_by_'.$adjuster_selected]) || 
			is_nan($row[$stock_selected.'_divided_by_'.$adjuster_selected]) || 
			is_infinite($row[$stock_selected.'_divided_by_'.$adjuster_selected]) ||
			!is_numeric($row[$stock_selected.'_divided_by_'.$adjuster_selected])
		) {
			unset($row[$stock_selected.'_divided_by_'.$adjuster_selected]);
		}
		array_push($newData,$row);
	}
	$data=$newData;

	// <make strings numbers>
		$newData=array();
		foreach($data as $row) {
			$newRow=array();
			foreach($row as $key => $value) {
				$newValue=(float) $value;
				$newRow[$key]=$newValue;
			}
			array_push($newData,$newRow);
		}
		$data=$newData;
	// </make strings numbers>


	$page['title']='💰'."Inflation Chart: The stock market adjusted for the US-dollar money supply M1 (and more) (by @levelsio)";
	$page['description']="This chart shows the price of stock markets adjusted for inflation of the US dollar money supply in M1, M2 and the money base (MB).".'. Money printer goes brrrrrrrrr.';

	// ob_start("sanitizeOutput");

	if($_GET['adjuster'] || $_GET['stock']) {
		$page['title']='💰'.$stocks[$_GET['stock']].' Price in '.$adjusters[$_GET['adjuster']];
		$page['description']="The price of ".$stocks[$_GET['stock']]." measured in the price of ".$adjusters[$_GET['adjuster']].', to adjust it for inflation. Money printer goes brrrrrrrrr.';
	}

	if($_GET['adjuster']=='btc' && $_GET['stock']=='cpi') {
		$page['title']='Bitcoin Price Index';
	}

?><!doctype html>
<html class="<?=$_GET['layout']?>">
<!--

	(m) MIT License

	Please steal my code but credit me, @levelsio with a link to https://twitter.com/levelsio if you use this to make something!

	Made with vanilla HTML, vanilla CSS, vanilla JS with jQuery and vanilla PHP.

	🌙 Ad lunam!

	Code: https://github.com/levelsio/inflationchart

-->
<meta charset="UTF-8" />
<script src="https://inflationchart.com/assets/jquery.js?<?=filemtime(__DIR__.'/../assets/jquery.js');?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://inflationchart.com/assets/chartjs.js?<?=filemtime(__DIR__.'/../assets/chartjs.js');?>"></script>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@levelsio">
<meta name="twitter:creator" content="@levelsio">
<meta name="twitter:title" content="<?=$page['title']?>">
<meta name="twitter:description" content="<?=$page['description']?>" />
<meta name="twitter:image:src" content="https://inflationchart.com/?action=screenshot&uri=<?=urlencode($_SERVER['REQUEST_URI'])?>">
<meta property="og:type" content="website"/>
<meta property="og:title" content="<?=$page['title']?>"/>
<meta property="og:image" content="https://inflationchart.com/?action=screenshot&uri=<?=urlencode($_SERVER['REQUEST_URI'])?>"/>
<meta property="og:description" content="<?=$page['description']?>" />
<meta property="og:url" content="https://inflationchart.com<?=$_SERVER['REQUEST_URI']?>">
<meta name="twitter:url" content="https://inflationchart.com<?=$_SERVER['REQUEST_URI']?>">
<link rel="icon" href="/assets/favicon.png"/>
<link rel="shortcut icon" href="/assets/favicon.png"/>
<link rel="preload" href="/assets/DMSans-Regular.ttf" as="font" type="font/ttf" crossorigin>
<link rel="preload" href="/assets/DMSans-Bold.ttf" as="font" type="font/ttf" crossorigin>
<link rel="preload" href="/assets/iosevka-custom-extended.woff2" as="font" type="font/woff2" crossorigin>
<!-- <link rel="preload" href="/assets/iosevka-custom-extendedbold.woff2" as="font" type="font/woff2" crossorigin> -->
<script async defer src="https://scripts.simpleanalyticscdn.com/latest.js"></script>
<noscript><img src="https://queue.simpleanalyticscdn.com/noscript.gif" alt=""/></noscript>
<title>
	<?=$page['title']?>
</title>
<style>
	@font-face{
		font-family:"DM Sans";
		font-style:normal;
		font-display:swap;
		font-weight:400;
		src:url("/assets/DMSans-Regular.ttf") format("truetype");
	}

	@font-face {
		font-family:"DM Sans";
		font-style:normal;
		font-display:swap;
		font-weight:700;
		src:url("/assets/DMSans-Bold.ttf") format("truetype");
	}

	@font-face {
		font-family:"Iosevka Custom";
		font-display:swap;
		font-weight:400;
		font-stretch:expanded;
		font-style:normal;
		src:url("/assets/iosevka-custom/woff2/iosevka-custom-extended.woff2") format("woff2"),url("/assets/iosevka-custom/woff/iosevka-custom-extended.woff") format("woff"),url("/assets/iosevka-custom/ttf/iosevka-custom-extended.ttf") format("truetype");
	}

	@font-face {
		font-family:"Iosevka Custom";
		font-display:swap;
		font-weight:700;
		font-stretch:expanded;
		font-style:normal;
		src:url("/assets/iosevka-custom/woff2/iosevka-custom-extendedbold.woff2") format("woff2"),url("/assets/iosevka-custom/woff/iosevka-custom-extendedbold.woff") format("woff"),url("/assets/iosevka-custom/ttf/iosevka-custom-extendedbold.ttf")
	}
	.side span.quote,
	.side a {
		/*font-family:"Iosevka Custom",monospace,sans-serif;*/
	}
	.side .fed {
		width:100%;
		max-width:200px;
		margin:0 auto;
		display:block;
	}
	.side .fed-wrapper {
		border-top:1px solid #2a2a2a;
		border-bottom:1px solid #2a2a2a;
	}
	@media (max-width:800px) {
		.side .fed-wrapper {
			border:none;
			border:1px solid #2a2a2a;
			display:table;
			margin:0 auto;
			border-radius:0;
			overflow:hidden;
		}
	}
	.side p {
		line-height:1.75;
		font-size:14px;
		font-weight:100;
		font-family:"DM Sans",sans-serif;
		color:rgb(186,186,186);
	}
	.side p {
		padding:14px;
		padding-top:0;
	}
	body {
		margin:0;
		padding:0;
		font-family:"Iosevka Custom",monospace,sans-serif;
		text-align:left;
		font-size:14px;
		background:#000;
		color:rgb(211,211,211);
		background:linear-gradient(180deg,#0a1325 0,#000000);
	}
	h1.selectors {
		width:calc(100% - 75px);
		margin:0 auto;
		margin-top:28px;
		display:block;
		margin-left:75px;
		margin-right:75px;
	}
	h1.selectors,
	h1 select {
		font-family:"Iosevka Custom",monospace,sans-serif;
		font-size:26px;
		color:rgb(211,211,211);
		color:#ff4742;
		z-index:1;
		position:relative;
	}
	select {
		outline:none;
		-webkit-appearance:none;
		background:none;
		padding:7px;
		padding-left:0;
		padding-right:0;
		/*border-radius:7px;*/
		color:rgb(186,186,186);
		border:1px solid rgb(186,186,186);
		border-color:#ff4742;
		color:#ff4742;
		<?if($_GET['layout']=='screenshot'){?>
			border-width:4px;
			font-weight:800;
		<?}?>
		cursor:pointer;
		margin:0;
	}
	select:hover {
		opacity:0.75;
	}
	select.time_selector {
		color:#fff;
		border-color:#fff;

		border-left:none;
		border-right:none;
		border-top:none;
	}
	select.time_selector:hover {
		background:#ff4742;
		color:#060b16;
		background:#42a5ff;
	}
	select.adjuster_selector {
		color:#ff4742;
		border-color:#ff4742;
		color:#42a5ff;
		border-color:#42a5ff;

		border-left:none;
		border-right:none;
		border-top:none;
	}
	select.adjuster_selector:hover {
		background:#ff4742;
		color:#060b16;
		background:#42a5ff;
	}
	select.stock_selector {
		border-left:none;
		border-right:none;
		border-top:none;
		color:rgb(43,222,115);
		border-color:rgb(43,222,115);
	}
	select.stock_selector:hover {
		background:rgb(43,222,115);
		color:#060b16;
	}
	select.time_selector:hover {
		background:#fff;
		color:#060b16;
	}
	.time_selector_wrapper,
	.adjuster_selector_wrapper,
	.stock_selector_wrapper {
		display:inline-block;
		margin:7px;
	}
	.heading_above {
		text-transform:uppercase;
		color:#fff;
		font-size:12px;
		font-weight:normal;
		text-align:left;
		color:rgb(211,211,211);
	}
	a {
		color:rgb(211,211,211);
		color:#ff4742;
	}

	.side {
		width:200px;
		border-left:1px solid #2a2a2a;
		height:100%;
		background:#000;
		overflow-y:scroll;
		overflow-x:hidden;
		z-index:1;
		position:fixed;
		right:0;
		display:none;
	}
	.main {
		/*width:calc(100% - 200px - 28px);*/
		height:100vh;
		/*margin-right:calc(200px + 28px);*/
		overflow-y:hidden;
		background:none;
	}

	.main .chart {
		margin:28px;
		margin-top:14px;
		height:calc(100vh - 250px);
	}

	.aux-text,
	.logo,
	.on-github,
	.by-levelsio {
		border:1px solid #2a2a2a;
		padding:7px;
		font-size:12px;
		/*border-radius:7px;*/
		background:#0a1325;
		z-index:1;
	}

	/* aux means ad */
	.aux-text {
		display:table;
		font-size:14px;
		padding:0;
		padding-left:14px;
		padding-right:14px;
		text-decoration:none;
		color:#fff;
		margin:14px auto;
		text-align:center;
		box-shadow:1px 2px 4px rgba(0,0,0,1);
	}
	.aux-text .button {
		display:inline-block;
		border:2px solid #ff4742;
		padding:7px;
		color:#ff4742;
		margin:-1px;
		margin-right:-16px;
		background:rgba(255,71,66,0.125);
	}

	@media (max-width:1200px) {
		.aux-text {
			line-height:1.5;
			font-size:12px;
			padding:14px;
		}
		.aux-text .button {
			display:table;
			margin:0 auto;
			margin-top:7px;
			padding:3.5px;
		}
	}
	@media (max-width:600px) {
		.aux-text {
			border-right:none;
			border-left:none;
		}
	}
	.logo {
		position:fixed;
		top:14px;
		left:14px;
		color:rgb(211,211,211);
		text-decoration:none;
		z-index:0;
	}

	.by-levelsio {
		position:fixed;
		text-decoration:none;
		color:rgb(211,211,211);
		bottom:14px;
		left:14px;
	}

	.on-github {
		position:fixed;
		text-decoration:none;
		color:rgb(211,211,211);
		bottom:14px;
		right:14px;
	}

	.legend {
		color:rgb(211,211,211);
		left:100px;
		top:155px;
		/*background:linear-gradient(180deg,#0a1325 0,#0a1325);*/
		/*background:#0a1325;*/
		background:rgb(10 19 37 / 90%);
		position:fixed;
		border:1px solid #2a2a2a;
		z-index:1;
		/*width:calc(100% - 200px - 1px - 7px - 7px);*/
		width:250px;
		padding:7px;
		padding-top:4px;
		padding-bottom:10px;
		position:fixed;
		/*background:#000;*/
		text-align:center;
		box-shadow:1px 2px 4px rgba(0,0,0,1);
		/*border-top:1px solid #2a2a2a;*/
		-webkit-touch-callout: none;
		-webkit-user-select: none;
		-khtml-user-select: none;
		-moz-user-select: none;
		-ms-user-select: none;
		user-select: none;
		text-align:left;
		font-size:16px;
	}
	.legend .show_stock_legend:hover,
	.legend .show_divided_by_legend:hover,
	.legend .show_adjusted_legend:hover,
	.legend .show_adjuster_legend:hover,
	.legend .logarithmic_legend:hover {
		opacity:0.75 !important;
	}
	.legend .show_stock_legend {
		color:#42a5ff;
	}
	.legend .show_divided_by_legend {
		color:#ff4742;
	}
	.legend .show_adjusted_legend {
		color:#ffc924;
	}
	.legend span {
		cursor:pointer;
	}
	.legend input[type="checkbox"] {
		position:relative;
		top:2px;
		cursor:pointer;
	}


	a:hover {
		opacity:0.75;
	}
	a:active {
		opacity:0.5;
	}

	.tweet-wrapper {
		padding:14px;
		padding-top:0;
		padding-bottom:0;
	}
	.youtube-wrapper {
		pointer-events:none;
		max-width:100%;
		padding:0px;
		border-bottom:1px solid #2a2a2a;
		padding-top:0;
		padding-bottom:0;
		margin-top:-14px;
	}

	.side span.quote {
		/*color:#fff;*/
		/*line-height:1.25;*/
		/*display:inline;*/
	}

	@media (max-width:1200px) {
		.on-github {
			display:none;
		}
		.by-levelsio {
			position:relative;
			margin:0 auto;
			left:auto;
			bottom:auto;
			display:table;
			background:none;
			margin-bottom:-14px;
			border:none;
			font-size:12px;
			color:rgb(186,186,186);
			display:none;
		}
		.main .chart {
			margin:7px;
		}
	}
	@media (max-width:1000px) {
		h1.selectors,
		h1.selectors select {
			font-size:20px;
		}
	}
	.logo {
		display:none;
	}

	html.screenshot .by-levelsio,
	html.screenshot .legend,
	html.screenshot .side {
		display:none;
	}
	html.screenshot h1.selectors,
	html.screenshot h1.selectors select {
		font-size:35px;
	}
	html.screenshot h1.selectors {
		margin:28px;
	}
	html.screenshot .main .chart {
		height:calc(100vh - 150px);
	}
	html.screenshot .main {
		width:100%;
		height:100vh;
		position:relative;
	}
	@media (max-width:800px) {
		.youtube-wrapper {
			display:none;
		}
		.legend {
			position:relative;
		}
		.main {
			width:100%;
			height:auto;
			position:relative;
		}
		.side {
			width:100%;
			border:none;
			background:none;
			overflow:none;
			height:auto;
			padding-bottom:28px;
			position:relative;
		}
		h1.selectors {
			width:auto;
			margin:0;
		}
		.legend {
			position:relative;
			left:auto;
			right:auto;
			top:auto;
			bottom:auto;
			width:auto;
			background:none;
			border:none;
			text-align:center;
			padding:14px;
		}
		
		h1.selectors,
		h1.selectors select {
			font-size:16px;
		}
		select {
			margin-top:0;
			margin-left:0;
			margin-right:0;
		}
		body {
			padding:0;
			padding-top:14px;
			text-align:center;
		}
		.hide_on_mobile {
			display:none;
		}
		h1.selectors .slash {
			display:none;
		}
		h1.selectors select {
			border:1px solid;
			padding:7px;
			margin:7px;
		}
		.heading_above {
			text-align:center;
		}
		.mobile_line_break {
			display:block;
		}
		.main .chart,
		.main #chart {
			height:50vh;
		}
	}
</style>

<div class="main">
	<center>
		<h1 class="selectors">
			<div class="stock_selector_wrapper">
				<div class="heading_above">
					The price of
				</div>
				<select class="stock_selector">
					<?foreach($stocks as $stock => $label) {
						?><option data-suffix="<?=$suffix[$stock]?>" data-short="<?
							list($short,$rest)=explode(': ',$label);
							echo $short;
						?>" value="<?=$stock?>" <?if($_GET['stock']==$stock){?>selected<?}?>>
							<?=$label?>
						</option>
					<?}?>
				</select>
			</div>
		<span><span class="mobile_line_break"></span><?/*<span class="slash"> / </span>*/?><span class="mobile_line_break"></span></span>
			<div class="adjuster_selector_wrapper">
				<div class="heading_above">
					As measured in
				</div>
				<select class="adjuster_selector">
					<?foreach($adjusters as $adjuster => $label) {?>
						<option data-suffix="<?=$suffix[$adjuster]?>" data-short="<?
							list($short,$rest)=explode(': ',$label);
							echo $short;
						?>" value="<?=$adjuster?>" <?if($_GET['adjuster']==$adjuster){?>selected<?}?>>
							<?=$label?>
						</option>
					<?}?>
				</select>
			</div>

			<div class="time_selector_wrapper">
				<div class="heading_above">
					In last
				</div>
				<select class="time_selector">
					<option value="1 year" <?if(!$_GET['time'] && $time_selected_default=='1 year'){?>selected<?}?> <?if($_GET['time']=='1 year'){?>selected<?}?>>🗓 1 year</option>
					<option value="5 years" <?if(!$_GET['time'] && $time_selected_default=='5 years'){?>selected<?}?> <?if($_GET['time']=='5 years'){?>selected<?}?>>🗓 5 years</option>
					<option value="10 years" <?if(!$_GET['time'] && $time_selected_default=='10 years'){?>selected<?}?> <?if($_GET['time']=='10 years'){?>selected<?}?>>🗓 10 years</option>
					<option value="20 years" <?if(!$_GET['time'] && $time_selected_default=='20 years'){?>selected<?}?> <?if($_GET['time']=='20 years'){?>selected<?}?>>🗓 20 years</option>
					<option value="50 years" <?if(!$_GET['time'] && $time_selected_default=='50 years'){?>selected<?}?> <?if($_GET['time']=='50 years'){?>selected<?}?>>🗓 50 years</option>
					<option value="all" <?if(!$_GET['time'] && $time_selected_default=='all'){?>selected<?}?> <?if($_GET['time']=='all'){?>selected<?}?>>🗓 all time</option>
				</select>
			</div>
		</h1>
	</center>

	<a href="https://twitter.com/levelsio" class="by-levelsio">
		by @levelsio
	</a>
	<a href="https://github.com/levelsio/inflationchart" class="on-github">
		Star on GitHub
	</a>

	<a href="https://inflationchart.com" class="logo">
		Inflation Chart
	</a>
	
	<script>
		var chart;
		var adjuster_selected='';
		var adjuster_selected_label='';
		var adjuster_selected_suffix='';
		var stock_selected='';
		var stock_selected_label='';
		var stock_selected_suffix='';
		var show_stock=<?=$show_stock?>;
		var show_divided_by=<?=$show_divided_by?>;
		var show_adjusted=<?=$show_adjusted?>;
		var show_adjuster=<?=$show_adjuster?>;
		var logarithmic=<?=$logarithmic?>;

		$(window).bind('popstate',function() {
			/* reload page if user presses back or forward etc, so that the URL they go to is actually loaded */
			window.location.reload();
		});

		var animationDataBufferIterator=0;
		var animationInterval;
		var animationDataDividedByBuffer=0;
		var animationDataAdjusterBuffer=0;
		var animationDataStockBuffer=0;
		var animationDatasetIndex=0;
		var animationFindDatasetIndexIterator=0;
		var animationWhatAreWeAnimating='';



		function str_replace(search, replace, subject, count) {
			/*Copyright (c) 2007-2016 Kevin van Zonneveld (https://kvz.io) 
			and Contributors (https://locutus.io/authors)

			Permission is hereby granted, free of charge, to any person obtaining a copy of
			this software and associated documentation files (the "Software"), to deal in
			the Software without restriction, including without limitation the rights to
			use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
			of the Software, and to permit persons to whom the Software is furnished to do
			so, subject to the following conditions:

			The above copyright notice and this permission notice shall be included in all
			copies or substantial portions of the Software.

			THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
			IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
			FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
			AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
			LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
			OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
			SOFTWARE.*/
			var i = 0,
				j = 0,
				temp = '',
				repl = '',
				sl = 0,
				fl = 0,
				f = [].concat(search),
				r = [].concat(replace),
				s = subject,
				ra = Object.prototype.toString.call(r) === '[object Array]',
				sa = Object.prototype.toString.call(s) === '[object Array]';
			s = [].concat(s);
			if (count) {
				this.window[count] = 0;
			}

			for (i = 0, sl = s.length; i < sl; i++) {
				if (s[i] === '') {
					continue;
				}
				for (j = 0, fl = f.length; j < fl; j++) {
					temp = s[i] + '';
					repl = ra ? (r[j] !== undefined ? r[j] : '') : r[0];
					s[i] = (temp)
						.split(f[j])
						.join(repl);
					if (count && s[i] !== temp) {
						this.window[count] += (temp.length - s[i].length) / f[j].length;
					}
				}
			}
			return sa ? s : s[0];
		}
		function removeEmojis(str) {
			var ranges = [
				'\ud83c[\udf00-\udfff]',
				'\ud83d[\udc00-\ude4f]',
				'\ud83d[\ude80-\udeff]' 
			];
			str = str.replace(new RegExp(ranges.join('|'), 'g'), '');
			return str;
		}
		function number_format(number, decimals, dec_point, thousands_sep) {
			/*Copyright (c) 2007-2016 Kevin van Zonneveld (https://kvz.io) 
			and Contributors (https://locutus.io/authors)

			Permission is hereby granted, free of charge, to any person obtaining a copy of
			this software and associated documentation files (the "Software"), to deal in
			the Software without restriction, including without limitation the rights to
			use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
			of the Software, and to permit persons to whom the Software is furnished to do
			so, subject to the following conditions:

			The above copyright notice and this permission notice shall be included in all
			copies or substantial portions of the Software.

			THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
			IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
			FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
			AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
			LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
			OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
			SOFTWARE.*/

			number = (number + '')
				.replace(/[^0-9+\-Ee.]/g, '');
			var n = !isFinite(+number) ? 0 : +number,
				prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
				sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
				dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
				s = '',
				toFixedFix = function(n, prec) {
					var k = Math.pow(10, prec);
					return '' + (Math.round(n * k) / k)
						.toFixed(prec);
				};
			
			s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
				.split('.');
			if (s[0].length > 3) {
				s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
			}
			if ((s[1] || '')
				.length < prec) {
				s[1] = s[1] || '';
				s[1] += new Array(prec - s[1].length + 1)
					.join('0');
			}
			return s.join(dec);
		}

		function date(format, timestamp) {
			/*Copyright (c) 2007-2016 Kevin van Zonneveld (https://kvz.io) 
			and Contributors (https://locutus.io/authors)

			Permission is hereby granted, free of charge, to any person obtaining a copy of
			this software and associated documentation files (the "Software"), to deal in
			the Software without restriction, including without limitation the rights to
			use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
			of the Software, and to permit persons to whom the Software is furnished to do
			so, subject to the following conditions:

			The above copyright notice and this permission notice shall be included in all
			copies or substantial portions of the Software.

			THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
			IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
			FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
			AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
			LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
			OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
			SOFTWARE.*/
			
			var that = this;
			var jsdate, f;
			var txt_words = [
			'Sun', 'Mon', 'Tues', 'Wednes', 'Thurs', 'Fri', 'Satur',
			'January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December'
			];
			var formatChr = /\\?(.?)/gi;
			var formatChrCb = function(t, s) {
			return f[t] ? f[t]() : s;
			};
			var _pad = function(n, c) {
			n = String(n);
			while (n.length < c) {
				n = '0' + n;
			}
			return n;
			};
			f = {
			d: function() {
				return _pad(f.j(), 2);
			},
			D: function() {
				return f.l()
				.slice(0, 3);
			},
			j: function() {
				return jsdate.getDate();
			},
			l: function() {
				return txt_words[f.w()] + 'day';
			},
			N: function() {
				return f.w() || 7;
			},
			S: function() {
				var j = f.j();
				var i = j % 10;
				if (i <= 3 && parseInt((j % 100) / 10, 10) == 1) {
				i = 0;
				}
				return ['st', 'nd', 'rd'][i - 1] || 'th';
			},
			w: function() {
				return jsdate.getDay();
			},
			z: function() {
				var a = new Date(f.Y(), f.n() - 1, f.j());
				var b = new Date(f.Y(), 0, 1);
				return Math.round((a - b) / 864e5);
			},

			W: function() {
				var a = new Date(f.Y(), f.n() - 1, f.j() - f.N() + 3);
				var b = new Date(a.getFullYear(), 0, 4);
				return _pad(1 + Math.round((a - b) / 864e5 / 7), 2);
			},

			F: function() {
				return txt_words[6 + f.n()];
			},
			m: function() {
				return _pad(f.n(), 2);
			},
			M: function() {
				return f.F()
				.slice(0, 3);
			},
			n: function() {
				return jsdate.getMonth() + 1;
			},
			t: function() {
				return (new Date(f.Y(), f.n(), 0))
				.getDate();
			},

			L: function() {
				var j = f.Y();
				return j % 4 === 0 & j % 100 !== 0 | j % 400 === 0;
			},
			o: function() {
				var n = f.n();
				var W = f.W();
				var Y = f.Y();
				return Y + (n === 12 && W < 9 ? 1 : n === 1 && W > 9 ? -1 : 0);
			},
			Y: function() {
				return jsdate.getFullYear();
			},
			y: function() {
				return f.Y()
				.toString()
				.slice(-2);
			},

			a: function() {
				return jsdate.getHours() > 11 ? 'pm' : 'am';
			},
			A: function() {
				return f.a()
				.toUpperCase();
			},
			B: function() {
				var H = jsdate.getUTCHours() * 36e2;
				var i = jsdate.getUTCMinutes() * 60;
				var s = jsdate.getUTCSeconds();
				return _pad(Math.floor((H + i + s + 36e2) / 86.4) % 1e3, 3);
			},
			g: function() {
				return f.G() % 12 || 12;
			},
			G: function() {
				return jsdate.getHours();
			},
			h: function() {
				return _pad(f.g(), 2);
			},
			H: function() {
				return _pad(f.G(), 2);
			},
			i: function() {
				return _pad(jsdate.getMinutes(), 2);
			},
			s: function() {
				return _pad(jsdate.getSeconds(), 2);
			},
			u: function() {
				return _pad(jsdate.getMilliseconds() * 1000, 6);
			},

			e: function() {
				/*				return that.date_default_timezone_get();
				 */
				throw 'Not supported (see source code of date() for timezone on how to add support)';
			},
			I: function() {
				var a = new Date(f.Y(), 0);
				var c = Date.UTC(f.Y(), 0);
				var b = new Date(f.Y(), 6);
				var d = Date.UTC(f.Y(), 6);
				return ((a - c) !== (b - d)) ? 1 : 0;
			},
			O: function() {
				var tzo = jsdate.getTimezoneOffset();
				var a = Math.abs(tzo);
				return (tzo > 0 ? '-' : '+') + _pad(Math.floor(a / 60) * 100 + a % 60, 4);
			},
			P: function() {
				var O = f.O();
				return (O.substr(0, 3) + ':' + O.substr(3, 2));
			},
			T: function() {
				/*				var abbr, i, os, _default;
				if (!tal.length) {
				tal = that.timezone_abbreviations_list();
				}
				if (that.php_js && that.php_js.default_timezone) {
				_default = that.php_js.default_timezone;
				for (abbr in tal) {
					for (i = 0; i < tal[abbr].length; i++) {
					if (tal[abbr][i].timezone_id === _default) {
						return abbr.toUpperCase();
					}
					}
				}
				}
				for (abbr in tal) {
				for (i = 0; i < tal[abbr].length; i++) {
					os = -jsdate.getTimezoneOffset() * 60;
					if (tal[abbr][i].offset === os) {
					return abbr.toUpperCase();
					}
				}
				}
				*/
				return 'UTC';
			},
			Z: function() {
				return -jsdate.getTimezoneOffset() * 60;
			},

			c: function() {
				return 'Y-m-d\\TH:i:sP'.replace(formatChr, formatChrCb);
			},
			r: function() {
				return 'D, d M Y H:i:s O'.replace(formatChr, formatChrCb);
			},
			U: function() {
				return jsdate / 1000 | 0;
			}
			};
			this.date = function(format, timestamp) {
			that = this;
			jsdate = (timestamp === undefined ? new Date() :
				(timestamp instanceof Date) ? new Date(timestamp) :
				new Date(timestamp * 1000)
			);
			return format.replace(formatChr, formatChrCb);
			};
			return this.date(format, timestamp);
		}


		$(function() {

			/* initialize */
			updateChart();

			/* <events> */
				$('select.adjuster_selector').bind('change',function() {
					updateSelected();
					updateUrl();
					window.location.reload();
				});
				$('select.stock_selector').bind('change',function() {
					updateSelected();
					updateUrl();
					window.location.reload();
				});
				$('select.time_selector').bind('change',function() {
					updateSelected();
					updateUrl();
					window.location.reload();
				});

				$('.legend .interactive_legend').bind('click',function(e) {
					$(this).find('input[type="checkbox"]').click();
				});

				$('.legend input[type="checkbox"]').bind('click',function(e) {
					e.stopPropagation();
				});

				$('.legend input[type="checkbox"]').bind('change',function(e) {
					console.log('1');
					console.log("$('.legend input[type=checkbox]').bind('change',function(e) {");
					show_stock=$('.legend input[type="checkbox"].show_stock:checked').length;
					show_divided_by=$('.legend input[type="checkbox"].show_divided_by:checked').length;
					show_adjusted=$('.legend input[type="checkbox"].show_adjusted:checked').length;
					show_adjuster=$('.legend input[type="checkbox"].show_adjuster:checked').length;
					logarithmic=$('.legend input[type="checkbox"].logarithmic:checked').length;
					updateVisibility();
					updateUrl();
				});
			/* </events> */

		});

		var stock_max=0;
		var adjusted_max=0;
		var adjuster_max=0;
		var divided_by_max=0;

		var stock_min=0;
		var adjusted_min=0;
		var adjuster_min=0;
		var divided_by_min=0;

		var is_animating=0;

		/* <animation> */

			/* <find currently visible dataset of stock and animate it> */
				function animateStockLine() {
					is_animating=true;

					chart.options.scales.yAxes[0].ticks.min=0;
					chart.options.scales.yAxes[1].ticks.min=0;
					chart.options.scales.yAxes[2].ticks.min=0;
					chart.options.scales.yAxes[3].ticks.min=0;

					chart.options.scales.yAxes[0].ticks.max=0;
					chart.options.scales.yAxes[1].ticks.max=0;
					chart.options.scales.yAxes[2].ticks.max=0;
					chart.options.scales.yAxes[3].ticks.max=0;

					animationDataBufferIterator=0;
					animationWhatAreWeAnimating='stock';

					animationFindDatasetIndexIterator=0;
					chart.data.datasets.forEach(function(dataset) {
						
						if(dataset.id==adjuster_selected+'_adjuster') {
							/* <set chart max for adjuster> */
								adjuster_min=findMin(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
								adjuster_max=findMax(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
							/* </set chart max for adjuster> */

/* TODO ANIMATE ADJUSTER BLUE LINE */

							/*animationDataAdjusterBuffer=chart.data.datasets[animationFindDatasetIndexIterator]['data'];
							chart.data.datasets[animationFindDatasetIndexIterator]['data']=[];*/
						}
						if(dataset.id==stock_selected) {
							/* <set chart max for stock> */
								stock_min=findMin(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
								stock_max=findMax(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
							/* </set chart max for stock> */

							if(!show_stock) {
								/* if stock is not shown, don't put data in buffer, because we won't animate it */
							}
							else {
								animationDataStockBuffer=chart.data.datasets[animationFindDatasetIndexIterator]['data'];
								chart.data.datasets[animationFindDatasetIndexIterator]['data']=[];
								animationDatasetIndex=animationFindDatasetIndexIterator;
							}
							requestAnimationFrame(animationStep);
						}
						
						if(dataset.id==adjuster_selected+'_adj_'+stock_selected) {
							/* <set chart max for adjusted> */
								adjusted_min=findMin(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
								adjusted_max=findMax(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
							/* </set chart max for adjusted> */

							animationDataDividedByBuffer=chart.data.datasets[animationFindDatasetIndexIterator]['data'];
							chart.data.datasets[animationFindDatasetIndexIterator]['data']=[];
						}


						if(dataset.id==stock_selected+'_divided_by_'+adjuster_selected) {
							/* <set chart max for adjusted> */
								divided_by_min=findMin(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
								divided_by_max=findMax(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
							/* </set chart max for adjusted> */
						}
					
						animationFindDatasetIndexIterator++;;
						
					});
				}
			/* </find currently visible dataset of stock and animate it> */

			/* <find currently visible dataset of divided_by data and animate it> */
				function animateDividedByLine() {
					is_animating=true;

					animationDataBufferIterator=0;
					animationWhatAreWeAnimating='divided_by';

					animationFindDatasetIndexIterator=0;
					chart.data.datasets.forEach(function(dataset) {

						if(dataset.id==adjuster_selected+'_adj_'+stock_selected) {
							animationDatasetIndex=animationFindDatasetIndexIterator;
							requestAnimationFrame(animationStep);
						}

						animationFindDatasetIndexIterator++;;
						
					});

					is_animating=false;
				}
			/* </find currently visible dataset of adjusted data and animate it> */

			function animationStep() {

				/* <do it N times so it's faster> */
					if(animationWhatAreWeAnimating=='stock') {
						var bufferToUse=animationDataStockBuffer;
					}
					if(animationWhatAreWeAnimating=='divided_by') {
						var bufferToUse=animationDataDividedByBuffer
					}

					var i=0;
					while(i<25) {
						chart.data.datasets[animationDatasetIndex]['data'].push(bufferToUse[animationDataBufferIterator]);
						animationDataBufferIterator++;
						i++;
					}
				/* </do it N times so it's faster> */


				chart.update();


				/* <quit if data finished> */
					if(animationWhatAreWeAnimating=='stock' && !show_stock) {
						/* if stock not visible, skip straight to draw the line instantly then go animate adjusted line, otherwise we'd have wait for a line to be drawn that we cannot see anyway (stock) */
						window.requestAnimationFrame(animateDividedByLine);
					}
					else if(animationDataBufferIterator<bufferToUse.length) {
						/* continue animating because we're not done yet */
						window.requestAnimationFrame(animationStep);
					}
					else {
						/* if we finished, see if we are animating the stock line, so we can animate the adjusted line next */
						if(animationWhatAreWeAnimating=='stock') {
							window.requestAnimationFrame(animateDividedByLine);
						}
					}
				/* </quit if data finished> */
				
			}
			function findMin(array) {
				var min=Infinity;
				for (var i = 0; i < array.length; i++) {
					var value=parseFloat(array[i]);
					if(value<min) {
						min=value;
					}
				}
				return min;
			}
			function findMax(array) {
				var max=0;
				for (var i = 0; i < array.length; i++) {
					var value=parseFloat(array[i]);
					if(value>max) {
						max=value;
					}
				}
				return max;
			}
		/* </animation> */


		function updateVisibility() {

			if(logarithmic) {
				chart.options.scales.yAxes[0].type='logarithmic'
				chart.options.scales.yAxes[1].type='logarithmic'
				chart.options.scales.yAxes[2].type='logarithmic'
				chart.options.scales.yAxes[3].type='logarithmic'
			}
			else {
				chart.options.scales.yAxes[0].type='linear'
				chart.options.scales.yAxes[1].type='linear'
				chart.options.scales.yAxes[2].type='linear'
				chart.options.scales.yAxes[3].type='linear'
			}



			if(show_stock) {
				$('.legend .show_stock_legend').css('opacity',1);
			}
			else {
				$('.legend .show_stock_legend').css('opacity',0.5);
			}
			if(show_divided_by) {
				$('.legend .show_divided_by_legend').css('opacity',1);
			}
			else {
				$('.legend .show_divided_by_legend').css('opacity',0.5);
			}
			if(show_adjusted) {
				$('.legend .show_adjusted_legend').css('opacity',1);
			}
			else {
				$('.legend .show_adjusted_legend').css('opacity',0.5);
			}
			if(show_adjuster) {
				$('.legend .show_adjuster_legend').css('opacity',1);
			}
			else {
				$('.legend .show_adjuster_legend').css('opacity',0.5);
			}
			if(logarithmic) {
				$('.legend .logarithmic_legend').css('opacity',1);
			}
			else {
				$('.legend .logarithmic_legend').css('opacity',0.5);
			}


			if(show_stock) {
				chart.options.scales.yAxes[0].display=true;
			}
			else {
				chart.options.scales.yAxes[0].display=false;
			}

			if(show_adjuster) {
				chart.options.scales.yAxes[1].display=true;
			}
			else {
				chart.options.scales.yAxes[1].display=false;
			}

			if(show_divided_by) {
				chart.options.scales.yAxes[2].display=true;
			}
			else {
				chart.options.scales.yAxes[2].display=false;
			}

			if(show_adjusted) {
				chart.options.scales.yAxes[3].display=true;
			}
			else {
				chart.options.scales.yAxes[3].display=false;
			}

			/* <set min & max> */
				if(show_adjusted && show_stock &&  stock_min>adjusted_min) {
					stock_or_adjusted_min=adjusted_min;
				}
				else if(show_stock) {
					stock_or_adjusted_min=stock_min;
				}
				else if(show_adjusted) {
					stock_or_adjusted_min=adjusted_min;
				}
				
				chart.options.scales.yAxes[0].ticks.min=stock_or_adjusted_min; /* adj by shd follow same as stock min/max to align */
				chart.options.scales.yAxes[1].ticks.min=adjuster_min;
				chart.options.scales.yAxes[2].ticks.min=divided_by_min;
				chart.options.scales.yAxes[3].ticks.min=stock_or_adjusted_min; /* adj by shd follow same as stock min/max to align */




				if(show_adjusted && show_stock && stock_max<adjusted_max) {
					stock_or_adjusted_max=adjusted_max;
				}
				else if(show_stock) {
					stock_or_adjusted_max=stock_max;
				}
				else if(show_adjusted) {
					stock_or_adjusted_max=adjusted_max;
				}
				
				chart.options.scales.yAxes[0].ticks.max=stock_or_adjusted_max;
				chart.options.scales.yAxes[1].ticks.max=adjuster_max;
				chart.options.scales.yAxes[2].ticks.max=divided_by_max;
				chart.options.scales.yAxes[3].ticks.max=stock_or_adjusted_max; /* adj by shd follow same as stock min/max to align */



				// chart.options.scales.yAxes[0].ticks.min=0;
				// chart.options.scales.yAxes[1].ticks.min=0;

				/* <make stock and adjuster scaled in axis> */
					chart.options.scales.yAxes[0].ticks.max=chart.options.scales.yAxes[1].ticks.max/chart.options.scales.yAxes[1].ticks.min*chart.options.scales.yAxes[0].ticks.min;

					// check if data doesn't fall out of max, if so scale it up on both so both data shows properly in bounds
					if(chart.options.scales.yAxes[0].ticks.max<stock_or_adjusted_max) {
						console.log(chart.options.scales.yAxes[0].ticks.max,'<',stock_or_adjusted_max);
						var scaler=stock_or_adjusted_max/chart.options.scales.yAxes[0].ticks.max;
						chart.options.scales.yAxes[0].ticks.max=chart.options.scales.yAxes[0].ticks.max*scaler;
						chart.options.scales.yAxes[1].ticks.max=chart.options.scales.yAxes[1].ticks.max*scaler;
					}
					if(chart.options.scales.yAxes[1].ticks.max<adjuster_max) {
						console.log(chart.options.scales.yAxes[1].ticks.max,'<',adjuster_max);
						var scaler=adjuster_max/chart.options.scales.yAxes[1].ticks.max;
						chart.options.scales.yAxes[1].ticks.max=chart.options.scales.yAxes[1].ticks.max*scaler;
						chart.options.scales.yAxes[0].ticks.max=chart.options.scales.yAxes[0].ticks.max*scaler;
					}
				/* </make stock and adjuster scaled in axis> */



			/* </set min & max> */

			var iterator=0;
			chart.data.datasets.forEach(function(dataset) {

				if(dataset.id==stock_selected && !show_stock) {
					chart.data.datasets[iterator].hidden=true;
				}
				if(dataset.id==stock_selected && show_stock) {
					chart.data.datasets[iterator].hidden=false;
				}

				if(dataset.id==adjuster_selected+'_adj_'+stock_selected && !show_adjusted) {
					chart.data.datasets[iterator].hidden=true;
				}
				if(dataset.id==adjuster_selected+'_adj_'+stock_selected && show_adjusted) {
					chart.data.datasets[iterator].hidden=false;
				}

				if(dataset.id==adjuster_selected+'_adjuster' && !show_adjuster) {
					chart.data.datasets[iterator].hidden=true;
				}
				if(dataset.id==adjuster_selected+'_adjuster' && show_adjuster) {
					chart.data.datasets[iterator].hidden=false;
				}

				if(dataset.id==stock_selected+'_divided_by_'+adjuster_selected && !show_divided_by) {
					chart.data.datasets[iterator].hidden=true;
				}
				if(dataset.id==stock_selected+'_divided_by_'+adjuster_selected && show_divided_by) {
					chart.data.datasets[iterator].hidden=false;
				}

				iterator++;
			});

			chart.update();
		}
		function updateUrl() {
			var params='';
			/* make sure all these are set to the default, so that if they differ from the default we add the ?params only */
			if(time_selected!='<?=$time_selected_default?>') {
				params=params+'&time='+time_selected;
			}
			if(!show_stock) {
				params=params+'&show_stock='+show_stock;
			}
			if(show_adjusted) {
				params=params+'&show_adjusted='+show_adjusted;
			}
			if(show_adjuster) {
				params=params+'&show_adjuster='+show_adjuster;
			}
			if(!show_divided_by) {
				params=params+'&show_divided_by='+show_divided_by;
			}
			if(logarithmic) {
				params=params+'&logarithmic='+logarithmic;
			}
			params=params.substr(1);
			if(params) {
				params='/?'+params;
			}
			/* <cpi-in-btc -> bitcoin-price-index> */
				if(stock_selected+'-in-'+adjuster_selected=='cpi-in-btc') {
					uri='/bitcoin-price-index'+params;
				}
			/* </cpi-in-btc -> bitcoin-price-index> */
			else {
				uri='/'+stock_selected+'-in-'+adjuster_selected+params;
			}
			history.pushState(null,null,uri);

			/* <preload social image so it's available/cached when needed> */
				<?if($_GET['layout']!='screenshot'){ /* avoid recursive loop */ ?>
					setTimeout(function() {
						$.ajax({
							url: 'https://inflationchart.com/?action=screenshot&uri='+encodeURIComponent(uri)
						});
					},5000);
				<?}?>
			/* </preload social image so it's available/cached when needed> */
		}

		function updateSelected() {
			adjuster_selected=$('select.adjuster_selector').children("option:selected").val();
			adjuster_selected_label=$('select.adjuster_selector').children("option:selected").text();
			adjuster_selected_suffix=$('select.adjuster_selector').children("option:selected").data('suffix');
			stock_selected=$('select.stock_selector').children("option:selected").val();
			stock_selected_label=$('select.stock_selector').children("option:selected").text();
			stock_selected_suffix=$('select.stock_selector').children("option:selected").data('suffix');
			time_selected=$('select.time_selector').children("option:selected").val();
		}
		function decimalify(t) {
			/* by levelsio */
			if(t==0) {
				t=0;
			}
			else if(t>=1000000000000) {
				t=number_format(t/1000000000000,1)+'T';
			}
			else if(t>=1000000000) {
				t=number_format(t/1000000000,1)+'B';
			}
			else if(t>=1000000) {
				t=number_format(t/1000000,1)+'M';
			}
			else if(t>=1000) {
				t=number_format(t/1000,1)+'k';
			}
			else if(t<=0.00000000001) {
				t=number_format(t*1000000000000,1)+' (1/T)';
			}
			else if(t<=0.00000001) {
				t=number_format(t*1000000000,1)+' (1/B)';
			}
			else if(t<=0.00001) {
				t=number_format(t*1000000,1)+' (1/M)';
			}
			else if(t<=0.1) {
				t=number_format(t,2);
			}
			else if(t<=1) {
				t=number_format(t,2);
			}
			else if(t<=10) {
				t=number_format(t,2);
			}
			else {
				t=number_format(t);
			}

			t=str_replace('.00','',t);
			return t;
		}
		function updateChart() {
			updateSelected();
			
			document.title=stock_selected_label+' Price in '+adjuster_selected_label;
			if(stock_selected+'-in-'+adjuster_selected=='cpi-in-btc') {
				document.title='Bitcoin Price Index';
			}

			$('.legend .stock_selected').text($('select.stock_selector').children("option:selected").data('short'));

			$('.legend .adjuster_selected').text($('select.adjuster_selector').children("option:selected").data('short'));

			chart.data.datasets.forEach(function(dataset) {

				var notTheStockOrTheAdjustedStock=true;

				if(dataset.id==stock_selected) {
					/* make original stock low opacity, but show */
					dataset.hidden=false;
					notTheStockOrTheAdjustedStock=false;
				}

				if(dataset.id==stock_selected+'_divided_by_'+adjuster_selected) {
					/* make adjusted stock high opacity, and show */
					dataset.hidden=false;
					dataset.borderColor='rgba(255,71,66,1)';
					notTheStockOrTheAdjustedStock=false;
				}

				if(dataset.id==adjuster_selected+'_adj_'+stock_selected) {
					/* make adjusted stock high opacity, and show */
					dataset.hidden=false;
					notTheStockOrTheAdjustedStock=false;
				}

				/* <new, show adjusted nominal values too as blue line> */
					if(dataset.id==adjuster_selected+'_adjuster') {
						dataset.hidden=false;
						notTheStockOrTheAdjustedStock=false;
					}
				/* </new, show adjusted nominal values too as blue line> */

				
				if(notTheStockOrTheAdjustedStock) {
					dataset.hidden=true;
					/* dataset.borderColor='rgba(255,71,66,0.1)'; */
				}
			});

			/*chart.update();*/

			animateStockLine();
			updateVisibility();
			updateUrl();

		}

	</script>

	<div class="chart">
		<canvas id="chart" width="500" height="600"></canvas>
	</div>

	<script>
		
		var ctx = document.getElementById("chart").getContext('2d');
		var gradientRed = ctx.createLinearGradient(0, 0, 0, 600);
		gradientRed.addColorStop(0.5, 'rgba(255,71,66,0.125)');	 
		gradientRed.addColorStop(1, 'rgba(255,71,66,0)');

		var gradientGreen = ctx.createLinearGradient(0, 0, 0, 600);
		gradientGreen.addColorStop(0, 'rgba(43,222,115,0.25)');	 
		gradientGreen.addColorStop(1, 'rgba(43,222,115,0)');
		
		var gradientBlue = ctx.createLinearGradient(0, 0, 0, 600);
		gradientBlue.addColorStop(0, 'rgba(25,25,125,0.5)');	 
		gradientBlue.addColorStop(1, 'rgba(25,25,125,0)');

		var gradientYellow = ctx.createLinearGradient(0, 0, 0, 600);
		gradientYellow.addColorStop(0.85, 'rgba(255,201,36,0.125)');   
		gradientYellow.addColorStop(1, 'rgba(255,201,36,0)');


		var chart = new Chart(ctx, {
			type: 'line',
			data: {
				labels:
					[<?
						foreach($data as $row) {
							if(empty($row['epoch'])) {
								continue;
							}
							echo '"'.date('Y-m',$row['epoch']).'"';
							/*?>new Date(<?=$row['epoch']?>*1000)<?*/
							echo ',';
						}
					?>],
					datasets: [
						{
							<?if($_GET['layout']=='screenshot'){?>
								borderWidth:4,
							<?} else {?>
								borderWidth:2,
							<?}?>
							hidden:true,
				 	 		id:'<?=$stock_selected?>',
							label: '<?=$stocks[$stock_selected]?>',
							borderColor: '#2bde73',
							backgroundColor: gradientGreen,
							yAxisID:'stock',
							fill: true,
							data: 
							[
								<?
									unset($previousValue);
									foreach($data as $row) {
										if(empty($row['epoch'])) {
											continue;
										}
										if(!$row[$stock_selected]) {
											if(!empty($previousValue) && $doubleEmptyValueLimiter<2) { 
												/* if missing data show previous value to fill in, because =GOOGLEFINANCE sometimes randomly misses single dates */
												echo $previousValue;
												$doubleEmptyValueLimiter++;
											}
											echo ',';
											continue;
										}
										echo $row[$stock_selected];
										echo ',';
										$previousValue=$row[$stock_selected];
										unset($doubleEmptyValueLimiter);
									}
								?>
							],
						},
						{
							<?if($_GET['layout']=='screenshot'){?>
								borderWidth:4,
							<?} else {?>
								borderWidth:2,
							<?}?>
							hidden:true,
				 	 		id:'<?=$adjuster_selected?>_adjuster',
							label: '<?=$adjusters[$adjuster_selected]?>',
							borderColor: '#42a5ff',
							backgroundColor: gradientBlue,
							yAxisID:'adjuster',
							fill: true,
							data: 
							[
								<?
									unset($previousValue);
									foreach($data as $row) {
										if(!$row[$adjuster_selected]) {
											if(!empty($previousValue) && $doubleEmptyValueLimiter<2) { 
												/* if missing data show previous value to fill in, because =GOOGLEFINANCE sometimes randomly misses single dates */
												echo $previousValue;
												$doubleEmptyValueLimiter++;
											}
											echo ',';
											continue;
										}
										echo $row[$adjuster_selected];
										echo ',';
										$previousValue=$row[$adjuster_selected];
										unset($doubleEmptyValueLimiter);
									}
								?>
							],
						},
						{
							<?if($_GET['layout']=='screenshot'){?>
								borderWidth:4,
							<?} else {?>
								borderWidth:2,
							<?}?>
							hidden:true,
				 	 		id:'<?=$stock_selected?>_divided_by_<?=$adjuster_selected?>',
							label: '<?=$stocks[$stock_selected]?> / <?=$adjusters[$adjuster_selected]?>',
							yAxisID:'divided_by',
							borderColor: '#ff4742',
							backgroundColor: gradientRed,
							fill: true,
							data: 
							[
								<?
									unset($previousValue);
									$doubleEmptyValueLimiter=0;
									foreach($data as $row) {
										if(empty($row['epoch'])) {
											continue;
										}
										if(!$row[$stock_selected.'_divided_by_'.$adjuster_selected]) {
											if(!empty($previousValue) && $doubleEmptyValueLimiter<2) {
												/* if missing data show previous value to fill in, because =GOOGLEFINANCE sometimes randomly misses single dates */
												echo $previousValue;
												$doubleEmptyValueLimiter++;
											}
											echo ',';
											continue;
										}
										echo $row[$stock_selected.'_divided_by_'.$adjuster_selected];
										echo ',';
										$previousValue=$row[$stock_selected.'_divided_by_'.$adjuster_selected];
										unset($doubleEmptyValueLimiter);
									}
								?>
							],
						},
						{
							<?if($_GET['layout']=='screenshot'){?>
								borderWidth:4,
							<?} else {?>
								borderWidth:2,
							<?}?>
							hidden:true,
				 	 		id:'<?=$adjuster_selected?>_adj_<?=$stock_selected?>',
							label: '<?=$adjusters[$adjuster_selected]?>-adj <?=$stocks[$stock_selected]?>',
							yAxisID:'adjusted',
							borderColor: '#ffc924',
							backgroundColor: gradientYellow,
							fill: true,
							data: 
							[
								<?
									unset($previousValue);
									$doubleEmptyValueLimiter=0;
									foreach($data as $row) {
										if(empty($row['epoch'])) {
											continue;
										}
										if(!$row[$adjuster_selected.'_adj_'.$stock_selected]) {
											if(!empty($previousValue) && $doubleEmptyValueLimiter<2) {
												/* if missing data show previous value to fill in, because =GOOGLEFINANCE sometimes randomly misses single dates */
												echo $previousValue;
												$doubleEmptyValueLimiter++;
											}
											echo ',';
											continue;
										}
										echo $row[$adjuster_selected.'_adj_'.$stock_selected];
										echo ',';
										$previousValue=$row[$adjuster_selected.'_adj_'.$stock_selected];
										unset($doubleEmptyValueLimiter);
									}
								?>
							],
						},
					]
			},
		
			options: {
				elements:{point:{radius:0},line: {
					tension:0.1
				}},
				animation: false,
				responsive:true,
				maintainAspectRatio:false,
				legend: {
					display: false
				},
				tooltips: {
					mode:'x-axis',
					intersect:false,
					callbacks: {
						labelColor: function(tooltipItem, chart) {
							var id=chart.config.data.datasets[tooltipItem.datasetIndex].id;
							if(id==adjuster_selected+'_adj_'+stock_selected) {
								return {
									borderColor:'#ffc924',
									backgroundColor:'#ffc924',
								};
							}
							else if(id==stock_selected+'_divided_by_'+adjuster_selected) {
								return {
									borderColor:'#ff4742',
									backgroundColor:'#ff4742',
								};
							}
							else if(id==stock_selected) {
								return {
									borderColor:'#2bde73',
									backgroundColor:'#2bde73',
								};
							}
							else if(id==adjuster_selected+'_adjuster') {
								return {
									borderColor:'#42a5ff',
									backgroundColor:'#42a5ff',
								};
							}
							else {
								return {
									borderColor:'#000',
									backgroundColor:'#000',
								};
							}
			            },
						label: function(tooltipItem, data) {
							var label = data.datasets[tooltipItem.datasetIndex].label || '';

							t=decimalify(tooltipItem.yLabel);

							if(label.indexOf(' / ')>-1) {
								label = t+' '+label;
							}
							else {
								label = '$'+t+' '+label;
							}
							return label;
						}
					}
				},
				scales: {
					xAxes: [{
						type: 'time',
						time: {
							format:'YYYY-MM',
							tooltipFormat:'YYYY-MM'
						},
						position: "bottom",
						gridLines:{
							tickMarkLength: 1,
							color: '#252525',
							zeroLineColor: '#252525'
						},
						ticks: {
							autoSkip:true, 
							maxTicksLimit:30,
							padding:14,
							fontFamily:'Iosevka Web, monospace',
							fontColor:'rgb(186,186,186)',
							labelOffset: 18,
							callback: function(t) {
								return t;
							}
						}
					}],
					yAxes: [
						{
							id:'stock',
							<?if($logarithmic){?>
								type: 'logarithmic',
							<?} else {?>
								type: 'linear',
							<?}?>
							display:false,
							stacked:false,
							position:'left',
							gridLines:{
								tickMarkLength: 1,
								color: '#252525',
								zeroLineColor: '#252525'
							},
							ticks: {
								maxRotation: 0,
								autoSkip:true,
								maxTicksLimit:12,
								beginAtZero: false, 
								/*min: 0,*/
								max:1,
								padding:14,
								fontFamily:'Iosevka Web, monospace',
								fontColor:'#2bde73',
								fontSize: 12,
								callback: function(t) {
									if(stock_selected_suffix) {
										return decimalify(t)+stock_selected_suffix;
									}
									return '$'+decimalify(t);
								}
							}
						},
						{
							<?if($logarithmic){?>
								type: 'logarithmic',
							<?} else {?>
								type: 'linear',
							<?}?>
							display:false,
							id: 'adjuster',
							stacked:false,
							position:'right',
							gridLines:{
								tickMarkLength: 1,
								color: '#252525',
								zeroLineColor: '#252525'
							},
							ticks: {
								maxRotation: 0,
								autoSkip:true,
								maxTicksLimit:12,
								beginAtZero: false,
								max:1,
								padding:14,
								fontFamily:'Iosevka Web, monospace',
								fontColor:'#42a5ff',
								fontSize: 12,
								callback: function(t) {
									if(adjuster_selected_suffix) {
										return decimalify(t)+adjuster_selected_suffix;
									}
									return '$'+decimalify(t);
								}
							}
						},
						{
							<?if($logarithmic){?>
								type: 'logarithmic',
							<?} else {?>
								type: 'linear',
							<?}?>
							display:false,
							id: 'divided_by',
							stacked:false,
							position:'right',
							gridLines:{
								tickMarkLength: 1,
								color: '#252525',
								zeroLineColor: '#252525'
							},
							ticks: {
								maxRotation: 0,
								autoSkip:true,
								maxTicksLimit:12,
								beginAtZero: false,
								max:1,
								padding:14,
								fontFamily:'Iosevka Web, monospace',
								fontColor:'#ff4742',
								fontSize: 12,
								callback: function(t) {
									return decimalify(t);
								}
							}
						},
						{
							<?if($logarithmic){?>
								type: 'logarithmic',
							<?} else {?>
								type: 'linear',
							<?}?>
							display:false,
							id:'adjusted',
							stacked:false,
							position:'right',
							gridLines:{
								tickMarkLength: 1,
								color: '#252525',
								zeroLineColor: '#252525'
							},
							ticks: {
								maxRotation: 0,
								autoSkip:true,
								maxTicksLimit:12,
								beginAtZero: false,
								max:1,
								padding:14,
								fontFamily:'Iosevka Web, monospace',
								fontColor:'#ffc924',
								fontSize: 12,
								callback: function(t) {
									if(adjuster_selected_suffix) {
										return decimalify(t)+adjuster_selected_suffix;
									}
									return '$'+decimalify(t)+adjuster_selected_suffix;
								}
							}
						}
					]
				}
			}
		});
		</script>




		<div class="legend">



			<span class="show_stock_legend interactive_legend">
				<input type="checkbox" class="show_stock" <?if($show_stock){?>checked<?}?>>
				<span style="color:rgb(43,222,115)">
					<!-- 🟢 -->

					<span class="stock_selected">
						
					</span>
				</span>
			</span><br/>




			<span class="mobile_line_break"></span>

			<span class="show_adjuster_legend interactive_legend">
				<input type="checkbox" class="show_adjuster" <?if($show_adjuster){?>checked<?}?>>
				<span style="color:#42a5ff">
					<!-- 🔵 -->

					<span class="adjuster_selected">
						
					</span>
				</span>
			</span><br/>



			<span class="mobile_line_break"></span>

			<span class="show_divided_by_legend interactive_legend">
				<input type="checkbox" class="show_divided_by" <?if($show_divided_by){?>checked<?}?>>
				<span data-type="adjusted"><!-- 🔴  -->
					<span class="stock_selected"></span>
					/
					<span class="adjuster_selected"></span>
				</span>
			</span>
			</span><br/>


			<span class="mobile_line_break"></span>

			<span class="show_adjusted_legend interactive_legend">
				<input type="checkbox" class="show_adjusted" <?if($show_adjusted){?>checked<?}?>>
				<span class="adjuster_selected"></span>-adj<span><!-- 🔴  -->
					<span class="stock_selected"></span>
				</span>
			</span><br/>






			<span class="mobile_line_break"></span>

			<span class="logarithmic_legend interactive_legend">
				<input type="checkbox" class="logarithmic" <?if($logarithmic){?>checked<?}?>>
				<span data-type="logarithmic">📐Logarithmic</span>
			</span><br/>


		</div>





		<a target="_new" href="https://ibkr.com/referral/pieter414" class="aux-text">
			Invest wisely and you can maintain or increase your standard of life. Invest poorly and the road to serfdom is real. <span class="button">Invest with IBKR</span>
		</a>





	</div>



	<div class="side">
		<!-- <div class="youtube-wrapper">
			<iframe style="margin-bottom:-5px" width="100%" height="200" src="https://www.youtube.com/embed/W41vsTO2GHY?autoplay=1&controls=0&mute=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
		</div> -->
		<p>
			<strong style="color:#fff;">
				Most popular pages (via <a href="https://simpleanalytics.com/inflationchart.com">Simple Analytics</a>)
			</strong><br/>
			<a href="/spx-in-m1">S&P500 in M1</a><br/>
			<a href="/income-in-food">Avg US Income in Food</a><br/>
			<a href="/income-in-home">Avg US Income in Avg US Home Price</a><br/>
			<a href="/spx-in-income">S&P500 in Avg US Income</a><br/>

			<a href="/spx-in-btc?logarithmic=1">S&P500 in BTC</a><br/>
			<a href="/spx-in-oil?logarithmic=1">S&P500 in Oil</a><br/>
			<a href="/spx-in-gold?logarithmic=1">S&P500 in Gold</a><br/>
			<a href="/btc-in-m1">BTC in M1</a><br/>
			<a href="/china-in-btc?logarithmic=1">China SSE in BTC</a><br/>
			<a href="/food-in-btc?logarithmic=1">Food in BTC</a><br/>
			<a href="/bigmac-in-btc?logarithmic=1">Big Mac in BTC</a><br/>
			<a href="/dji-in-btc?logarithmic=1">DJI in BTC</a><br/>
			<a href="/gold-in-btc?logarithmic=1">Gold in BTC</a><br/>
			<a href="/btc-in-gold">BTC in Gold</a><br/>

<?/*
			<a href="/income-in-food_and_home">Avg US Income in Food + Avg US Home Price</a><br/>
			<a href="/income-in-bigmac">Avg US Income in Big Macs</a><br/>

			<a href="/home-in-m1">Avg US Home in M1</a><br/>
			<a href="/income-in-btc?logarithmic=1">Avg US Income in BTC</a><br/>
			<a href="/nasdaq-in-btc?logarithmic=1">NASDAQ in BTC</a><br/>
			<a href="/tsla-in-btc?logarithmic=1">TLSA in BTC</a><br/>
			<a href="/gdp-in-btc?logarithmic=1">GDP in BTC</a><br/>

			<a href="/spx-in-bigmac">S&P500 in Big Macs</a><br/>
			<a href="/china-in-food">China SSE in Food</a><br/>
			<a href="/china-in-food_and_home">S&P500 in Food + Avg US Home</a><br/>
			
			<a href="/home-in-food">Avg US Home in Food</a><br/>
			<a href="/home-in-bigmac">Avg US Home in Big Macs</a><br/>
			<a href="/bigmac-in-cpi">Big Mac in CPI</a><br/>
			<a href="/bigmac-in-m1">Big Mac in M1</a><br/>
			<a href="/silver-in-gold">Silver in Gold</a><br/>
			
			*/?>
		</p>

		<!-- <div class="fed-wrapper">
			<img src="/assets/fed3.gif?<?=filemtime(__DIR__.'/../assets/fed3.gif');?>" class="fed" />
		</div> -->
		<p>
			<strong style="color:#fff;">
				What is this?
			</strong><br/>

			📈 This chart shows 🟢<span style="color:rgb(43,222,115)">the nominal price</span> vs. 🔴<span style="color:rgb(255,71,66)">the real value</span> (as adjusted for inflation) of the stock market (or another value you select). You can inflation-adjust it by the U.S.-dollar money supply M1, M2 or MB (the money base), CPI, Big Mac, Gold, BTC and ETH.
		</p>
		<p>
			📉 Combining data sets we can adjust the stock market and home prices with the actual money supply, and find that even if <span style="color:rgb(43,222,115)">it looks like stock markets and home prices are going up</span>, they <span style="color:rgb(255,71,66)">may actually be going down in real value</span>. 
		</p>

	

		<?/*<p>
			<strong style="color:#fff;">
				Explain like I'm 5 years old?
			</strong><br/>
			<strong>
				💣 TL;DR your money is getting worth less over time, and recently faster than before and this site provides evidence for it.
			</strong>
		</p>
		<p>
			👶 Let's start: select [M1-adjusted] [S&P500] in [last 20 years] first on top. M1 is the money base, which means all $ in circulation. S&P500 is the most important stock market index of 500 big American companies.
		</p>
		<p>
			The 🟢green line is the actual price of the S&P500 in history up to today. The 🔴red line converges to the same price the closer we get to the past (the left end of the chart). 
		</p>
		<p>
			But if you go forward in time (the right end of the chart) the lines start dispersing. The price of the S&P500 in the year 2000 is ~$1,400. But adjusted by the <a href="https://fred.stlouisfed.org/series/BOGMBASE">money base (MB)</a>, it is ~$400 in today's prices, or ~3x less. That's because in those 20 years, the MB grew by 8x. For every 1 dollar that was in existence in the year 2000, there's now 8 dollars. You could then expect the value of the S&P500 to also grow by at least 8x. That'd mean the value stayed at least the same. But the S&P500 only grew by 2.6x. 8x divided by 2.6x means there's been a decrease in real value of the S&P500 about ~3x (if adjusted by the money base, MB). That 3x is the same as the 3x we found comparing the price of the S&P500 in the year 2000 and the MB-adjusted price today.
		</p>
		<p>
			That decrease in real value is visible in the chart at specific moments. Look at the 🔴red line in 2008, when there was the <a href="https://en.wikipedia.org/wiki/Financial_crisis_of_2007%E2%80%932008">Financial Crisis</a> and at 2020 when the <a href="https://en.wikipedia.org/wiki/COVID-19_pandemic">COVID-19 Pandemic</a> started. That's moments when the Federal Reserve and other central banks started printing lots of money from thin air. You don't see that in the 🟢green line as that's the official prices. That doesn't mean it's some conspiracy. It just means the nominal/official prices of stock markets and stocks don't tell the whole story of the economy is actually growing or not.
		</p>
		<p>
			There's more indicators you can adjust by then just the money base. Try a few by clicking on the [-adjusted] select box top left and changing it. You can also change what you'd like to adjust by clicking the second select box. And you can change the time view with the third box. Happy researching!
		</p>*/?>
		<p>
			<strong style="color:#fff;">
				What data you use?
			</strong><br/>

			🇺🇸 S&P500, Dow Jones (DJI) and NASDAQ are the most common stock market indices, representing the performance of the United States, but in a way are so important they're quite benchmark of the West and the entire globe. Investors use these indices (plural of index) as a benchmark of the overall market conditions. The NASDAQ index especially is heavily weighted towards tech.<?/* Historical data for these is from <a href="https://google.com/finance">Google Finance</a>.*/?>
		</p>
		<p>
			🇨🇳 China SSE is the Shanghai Stock Exchange Composite Index, a.k.a. the main stock market of China. It's converted to USD with the CNY:USD rate of the historical date of each data point.<?/* Data is from <a href="https://finance.yahoo.com/quote/000001.SS/">Yahoo Finance</a>.*/?>
		</p>
		<p>
			🌏 Asia is the MSCI Asia (ex-Japan) index (in USD), which is a benchmark of over 1,000+ of the most important public companies all over Asia.
		</p>
		<p>
			💰 US GDP is the <a href="https://fred.stlouisfed.org/series/GDP">gross domestic product of the U.S.</a>.
		</p>
		<p>
			💰 Avg US Income is the <a href="https://fred.stlouisfed.org/series/MEHOINUSA646N">annual median U.S. household income</a>.
		</p>
		<p>
			🏆 Gold price (aka XAU) is from <a href="https://www.indexmundi.com/commodities/?commodity=gold&months=360">IndexMundi</a>. 
		</p>
		<p>
			🥇 BTC/ETH and $TSLA prices are from <a href="https://google.com/finance">Google Finance</a>.
		</p>
		<p>
			🛒 CPI is the consumer price index, a basket of goods (like milk, bread, meat etc.) that's commonly used as the official inflation number. It's heavily criticized though for underreporting actual inflation.
		</p>
		<p>
			🍔 Big Mac measures the average price of a Big Mac at McDonald's in the United States and is famously used in <a href="https://www.economist.com/big-mac-index">the Economist's Big Mac Index</a> to measure inflation.
		</p>
		<p>
			🥩 Food represents the <a href="http://www.fao.org/worldfoodsituation/foodpricesindex/en/">Food Price Index (FPI)</a> by the United Nations, a measure of the international prices of a basket of 5 food commodities which are: sugar, cereals, vegetable oils, meat and dairy.
		</p>
		<p>
			🏡 Avg US Home is the <a href="https://www.nar.realtor/topics/existing-home-sales">median U.S. single-family home price</a>, with historical data from <a href="https://dqydj.com/historical-home-prices/">DQYDJ</a>.
		</p>
		<p>
			🥩 Food + Avg US Home is a combination I made of the (global) Food Price Index (FPI) and the median U.S. single-family home price. Used as a benchmark of how much it costs to live. Caveat here is that while the food prices are worldwide, the home price is U.S.
		</p>
		<p>
			👩‍💻 Pop. is the world population from <a href="https://data.worldbank.org/indicator/SP.POP.TOTL">World Bank</a>. Population is in billions.
		</p>
		<?/*<div class="tweet-wrapper">
			<blockquote class="twitter-tweet" data-theme="dark"><p lang="en" dir="ltr">Praet: As a central bank, we can create money to buy assets <a href="https://twitter.com/hashtag/AskECB?src=hash&amp;ref_src=twsrc%5Etfw">#AskECB</a> <a href="https://t.co/zTQuU4y1ch">https://t.co/zTQuU4y1ch</a></p>&mdash; European Central Bank (@ecb) <a href="https://twitter.com/ecb/status/1105494215381913601?ref_src=twsrc%5Etfw">March 12, 2019</a></blockquote> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
		</div>*/?>
		<p>
			🖨 As the Federal Reserve is printing money, it's expected that the real value of each US dollar decreases (called inflation). To estimate how much money is printed, I use <a href="https://fred.stlouisfed.org/series/M1">the Fed's M1, M2 and MB money supply data</a>. M1 is a measure of the money supply that includes physical currency and bank accounts. M2 is the same but also includes savings accounts (heavily simplified). The money base (MB) is the total amount of a currency that is either in general circulation in the hands of the public or in the commercial bank deposits held in the central bank's reserves. MB, M1 and M2 is in billions.
		</p>
		<p>
			❌ Caveats: this isn't financial advice and MB, M1 and M2 are limited measures of the money supply. That there's growing inflation due to printing of money I think we can all agree on though. I hope this site helps to visualize this a bit.
		</p>
		<p>
			🧨 <span class="quote">"The end game of rampant inflation is always war and/or revolution. Show me a regime change, and I will show you inflation. When you work your ass off only to stand still or get poorer, any “ism” that promises affordable food and shelter for the unwashed masses will reign supreme. If you are starving to death, nothing else matters except feeding your family. The symptoms of inflation are populism, social strife, food riots, high and rising financial asset prices, and income inequality. (..) Invest wisely and you can maintain or increase your standard of life against the rising fiat cost of energy. Invest poorly and the road to serfdom is real. You will find yourself working harder for a declining standard of living, and your fiat earnings and assets will not be able to keep up with the rising fiat cost of energy."</span> &mdash; <a href="https://blog.bitmex.com/pumping-iron/">Arthur Hayes</a>
		</p>
		<?/*<p>
			Then again the Fed doesn't agree:
		</p>
		<div class="youtube-wrapper">
			<iframe width="274" height="250" src="https://www.youtube.com/embed/SGNyCOlIEHY" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
		</div>*/?>
		<p>
			💬 <a href="https://news.ycombinator.com/item?id=26128388">Hacker News</a> has opinions about this site
		</p>
		<p>
			✨ Last updated: <?=date('Y-m-d',filemtime('index.php'))?>. 
		</p>
		<p>
			🧠 The database behind this is an open <a href="https://docs.google.com/spreadsheets/d/1xJGrHWj6uO6ykFPvht-RBG5qlLeO0axraxUJ9UzOhFo/edit?usp=sharing">Google Sheet</a> you can view. If you see any problems/bugs/errors with it, please let me know on Twitter below!
		</p>
		<p>
			👨‍🎨 Made by <a href="https://twitter.com/levelsio">@levelsio</a> (if you like it, tweet me a fun msg 😊). Inspired by <a href="https://stonksinbtc.xyz?ref=inflationchart.com">Stonks in BTC</a> by <a href="https://twitter.com/dannyaziz97">Danny Aziz</a>, and <a href="https://cryptowat.ch">Cryptowatch</a>'s layout.
			
		</p>
	</div>
</div>
</html><?

function sanitizeOutput($buffer) {
	$search = array(
		'/\>[\S ]+/s',	
		'/[\S ]+\</s',	
		'/(\s)+/s'		
	);
	$replace = array(
		'>',
		'<',
		'\\1'
	);
	$buffer = preg_replace($search, $replace, $buffer);
	return $buffer;
}
function curl_get_contents ($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
}
function sendToAdminTelegram($message) {
	global $config;
	$telegram_bot_token=$config['telegramAdminChat']['bot_token'];
	$telegram_chat_id=$config['telegramAdminChat']['chat_id'];
	// file_get_contents('https://api.telegram.org/bot'.$telegram_bot_token.'/sendMessage?chat_id='.$telegram_chat_id.'&parse_mode=markdown&disable_web_page_preview=true&text='.urlencode($message).'&disable_web_page_preview=true');
	// use shell exec to do it async and not slow down entire site
	$text=$message;
	// shell_exec('curl '.escapeshellarg('https://api.telegram.org/bot'.$telegram_bot_token.'/sendMessage?chat_id='.$telegram_chat_id.'&text='.urlencode($text).'&parse_mode=markdown&disable_web_page_preview=true').' > /dev/null 1>/dev/null &');
	shell_exec('curl '.escapeshellarg('https://api.telegram.org/bot'.$telegram_bot_token.'/sendMessage?chat_id='.$telegram_chat_id.'&text='.urlencode($text).'&parse_mode=markdown&disable_web_page_preview=true').' > /dev/null 1>/dev/null &');
}

function timeAgoLong($ptime) {
  
  	if($ptime>time()) {
		// in future, so reverse it
	    $etime = $ptime - time();
	}
	else {
	    $etime = time() - $ptime;
	}

    if ($etime < 1)
    {
        return 'one day';
    }

    $a = array( 12 * 30 * 24 * 60 * 60  =>  'year',
                30 * 24 * 60 * 60       =>  'month',
                24 * 60 * 60            =>  'day',
                60 * 60                 =>  'hour',
                60						=>  'minute',
                1						=>  'second'
                );

    foreach ($a as $secs => $str)
    {
        $d = $etime / $secs;
        if ($d >= 1)
        {
            $r = floor($d);
            return $r . ' ' . $str . ($r > 1 ? 's' : '');
        }
    }
}
?>
