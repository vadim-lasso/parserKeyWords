<?
/**
 * Parser keywords
 * @author vadim_lasso<vadim.lasso@gmail.com>
 * February 2015
 */
header('Content-Type: text/html; charset=UTF-8');

function routingActions($action) 
{
	return ((function_exists($action))) ? $action() : 'Действие не найдено';
}

function outputResponse($data) 
{
	header('Content-Type: application/json');
	if(is_array($data)) 
		echo json_encode($data);
	else
		echo json_encode(array('status' => 'danger', 'message' => $data));
}

function saveToFile($path, $fileName, $data, $fileAppend = false) 
{
	$path = trimAbsolutePath($path);
	if (!is_dir($path))
		mkdir($path, 0700);
	return (file_put_contents($path.$fileName, $data."\r\n", ($fileAppend) ? FILE_APPEND : false)) ? true : false;
}

function createFile($path, $fileName) 
{
	$path = trimAbsolutePath($path);
	if (!is_dir($path))
		mkdir($path, 0700);
	$result = file_put_contents($path.$fileName, '');
	
	return ($result === false) ? false: true;
}

function clearFile($path, $fileName) 
{
	$path = trimAbsolutePath($path);
	file_put_contents($path.$fileName, '');
}

function trimAbsolutePath($path) 
{
	return preg_replace('#^/#', '', $path);
}

function addCslashesRegex($string) 
{
	$arAddCslashes = array('\\', '|', '^', '$', '.', '*', '+', '?', '{', '}', '[', ']', '(', ')', '"', '/');
	foreach ($arAddCslashes as $value) {
		$string = str_replace($value, '\\'.$value, $string);
	}
	return trim($string);
}

function trimInvalidChars($string) 
{
	return str_replace(array(">", "<", "*", "/", "\\", "|", "?", ":", ";", "+"), " ", $string);
}

function notInFile($path, $fileName, $string) 
{
	$path = trimAbsolutePath($path);
	return (!in_array($string, explode("\r\n", file_get_contents($path.$fileName)))) ? true : false;
}

function isValidKeywords($keywords, $keywordsPlural, $keywordsFound) 
{
	if (preg_match('/\s/', $keywords)) {
		$arKeywords = explode(' ', $keywords);
		$arKeywordsPlural = explode(' ', $keywordsPlural);
		foreach ($arKeywords as $key => $keywords) {
			$pattern = "/({$keywords}|{$arKeywordsPlural[$key]})(\s|\t|$)+/i";
			if (!preg_match($pattern, $keywordsFound)) {
				return false;
			}
		}
		return true;
	}
	
	$pattern = "/({$keywords}|{$keywordsPlural})(\s|\t|$)+/i";
	return (preg_match($pattern, $keywordsFound)) ? true : false;
}

function getURL($url, $timeout = 5, $maxRedirs = 1)
{
    $ch = curl_init();
	$header[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
	$header[] = "Connection: keep-alive";
	$header[] = "Keep-Alive: 300";
	$header[] = "Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3";
	$header[] = "Pragma: "; 
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.3; WOW64; rv:35.0) Gecko/20100101 Firefox/35.0");
    curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $content = curl_exec($ch);
    $response = curl_getinfo($ch);
    curl_close ($ch);	
    if (($response['http_code'] == 301 OR $response['http_code'] == 302) AND $maxRedirs)
        if ($headers = get_headers($response['url']))
            foreach($headers as $value)
                if (substr( strtolower($value), 0, 9 ) == "location:") 
                    return getURL(trim(substr($value, 9, strlen($value))), $timeout, --$maxRedirs);
	return ($content) ? $content : false;
}

function getKeywordsFromURL($url)
{
	preg_match('/(\?|\&)([^=]+)\=([^&]+)/i', $url, $matches);
	if (!isset($matches[2]) || !isset($matches[3]))
		return false;
	return array(
		'keywordsname' => trimInvalidChars($matches[2]),
		'keywords' => trimInvalidChars($matches[3])
	);
}

