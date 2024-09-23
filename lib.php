<?php
// ----------------------------------- ОГОЛОШЕННЯ ФУНКЦІЙ ----------------------------------------
function MessageToBot($bot_token, $tg_user, $tg_zmist){
	$params = array(
		'chat_id' => $tg_user,
		'text' => $tg_zmist,
		'parse_mode' => 'HTML',
	);
	$curl = curl_init();

	curl_setopt($curl, CURLOPT_URL, 'https://api.telegram.org/bot' . $bot_token . '/sendMessage');
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

	// Приглушує вивід у браузер
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

	// Наступний рядок виконує запит і по замовчуванню виводить повідомлення в браузер.
	$jsonContent = curl_exec($curl); // Запит до API

	$data = json_decode($jsonContent, true);

	if($data['ok'] == '1') {
		return 'OK';
	} else {
		return 'Помилка від Телеграма';
	};
	curl_close($curl);
}
// ------------------------------------------------------------------------------------------------
function appendToFile($filename, $text) {
	// Відкриваємо файл для запису (якщо файл не існує, він буде створений)
	$file = fopen($filename, "a");
	if ($file === false) {
			// Обробка помилки, якщо не вдалося відкрити файл
			return false;
	}
	// Записуємо текст у файл
	$result = fwrite($file, $text. PHP_EOL);
	if ($result === false) {
			// Обробка помилки під час запису
			fclose($file); // Закриваємо файл
			return false;
	}
	// Закриваємо файл
	fclose($file);
	return true;
}
// ------------------------------------------------------------------------------------------------
function SendHookTelegram(){
	// Вставте Ваш токен бота та URL вебхука
	$botToken = "YOUR_BOT_TOKEN";
	$webhookUrl = "https://YOUR_ADRESS /filename.php"; // URL до вашого PHP скрипта, який обробляє запити від Telegram

	// URL для встановлення вебхука
	$setWebhookUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);

	// Налаштування контексту для HTTP запиту
	$options = [
	    'http' => [
	        'method'  => 'GET',
	        'header'  => "Content-Type: application/json\r\n",
	    ]
	];

	// Створення контексту потоку
	$context = stream_context_create($options);

	// Виконання запиту до Telegram API
	$response = file_get_contents($setWebhookUrl, false, $context);

	if ($response === FALSE) {
	    echo 'Помилка при встановленні вебхука.';
	} else {
	    echo 'Відповідь Telegram: ' . $response;
	}
};
// ------------------------------------------------------------------------------------------------

