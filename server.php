<?php
$currentDate = date('Y-m-d H:i:s');

$host = 'localhost';
$db = 'YOUR_DATA';
$user = 'YOUR_DATA';
$pass = 'YOUR_DATA';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8";

try {
	$pdo = new PDO($dsn, $user, $pass);
} catch (PDOException $e) {
	die('Підключення до БД не вдалося: '.$e->getMessage());
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update || !isset($update['message'])) {
	exit;
}

define('TELEGRAM_BOT_ID', 'YOUR_DATA');
define('TELEGRAM_CHAT_ID', $update['message']['chat']['id']);
define('TELEGRAM_NAME_PM', $update['message']['from']['username']);

define('TRELLO_API_KEY', 'YOUR_DATA');
define('TRELLO_TOKEN', 'YOUR_DATA');

$apiKey = TRELLO_API_KEY;
$token = TRELLO_TOKEN;

$chatId = TELEGRAM_CHAT_ID;
$username = TELEGRAM_NAME_PM;

$text = $update['message']['text'];
$length_short = mb_strlen($text, 'UTF-8');
$message = "";

require 'lib.php';

// Отримання кількості завдань у роботі для кожного користувача
function getTasksInProgress($pdo) {
	global $apiKey;
	$stmt = $pdo->query("SELECT DISTINCT trello_username, trello_token FROM botusers WHERE trello_token IS NOT NULL");
	$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$message = "<b>Звіт про кількість завдань у роботі:</b>\n\n";
	foreach ($users as $user) {
			$trelloToken = $user['trello_token'];
			$trelloUsername = $user['trello_username'];

			$url = "https://api.trello.com/1/members/$trelloUsername/cards?filter=visible&key=$apiKey&token=$trelloToken";

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			curl_close($ch);

			$cards = json_decode($response, true);
			$tasksInProgress = 0;

			foreach ($cards as $card) {
					// Замініть ID_СПИСКУ_IN_PROGRESS на реальний ID списку у вашій дошці Trello
					if ($card['idList'] == 'ID_СПИСКУ_IN_PROGRESS') {  
							$tasksInProgress++;
					}
			}

			$message .= "<b>{$user['trello_username']}:</b> {$tasksInProgress} завдань у роботі\n";
	}

	return $message;
}