function makeValidAddress($url) 
{
	if (!preg_match('/^[-\.:\/\/a-z0-9]+\.[a-z]+\/?.*$/i', $url)) 
		return false;
	/*
	if (!preg_match('/(www\.)/i', $url)) {
		$url = preg_replace_callback(
			'/^(http[s]?:\/\/)?(.*)$/i', 
			function($matches) {
				return $matches[1] . "www." . $matches[2];
			}, 
			$url
		);
	}
	*/
	return $url;
}

function getListURL($url, $keywords, $keywordsPlural, $keywordsName, $fileName, $filePath)
{
	if (!$result = getURL($url)) 
		return array('status' => 'warning', 'message' => "Получена пустая страница по адресу <b>{$url}</b>");
	
	/* fix bag */
	saveToFile($filePath, "tmp_{$_REQUEST['keywords']}.data", str_replace('www.', '', $url), true);
	
	$arKeywordsCurRequest = getKeywordsFromURL($url);
	
	$patternfromLinks = "/href=\"(.*\?{$keywordsName}=.*)\"/iUs";
	if (!preg_match_all($patternfromLinks, $result, $matches))
		return array('status' => 'warning', 'message' => "Не найдено ни одного ключевого слова по адресу <b>{$url}</b>");

	$listURL = $matches[1];
	$validListURL = array();
	$keywordsCount = 0;
	foreach($listURL as $url) {
		$arKeywords = getKeywordsFromURL($url);
		$keywordsFound = $arKeywords['keywords'];
		
		if (!isValidKeywords($keywords, $keywordsPlural, $keywordsFound))
			continue;
		
		if (notInFile($filePath, "tmp_{$_REQUEST['keywords']}.data", $url))
			$validListURL[] = $url;

		if (notInFile($filePath, $fileName, $keywordsFound)) {
			$keywordsCount++;
			saveToFile($filePath, $fileName, $keywordsFound, true);
		}
	}
	if (count($validListURL))
		return array(
			'listurl' => $validListURL,
			'log' => array(
				'keywords' => $arKeywordsCurRequest['keywords'],
				'keywordscount' => $keywordsCount
			)
		);
	else
		return array('status' => 'warning', 'message' => "Все ключевые слова по адресу <b>{$url}</b> уже были сохранены или не подходят по начальному слову");
}

function _deleteFile() 
{
	$path = trimAbsolutePath($_REQUEST['path_created_files']);
	unlink($path."tmp_{$_REQUEST['keywords']}.data");
}

function _getFileName() 
{
	if (!$data = getKeywordsFromURL($_REQUEST['url']))
		return 'В адресе страницы не найдено ключевых слов для поиска';
	
	$extension = pathinfo($_REQUEST['file_name'], PATHINFO_EXTENSION);
	return array(
		'status' => 'data', 
		'filename' => $data['keywords'].'.'.$extension, 
		'keywordsname' => $data['keywordsname'],
		'keywords' => $data['keywords']
	);
}

function _getKeywords() 
{
	if (empty($_REQUEST['keywords_plural']))
		return 'Не указано слово во множественном числе';
	
	if (!$url = makeValidAddress($_REQUEST['url']))
		return array('status' => 'warning', 'message' => "Некорректный адрес страницы {$_REQUEST['url']}");
	
	if ($_REQUEST['start'] == 'true') {
		if (!createFile($_REQUEST['path_created_files'], $_REQUEST['file_name']))
			return "Ошибка создания файла {$_REQUEST['file_name']}";
		createFile($_REQUEST['path_created_files'], "tmp_{$_REQUEST['keywords']}.data");
	}
	
	return getListURL(
		$url, 
		$_REQUEST['keywords'],
		$_REQUEST['keywords_plural'],
		$_REQUEST['keywords_name'],
		$_REQUEST['file_name'],
		$_REQUEST['path_created_files']
	);
}
?><? if (isset($_REQUEST['action'])): ?><? outputResponse(routingActions($_REQUEST['action'])); ?>
<? else: ?>
<? ob_start() ?>
<!DOCTYPE>
<html lang="en">
    <head>
		<title>Parser keywords</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
		<style type="text/css">
			.log { overflow-y: auto;height: 100%; }
			.log a { font-weight: bold;text-decoration: underline; }
			.page-header { height: 350px;}
			.tab-content { margin-top: 20px; }
			.alert { display: none;font-size: 12px;padding: 2px 5px;margin-bottom: 5px;margin-right: 5px; }
			.pull-right { padding-right: 15px; }
			.load { display: none; }
			.btn-default { display: none; }
			.control-label { font-size: 13px; }
			.keywords { display: none; }
		</style>
    </head>
