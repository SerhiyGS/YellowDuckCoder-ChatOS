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
	// Відкриття файлу для запису (якщо файл не існує, він буде створений)
	$file = fopen($filename, "a");
	if ($file === false) {
		// Обробка помилки, якщо не вдалося відкрити файл
		return false;
	}
	// Запис тексту у файл
	$result = fwrite($file, $text. PHP_EOL);
	if ($result === false) {
		// Обробка помилки під час запису
		fclose($file); // Закриття файлу
		return false;
	}
	// Закриття файлу
	fclose($file);
	return true;
}
// ------------------------------------------------------------------------------------------------
function sendCurlRequest($url, $method = 'GET', $data = []) {
	$ch = curl_init();
	if ($method === 'PUT') {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	} elseif ($method === 'POST') {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	curl_close($ch);
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
	$boardId = getBoardIdByName(TRELLO_BOARD_NAME);
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
	$answer = "<b>$namePM</b>, за Вашим бажанням та за допомогою API на нашій дошці у Trello".$protocol."\n\n";
	return $answer;
};
// ------------------------------------------------------------------------------------------------
function CloseLists($namePM, $listNames) {
	$boardId = getBoardIdByName(TRELLO_BOARD_NAME);
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
	$memberResult = [];
  // URL для отримання членів робочої області
  $membersUrl = "https://api.trello.com/1/organizations/{$workspaceId}/members?key=" . TRELLO_API_KEY . "&token=" . TRELLO_TOKEN;
  // Отримання всіх учасників до запрошення нового учасника
  $membersBefore = sendCurlRequest($membersUrl, 'GET');
  // Виконання запит для запрошення нового учасника
  $url = "https://api.trello.com/1/organizations/{$workspaceId}/members?key=" . TRELLO_API_KEY . "&token=" . TRELLO_TOKEN;
  // Дані для запиту (запрошення учасника)
  $data = [
    'email' => $email,   // Email нового учасника
    'type' => 'normal',  // Тип учасника (normal - звичайний користувач)
  ];
  // Виконання запиту для запрошення учасника
  $response = sendCurlRequest($url, 'PUT', $data);
  // Перевірка відповіді на успішне запрошення
  if (isset($response['id'])) {
		// Отримання всіх учасників після запрошення нового учасника
		$membersAfter = sendCurlRequest($membersUrl, 'GET');
		// Створення допоміжного масиву для швидкого пошуку по ID у першому масиві
		$membersBeforeId = array_column($membersBefore, 'id');
		// Перебирання другого масиву
		foreach ($membersAfter as $mA) {
			// Якщо ID елемента з другого масиву не існує в першому масиві
			if (!in_array($mA['id'], $membersBeforeId)) {
				// Додавання цього елементу до третього масиву
				$memberResult[] = $mA;
			}
		}
		$answer = "\n\n<b>$namePM</b>, учасника з email '{$email}' успішно запрошено до робочої області.\n";
  } else {
    $answer = "\n\n<b>$namePM</b>, не вдалося запросити учасника з email '{$email}'.\n";
  }
	$memberResult[0]['answer'] = $answer;
	return $memberResult;
}
// ------------------------------------------------------------------------------------------------
?>