// -----------------------------------------------------------------------------------
if (substr($text, 0, 6) == '/start') {
	//appendToFile("./data.txt", $currentDate." ".TELEGRAM_CHAT_ID);
	$stmt = $pdo->prepare("INSERT INTO botusers (chat_id, username) VALUES (:chat_id, :username)");
	$stmt->execute([
		'chat_id' => TELEGRAM_CHAT_ID,
		'username' => TELEGRAM_NAME_PM
	]);
	$message = "Привіт <b>".TELEGRAM_NAME_PM."</b> \u{1F91D}\nБудь ласка, надішліть свій email для приєднання Вас до нашої дошки у Trello.\nПеред назвою електронної адреси встановіть <b>/send_email</b> та пробіл";

}
// ----------------------------------------------------------------------------------------
if (substr($text, 0, 11) == '/send_email') {
	$short_text = substr($text, 12, $length_short - 11);
	$audit = filter_var($short_text, FILTER_VALIDATE_EMAIL);
	if ($audit) {// Перевірка, чи міститься в змінній коректна електронна адреса
		$message = "<b>".TELEGRAM_NAME_PM."</b>, дякуємо Вам за надіслану адресу електронної пошти <b>$audit</b>.\u{1F44D}\nЗараз Ви будете долучені до нашої дошки у Trello.\u{1F4AF}";
		$stmt = $pdo->prepare("INSERT INTO botusers (chat_id, username, email) VALUES (:chat_id, :username, :email)");
		$stmt->execute([
			'chat_id' => TELEGRAM_CHAT_ID,
			'username' => TELEGRAM_NAME_PM,
			'email' => $audit
		]);

	// ----------------------------------------
		// Приклад виклику функції
		$workspaceName = 'Робоча область Trello'; // Введіть назву робочої області, до якої хочете запросити учасника
		$workspaceId = getWorkspaceIdByName($workspaceName);

		if ($workspaceId) {
	    $email = $audit;  // Введіть email користувача, якого потрібно запросити
	    $message .= inviteMemberToWorkspace($workspaceId, $email, TELEGRAM_NAME_PM);
		} else {
			$message .= "Робоча область з назвою '{$workspaceName}' не знайдена.\u{1F61E}\n";
		}

	//----------------------------------------

	} else {
		$message = "<b>".TELEGRAM_NAME_PM."</b>, на жаль, у повідомленні [<b>$short_text</b>], що Ви надіслали, не міститься коректної електронної адреси.\u{1F61E}";
	}
}
// ----------------------------------------------------------------------------------------
if (substr($text, 0, 10) == '/get_lists') {
	$apiKey = TRELLO_API_KEY;
	$token = TRELLO_TOKEN;

	$boardId = getBoardIdByName('NAME_YOUR_TRELLO_BOARD');

	// URL для отримання списків на дошці
	$url = "https://api.trello.com/1/boards/{$boardId}/lists?key={$apiKey}&token={$token}";

	// Ініціалізація cURL
	$curl = curl_init();
	curl_setopt_array($curl, [
	    CURLOPT_URL => $url,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
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
	$lists = json_decode($response, true);

	// Перевірка на помилки декодування JSON
	if (json_last_error() !== JSON_ERROR_NONE) {
		appendToFile("./data.txt", $currentDate.'Помилка декодування JSON: ' . json_last_error_msg());

	  exit('Помилка декодування JSON: ' . json_last_error_msg());

	}

	// Виведення списків на обраній дошці
	$all_lists = "\n\n\r";

	foreach ($lists as $list) {
		$all_lists .= "Назва списку: " . $list['name'] . "\n" . "ID списку: " . $list['id'] . "\n\n";

			appendToFile("./data.txt", $currentDate."Назва списку: " . $list['name'] . "\n");
			appendToFile("./data.txt", $currentDate."ID списку: " . $list['id'] . "\n\n");
	}


	$message = "<b>".TELEGRAM_NAME_PM."</b>, за Вашим бажанням та за допомогою API з дошки у Trello отримано списки:". $all_lists;

}
// ----------------------------------------------------------------------------------------
if (substr($text, 0, 10) == '/set_lists') {
	$all_list_name = substr($text, 11, $length_short-11);
	$arr_list_name = explode(";", $all_list_name);
	$count_list_name = count($arr_list_name);
	if(strlen($all_list_name) > 0 and $count_list_name > 0){
		foreach ($arr_list_name as $value) {
			$message .= MakeNewLists($pdo, TRELLO_API_KEY, TRELLO_TOKEN, TELEGRAM_CHAT_ID, TELEGRAM_NAME_PM, $value). ' ';
		}
	}
}
// -----------------------------------------------------------------------------------------
if (substr($text, 0, 12) == '/close_lists') {
	$all_list_name = substr($text, 13, $length_short-13);
	$arr_list_name = explode(";", $all_list_name);
	$count_list_name = count($arr_list_name);
	if(strlen($all_list_name) > 0 and $count_list_name > 0){
		foreach ($arr_list_name as $value) {
			$message .= CloseLists(TELEGRAM_NAME_PM, $value). ' ';
		}
	}
}
// ---------------------------------------
// Об'єднання користувача через команду /common_user Необхідно вказати /common_user USERNAME_TRELLO;YOUR_TRELLO_TOKEN
if (substr($text, 0, 12) == '/common_user') {
	$all_list_name = substr($text, 13, $length_short-13);

	$arr_list_name = explode(";", $all_list_name);

	$trello_username = trim($arr_list_name[0]);
	$trelloToken =  trim($arr_list_name[1]);

	$stmt = $pdo->prepare("SELECT * FROM botusers WHERE trello_username = :trello_username AND trello_token IS NOT NULL");
	$stmt->execute([
		'trello_username' => $trello_username
	]);

	$user = $stmt->fetch();

	if ($user) {
		$message = "<b>".TELEGRAM_NAME_PM."</b>, Ви вже об'єднали свій обліковий запис Telegram з Trello.";
	} else {

		$stmt = $pdo->prepare("UPDATE botusers SET trello_username = :trello_username, trello_token = :trello_token WHERE chat_id = :chat_id");

		$stmt->execute([
			'trello_username' => $trello_username,
			'trello_token' => $trelloToken,
			'chat_id' => TELEGRAM_CHAT_ID
		]);
	
		$message = "<b>".TELEGRAM_NAME_PM."</b>, Ваш обліковий запис Trello успішно об'єднаний з обліковим записом Telegram!";
	}
}


// ---------------------------------------
// Команда для формування звіту /zvit
if ($text === '/zvit') {
	$message = getTasksInProgress($pdo); 	
}
// ---------------------------------------

// Перевірка наявності термінових повідомлень з Trello
if (file_exists('./trello_log.txt')) {
    $content = file_get_contents('./trello_log.txt');
    if (!empty($content)) {
				$message .= "\n\n"."<b>УВАГА!!!\n\nТермінове повідомлення з Trello:</b>\n\n".$content;
        unlink('./trello_log.txt');
    }
}
// ---------------------------------------
// Відправка повідомлення користувачу
if($message != ''){
	$response = MessageToBot(TELEGRAM_BOT_ID, TELEGRAM_CHAT_ID, $message);
}
// ---------------------------------------
?>
