<?php
require_once "vendor/autoload.php";
require_once "translit.php"; // функция транслита текста



$token = "ВАШ_токен_из_телеграма";  // Тут токен, полученный в телеграмме.


$bot = new \TelegramBot\Api\Client($token);


if(!file_exists("register.log")){ 
	/**
	 * файл register.log будет создаваться при инициализации webhook. 
	 * если этого файла нет, значит что-то не так
	 */
	 
	
	$page_url = "Адрес_вашего_сервера"; // URl страницы, где лежит бот
	$result = $bot->setWebhook($page_url); // Инициализируем вебхук
	if($result){
		file_put_contents("register.log",time()); // создаем файл и кидаем туда дату
	}
}



$bot->command('start', function ($message) use ($bot) {
	$answer = "Приветик, назови свой город!";
	$bot->sendMessage($message->getChat()->getId(), $answer);
});


$bot->on(function($Update) use ($bot){
	$message = $Update->getMessage();
	$mestext = trim(strip_tags(translit($message->getText())));
	
	
	$options = [
	'http' => [
		'method' => 'GET',
		'header' => 'Content-type: text/html; charset=utf-8'
	]

];

	/**
	 * Далее с помощью яндекс геокода определяем координаты введенного города, предварительно обработав запрос(переводим в транслит и т.д.)
	 */


$urlCity = "https://geocode-maps.yandex.ru/1.x/?apikey=api_ключ&format=json&geocode=".$mestext; // Тут наш api ключ из яндекс геолокатор
$context2 = stream_context_create($options);
$jsonCity = file_get_contents($urlCity, false, $context2);
$jsonCityDecode = json_decode($jsonCity);

$koords = $jsonCityDecode->response->GeoObjectCollection->featureMember[0]->GeoObject->Point->pos;


list($lon, $lat) = explode (' ', $koords); // Координаты приходят в виде строки с пробелом, делим ее и кидаем по переменным




$opts = [
  'http' => [
    'method' => "GET",
    'header' => "X-Yandex-API-Key:api_ключ" // тут мы в заголовке отправляем api ключ погоды
  ]
];

	/*
	 * Далее отправляем гет запрос с нашими координатами и получаем ответ в виде json, которую разюиваем и обращаемся к объектам.
	 */

$url = "https://api.weather.yandex.ru/v1/forecast?lat=$lat&lon=$lon&limit=1&hours=false&extra=false";
$context = stream_context_create($opts);
$contents = file_get_contents($url, false, $context);
$weather = json_decode($contents);


$temp = $weather->fact->temp;
$humidity = $weather->fact->humidity;
$speed = $weather->fact->wind_speed;
$pressure = $weather->fact->pressure_mm;
$condition = $weather->fact->condition;
	switch ($condition) {
		case 'clear' : $cond = 'Ясно';break;
		case 'partly-cloudy' : $cond = 'Малооблачно';break;
		case 'cloudy' : $cond = 'Малооблачно с прояснениями';break;
		case 'overcast' : $cond = 'Пасмурно';break;
		case 'partly-cloudy-and-light-rain' : $cond = 'Небольшой дождь';break;
		case 'partly-cloudy-and-rain' : $cond = 'Сильный дождь';break;
		case 'overcast-and-rain' : $cond = 'Сильный дождь, гроза';break;
		case 'overcast-thunderstorms-with-rain' : $cond = 'Небольшой дождь';break;
		case 'cloudy-and-light-rain' : $cond = 'Пасмурно и небольшой дождь';break;
		case 'overcast-and-light-rain' : $cond = 'Сильный дождь';break;
		case 'cloudy-and-rain' : $cond = 'Дождь';break;
		case 'overcast-and-wet-snow' : $cond = 'Дождь со снегом';break;
		case 'partly-cloudy-and-light-snow' : $cond = 'Небольшой снег';break;
		case 'partly-cloudy-and-snow' : $cond = 'Снег';break;
		case 'overcast-and-snow' : $cond = 'Снегопад';break;
		case 'cloudy-and-light-snow' : $cond = 'Небольшой снег';break;
		case 'overcast-and-light-snow' : $cond = 'Небольшой снег';break;
		case 'cloudy-and-snow' : $cond = 'Снег';break;
		
	}

$feels_like = $weather->fact->feels_like;




	
	$cid = $message->getChat()->getId();
	
	if(is_string($koords)){ // Если строка, значит город был введен верно, иначе отправляем на else
		$bot->sendMessage($message->getChat()->getId(), "Температура сейчас: ".$temp);
		$bot->sendMessage($message->getChat()->getId(), "Ощущается как: ".$feels_like);
		$bot->sendMessage($message->getChat()->getId(), "Ветер: ".$speed." м/с");
		$bot->sendMessage($message->getChat()->getId(), "Влажность: ".$humidity." %");
		$bot->sendMessage($message->getChat()->getId(), "Давление: ".$pressure. " мм рт. ст");
		$bot->sendMessage($message->getChat()->getId(), "Сейчас на улице ".$cond);
		if ($temp<0) {
			$bot->sendMessage($message->getChat()->getId(), "Сегодня прохладно, оденься теплее!");
		}
		if ($speed > 14) {
			$bot->sendMessage($message->getChat()->getId(), "Сегодня сильный ветер, оденься теплее!");
		}
		
	}
	else
	{$bot->sendMessage($message->getChat()->getId(), "Не знаю такой город, попробуй другой");}
}, function($message) use ($bot){
	return true; // когда тут true - команда проходит
});


	

$bot->run();