function sendCurlRequest($url, $method = 'GET', $data = []) {
	$curl = curl_init();
	curl_setopt_array($curl, [
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
	]);
	if (!empty($data)) {
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
	}
	$response = curl_exec($curl);
	if (curl_errno($curl)) {
			appendToFile("./data.txt", $currentDate." sendCurlRequest -> Помилка при запиті: ".curl_error($curl));
			curl_close($curl);
			return false;
	}
	curl_close($curl);
	return json_decode($response, true);
}
// ------------------------------------------------------------------------------------------------
function getBoardIdByName($boardName) {
	$url = "https://api.trello.com/1/members/me/boards?key=" . TRELLO_API_KEY . "&token=" . TRELLO_TOKEN;
	$response = sendCurlRequest($url);
	foreach ($response as $board) {
		if ($board['name'] === $boardName) {
			return $board['id'];
		}
	}
	return false;
}
// ------------------------------------------------------------------------------------------------
function MakeNewLists($pdo, $apiKey, $token, $chat_id, $namePM, $listName){
	$boardId = getBoardIdByName('NAME_YOUR_BOARD');
	$url = "https://api.trello.com/1/lists";

	$listName = trim($listName);
	// Дані для POST-запиту
	$data = [
			'name' => $listName,
			'idBoard' => $boardId,
			'key' => $apiKey,
			'token' => $token
	];

	// Ініціалізація cURL
	$curl = curl_init();
	curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
			CURLOPT_POSTFIELDS => http_build_query($data),
	]);
	
	// Виконання запиту
	$response = curl_exec($curl);
	
	// Перевірка на помилки під час виконання запиту
	if (curl_errno($curl)) {
			appendToFile("./data.txt", $currentDate."Помилка при запиті:".curl_error($curl));
			curl_close($curl);
			exit();
	}
	
	// Закриття cURL
	curl_close($curl);

	// Виведення необробленої відповіді для дебагу
	appendToFile("./data.txt", $currentDate. " необроблена відповідь від Trello API: \n$response\n\n");

	// Перетворення відповіді в масив
	$result = json_decode($response, true);
	
	// Перевірка на помилки декодування JSON
	if (json_last_error() !== JSON_ERROR_NONE) {
		appendToFile("./data.txt", $currentDate.' Помилка декодування JSON: ' . json_last_error_msg());
		exit('Помилка декодування JSON: ' . json_last_error_msg());
	}
	
	// Виведення результату
	if (isset($result['id'])) {
		appendToFile("./data.txt", $currentDate." Список '{$listName}' успішно створений з ID: " . $result['id']);
		$protocol = " успішно створений список '{$listName}' з ID:".$result['id']."";
	} else {
		appendToFile("./data.txt", $currentDate." Не вдалося створити список. Перевірте параметри.");
		$protocol =  "не вдалося створити список. Перевірте параметри."."";
	}

	$stmt = $pdo->prepare("INSERT INTO botusers (chat_id, username, protocol) VALUES (:chat_id, :username, :protocol)");
	$stmt->execute([
		'chat_id' => $chat_id,
		'username' => $namePM,
		'protocol' => $protocol
	]);

	$answer = "<b>$namePM</b>, за Вашим бажанням та за допомогою API на нашій дошці у Trello".$protocol."\n\n";
	return $answer;
};
// ------------------------------------------------------------------------------------------------
function CloseLists($namePM, $listNames) {
	$boardId = getBoardIdByName('NAME_YOUR_BOARD');
	$url = "https://api.trello.com/1/boards/{$boardId}/lists?key=" . TRELLO_API_KEY . "&token=" . TRELLO_TOKEN;
	$lists = sendCurlRequest($url);
	foreach ($lists as $list) {
		if ( trim($list['name']) ==  trim($listNames)	) {
			$listId = $list['id'];
			$deleteUrl = "https://api.trello.com/1/lists/{$listId}/closed?key=" . TRELLO_API_KEY . "&token=" . TRELLO_TOKEN;
			sendCurlRequest($deleteUrl, 'PUT', ['value' => true]);
			$answer = "<b>$namePM</b>, за Вашим бажанням та за допомогою API на нашій дошці у Trello список з назвою ".$list['name']." успішно закритий.\n\n";
		}
	}
	return $answer;
}
// ------------------------------------------------------------------------------------------------
function getWorkspaceIdByName($workspaceName) {
	$url = "https://api.trello.com/1/members/me/organizations?key=" . TRELLO_API_KEY . "&token=" . TRELLO_TOKEN;

	// Виконання запиту для отримання організацій користувача
	$response = sendCurlRequest($url);

	// Перевірка отриманої відповіді та пошук потрібної робочої області за назвою
	foreach ($response as $workspace) {
			if ($workspace['displayName'] === $workspaceName) {
					return $workspace['id'];
			}
	}
	// Якщо робоча область не знайдена
	return false;
}
// -------------------------------------------------------------------------------------------------
function inviteMemberToWorkspace($workspaceId, $email, $namePM) {
	$url = "https://api.trello.com/1/organizations/{$workspaceId}/members?key=" . TRELLO_API_KEY . "&token=" . TRELLO_TOKEN;
	// Дані для запиту
	$data = [
			'email' => $email,           // Email нового учасника
			'type' => 'normal',          // Тип учасника (normal - звичайний користувач)
	];
	// Виконання запиту для запрошення учасника
	$response = sendCurlRequest($url, 'PUT', $data);
	// Перевірка відповіді
	if (isset($response['id'])) {
			$answer = "\n\n<b>$namePM</b>, за Вашим бажанням та за допомогою API на нашій дошці у Trello учасника з email '{$email}' успішно запрошено до робочої області.\n\n";
	} else {
			$answer = "\n\n<b>$namePM</b>, за Вашим бажанням та за допомогою API на нашій дошці у Trello не вдалося запросити учасника з email '{$email}'. Необхідно перевірити параметри та права доступу.\n\n";
	}
	return $answer;
}
// ------------------------------------------------------------------------------------------------
function SendHookTrello(){
	// Введіть ваш API ключ і токен Trello
	$apiKey = 'YOUR_API_KEY';
	$token = 'YOUR_TOKEN_TRELLO';
	
	// ID дошки, для якої створюємо Webhook
	$boardId = getBoardIdByName('NAME_YOUR_BOARD');

	
	// URL вашого PHP-скрипту, який буде приймати Webhook
	$callbackUrl = 'https://YOUR_ADRESS/webhook.php'; // Замініть на ваш актуальний URL
	
	// Назва Webhook
	$description = 'Trello Webhook for card movement';
	
	// Дані для запиту на створення Webhook
	$data = [
			'description' => $description,
			'callbackURL' => $callbackUrl,
			'idModel' => $boardId,
			'key' => $apiKey,
			'token' => $token,
	];
	
	// Ініціалізація cURL
	$curl = curl_init();
	curl_setopt_array($curl, [
			CURLOPT_URL => 'https://api.trello.com/1/webhooks',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
	]);
	
	// Виконання запиту
	$response = curl_exec($curl);
	
	// Перевірка на помилки
	if (curl_errno($curl)) {
			echo 'Помилка при запиті: ' . curl_error($curl);
			curl_close($curl);
			exit();
	}
	
	// Закриття з'єднання
	curl_close($curl);
	
	// Виведення відповіді
	$result = json_decode($response, true);
	if (isset($result['id'])) {
			echo "Webhook успішно створено!";
	} else {
			echo "Не вдалося створити Webhook. Перевірте параметри.";
	}


}
// ------------------------------------------------------------------------------------------------
?>