<body>
	<div class="page-header">
		<div class="container">
			<div class="row">
				<div class="col-md-8">
					<ul class="nav nav-tabs">
						<li data-toggle="tab" class="active"><a href="#get-content">Парсер ключевых слов</a></li>
					</ul>
					<div class="tab-content">
						<div id="get-content" class="tab-pane fade in active">
							<form id="form-get-content" class="form-horizontal">
								<div class="form-group form-group-sm">
									<label class="col-sm-3 control-label">Страница с запросом: </label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="url" value="" />
									</div>
								</div>
								<div class="form-group form-group-sm keywords">
									<label class="col-sm-3 control-label">Начальное слово: </label>
									<div class="col-sm-3">
										<input type="text" class="form-control" name="keywords" value="" disabled />
									</div>					
									<label class="col-sm-3 control-label">Во множественном числе: </label>
									<div class="col-sm-3">
										<input type="text" class="form-control" name="keywords_plural" value="" />
									</div>
								</div>								
								<div class="form-group form-group-sm">
									<label class="col-sm-3 control-label">Имя файла для результатов: </label>
									<div class="col-sm-2">
										<input type="text" class="form-control" name="file_name" value="keywords.txt" />
									</div>					
									<label class="col-sm-2 control-label">Путь для сохранения файлов: </label>
									<div class="col-sm-2">
										<input type="text" class="form-control" name="path_created_files" value="files/" />
									</div>
									<label class="col-sm-2 control-label">Задержка (сек.): </label>
									<div class="col-sm-1">
										<input type="text" class="form-control" name="delay" value="0">
									</div>
								</div>					
								<div class="form-group form-group-sm">
									<div class="pull-right">
										<input type="hidden" name="keywords_name" value="" />
										<input type="button" class="btn btn-default" name="stop" data-stop="N" value="Закончить" />
										<input type="button" class="btn btn-success" name="start" value="Начать" />
									</div>
								</div>
							</form>
							<div class="load pull-right">
								Получили <span class="badge">0</span> уникальных ключевиков
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="log"></div>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
	<script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
    <script language="JavaScript">
		var stackURL = [];
        $(function() {
			
			$(".nav a").click(function(e){
				e.preventDefault();
				$(this).tab('show');
			});

			$('input[name="url"]').on('keyup paste', function() {
				var self = this;
				setTimeout(function(e) { // fix get paste value
					getFileName().done(function(data) {
						if (data.status == 'danger') 
							setMessageToLog(data);
						else {
							var message = 'Поиск начнется по keywords: <b>' + data.keywords + '</b>'
							if ($('.log').children().last().html() != message) // fix duplicate message
								setMessageToLog({
									status: 'info',
									message: message
								});
						}
						$('.keywords').show();
						$('input[name="file_name"]').val(data.filename);
						$('input[name="keywords"]').val(data.keywords);
						$('input[name="keywords_plural"]').val(data.keywords + 's');
						$('input[name="keywords_name"]').val(data.keywordsname);
					});
				}, 0);
			});	
			
            $('#form-get-content input[name="stop"]').on('click', function() {
				$(this).data('stop', 'Y');
			});
			
            $('#form-get-content input[name="start"]').on('click', function() {
				initEach();
				eachKeywords();
			});
        });
			
		function initEach() {
			$('.badge').text('0');
			$('.load').show();
			$('.btn-default').show();
			$('.btn-default').data('stop', 'N');
		}
			
		function getFileName() {
			var data = $('#form-get-content').serialize() + '&' 
					   + $.param({action: '_getFileName'});
			return sendAction(data);
		}
		
		function deleteFile() {
			var data = $('#form-get-content').serialize() + '&' 
					   + $.param({action: '_deleteFile'});
			return sendAction(data);
		}		
			
		function eachKeywords() {
			var delay = $('input[name="delay"]').val() * 1000;
			setMessageToLog({
				status: 'info',
				message: 'Начинаем получение ключевых слов'
			});
			
			$('input[name="url"]').attr('disabled', 'disabled'); // fix not serialize url
			$('input[name="keywords"]').removeAttr('disabled'); // fix serialize disabled
			var data = $('#form-get-content').serialize() + '&' 
					   + $.param({action: '_getKeywords'});
			$('input[name="keywords"]').attr('disabled', 'disabled'); // fix serialize disable
			
			stackURL.push($('input[name="url"]').val());
			sendChainUrlOfAction(stackURL, data, delay, updateProgressGetContent, 0);
		}
		
		function sendChainUrlOfAction(chain, data, delay, handler, depthLevel) {
			
			console.log(chain); 
			
			if ($('input[name="stop"]').data('stop') == 'Y')
				chain.length = 0; // stop
			if (chain.length) {
				var start = false;
				if (!depthLevel)
					start = true;
				
				var url = chain.shift();
				var dataAll = data + '&' 
							+ $.param({ url: url }) + '&'
							+ $.param({ start: start });
				$.ajax({
					type: "POST",
					data: dataAll,
					dataType: "json",
					success: function (response) {
						
						if (response.status == 'warning') 
							setMessageToLog(response);
						else {
							handler(url, response.log);
							var merge = $.merge(chain, response.listurl);
							chain = merge.filter(function(itm, i, a){
								return i == a.indexOf(itm);
							});
						}
						
						setTimeout(function() {
							depthLevel++;
							sendChainUrlOfAction(chain, data, delay, handler, depthLevel)
						}, delay);
					}
				});
			} else {
				var pathCreatedFiles = $('input[name="path_created_files"]').val();
				var fileName = $('input[name="file_name"]').val();
				setMessageToLog({
					status: 'info',
					message: 'Загрузка ключевых слов в файл <a target="_blank" href="' + pathCreatedFiles + fileName + '">' + fileName + '</a>' +
							 '&nbsp;закончена.'
				});
				stackURL.length = 0;
				$('input[name="url"]').removeAttr('disabled');
				$('.btn-default').hide();
				$('#form-get-content input[name="start"]').val('Повторить');
				
				$('input[name="keywords"]').removeAttr('disabled');
				deleteFile().done(function() {
					$('input[name="keywords"]').attr('disabled', 'disabled');
				});
			}
		}
		
		function updateProgressGetContent(url, data) {
			updateCountKeywords(data.keywordscount);
			setMessageToLog({
				status: 'success',
				message: 'По запросу <b>' + data.keywords + '</b> получили <b>' + data.keywordscount + '</b> подходящих ключевиков'
			});
		}
		
		function updateCountKeywords(number) {
			var count = parseInt($('.badge').text());
			$('.badge').text(count + number);
		}
		
		function sendAction(data) {
			return $.ajax({
				type: "POST",
				data: data,
				dataType: "json"
			});
		}		
		
		function setMessageToLog(data) {
			var logSelector = '.log';
			var logContainer = $(logSelector);
			var htmlMessage = '<div class="alert alert-' + data.status + '" role="alert">' + data.message + '</div>';
			$(htmlMessage).appendTo(logSelector).fadeIn(100);
			logContainer[0].scrollTop = logContainer[0].scrollHeight;
			if (data.status == 'danger')
				throw new Error('stop');
		}
		
		function escapeHtml(text) {
		  return text
			  .replace(/&/g, "&amp;")
			  .replace(/</g, "&lt;")
			  .replace(/>/g, "&gt;")
			  .replace(/"/g, "&quot;")
			  .replace(/'/g, "&#039;");
		}
    </script>
</body>
</html>
<? ob_end_flush(); ?>
<? endif; ?>