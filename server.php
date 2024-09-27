<?php
$currentDate = date('Y-m-d H:i:s');


$host = 'localhost';
$db = 'YOUR_NAME_DATABASE';
$user = 'YOUR_LOGIN';
$pass = 'YOUR_PASSWORD';

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


define('TELEGRAM_BOT_ID', 'YOUR_BOT_ID');
define('TELEGRAM_CHAT_ID', $update['message']['chat']['id']);
define('TELEGRAM_NAME_PM', $update['message']['from']['username']);

define('TRELLO_API_KEY', 'YOUR_TRELLO_API_KEY');
define('TRELLO_TOKEN', 'YOUR_TRELLO_TOKEN');
define('TRELLO_BOARD_NAME', 'YOUR_TRELLO_BOARD_NAME');
$workspaceName = 'TRELLO_WORKSPACE'; // Введіть назву робочої області, до якої хочете запросити учасника


$apiKey = TRELLO_API_KEY;
$token = TRELLO_TOKEN;
$chatId = TELEGRAM_CHAT_ID;
$username = TELEGRAM_NAME_PM;
$text = $update['message']['text'];
$length_short = strlen($text);
$message = "";
require 'lib.php';
// -----------------------------------------------------------------------------------
if (substr($text, 0, 6) == '/start') {
	// Підготовка SELECT-запиту для перевірки наявності користувача з таким username та email
	$stmt = $pdo->prepare("SELECT username, email FROM botusers WHERE username = :username");
	$stmt->execute([
		'username' => TELEGRAM_NAME_PM
	]);
	// Отримання результату у вигляді асоціативного масиву
	$user = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($user) {
	 if($user['email'] == ''){
		$message = "Привіт <b>".TELEGRAM_NAME_PM."</b>!\u{1F91D}\nВи вже відправляли команду <b>/start</b>, але ще не додали Ваш email для приєднання Вас до нашої дошки у Trello.\nБудь ласка, надішліть свій email для приєднання Вас до нашої дошки у Trello.\nПеред назвою електронної адреси встановіть <b>/send_email</b> та пробіл";
	 } else {
		$message = "Привіт <b>".TELEGRAM_NAME_PM."</b>!\u{1F91D}\nБудьте уважні!\nВи вже відправляли команду <b>/start</b>, та додали Ваш email для приєднання Вас до нашої дошки у Trello.";
	 }
	} else {
	  // Якщо запису з таким username та email немає, виконується вставка нового запису
		$stmt = $pdo->prepare("INSERT INTO botusers (chat_id, username) VALUES (:chat_id, :username)");
		$stmt->execute([
				'chat_id' => TELEGRAM_CHAT_ID,
				'username' => TELEGRAM_NAME_PM
		]);
		$message = "Привіт <b>".TELEGRAM_NAME_PM."</b> \u{1F91D}\nБудь ласка, надішліть свій email для приєднання Вас до нашої дошки у Trello.\nПеред назвою електронної адреси встановіть <b>/send_email</b> та пробіл";
	}
}
// ----------------------------------------------------------------------------------------
if (substr($text, 0, 11) == '/send_email') {
	$short_text = substr($text, 12, $length_short - 11);
	$audit = filter_var($short_text, FILTER_VALIDATE_EMAIL);
	if ($audit) {// Перевірка, чи міститься в змінній коректна електронна адреса
		$message = "<b>".TELEGRAM_NAME_PM."</b>, дякуємо Вам за надіслану адресу електронної пошти <b>$audit</b>.\u{1F44D}\nЗараз Ви будете долучені до нашої дошки у Trello.\u{1F4AF}";
		$stmt = $pdo->prepare("UPDATE botusers SET email = :email WHERE username = :username");
		$stmt->execute([
			'username' => TELEGRAM_NAME_PM,
			'email' => $audit
		]);
		$workspaceId = getWorkspaceIdByName($workspaceName);
		if ($workspaceId) {
	    $arrMemberResult = inviteMemberToWorkspace($workspaceId, $audit, TELEGRAM_NAME_PM);
			$stmt = $pdo->prepare("UPDATE botusers SET trello_id = :trello_id, trello_fullname = :trello_fullname, trello_username = :trello_username WHERE username = :username");
			$stmt->execute([
				'trello_id' => $arrMemberResult[0]['id'],
				'trello_fullname' => $arrMemberResult[0]['fullName'],
				'trello_username' => $arrMemberResult[0]['username'],
				'username' => TELEGRAM_NAME_PM
			]);
			$message .= $arrMemberResult[0]['answer'];
		} else {
			$message .= "Робоча область з назвою '{$workspaceName}' не знайдена.\u{1F61E}\n";
		}
	} else {
		$message = "<b>".TELEGRAM_NAME_PM."</b>, на жаль, у повідомленні [<b>$short_text</b>], що Ви надіслали, не міститься коректної електронної адреси.\u{1F61E}";
	}
}
// ----------------------------------------------------------------------------------------
if (substr($text, 0, 10) == '/get_lists') {
	$apiKey = TRELLO_API_KEY;
	$token = TRELLO_TOKEN;
	$boardId = getBoardIdByName(TRELLO_BOARD_NAME);	
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
if ($text == '/zvit') {
	$apiKey = TRELLO_API_KEY;
	$token = TRELLO_TOKEN;
	$boardId = getBoardIdByName(TRELLO_BOARD_NAME);
	// --------------------------------------------------------------------------------------------------------------------------------
	// URL для запиту до Telegram API
	$url1 = "https://api.telegram.org/bot".TELEGRAM_BOT_ID."/getChatMemberCount?chat_id=".TELEGRAM_CHAT_ID;
	//Отримати кількість учасників Telegram групи в якій знаходиться створений телеграм-бот, тому що сам список отримати НЕМОЖЛИВО!
	$countMembers = sendCurlRequest($url1, 'GET');
	if ($countMembers['ok']) {
    $message = "Загальна кількість учасників Telegram групи ChatOS_Group = <b>".$countMembers['result']."</b>\n";
	} else {
		$message = "Помилка: ".$countMembers['description']."\n\n";
	}
	// --------------------------------------------------------------------------------------------------------------------------------
	// --------------------------------------------------------------------------------------------------------------------------------
	//Отримати з бази даних список учасників Telegram групи які вказали email для приєднання до дошки у Trello
	$stmt = $pdo->query("SELECT username, trello_username, trello_fullname, trello_id FROM botusers WHERE email IS NOT NULL");
	$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$count_users = count($users);
	$message .= "Приєднані до дошки Trello = <b>$count_users</b>";
	// --------------------------------------------------------------------------------------------------------------------------------
	// --------------------------------------------------------------------------------------------------------------------------------
	// Отримати всі картки з усіх списків (колонок) на дошці, назва якої зазначена у TRELLO_BOARD_NAME.  
	$url2 = "https://api.trello.com/1/boards/$boardId/cards?key=$apiKey&token=$token";
	$cards = sendCurlRequest($url2, 'GET');
	// Отримати idList, idMembers, name  кожного завдання у списку на дошці, назва якої зазначена у TRELLO_BOARD_NAME. 
	$cardsListMemberName = array();
	// Проходимо по кожній картці
	foreach ($cards as $card) {
		// Перевіряємо, чи існує ключ idMembers і чи він не пустий
		if (!empty($card['idMembers'])) {
			for ($i=0; $i < count($card['idMembers']); $i++) { 
				$cardsListMemberName[] = array(
	        "idList" => $card['idList'],//ІД Списку
	        "idMembers" => $card['idMembers'][$i],// ІД Учасники
	        "name" => $card['name']//Назва завдання
	    	);
			}
		}
	}
	// --------------------------------------------------------------------------------------------------------------------------------
	// --------------------------------------------------------------------------------------------------------------------------------
	// Отримати списки (колонки) на дошці, назва якої зазначена у TRELLO_BOARD_NAME. Списки на дошці обов'язково повинні бути встановлені завчасно
	$url3 = "https://api.trello.com/1/boards/$boardId/lists?key=$apiKey&token=$token";
	$lists = sendCurlRequest($url3, 'GET');	
	// Отримати ID і Name кожного списку на дошці, назва якої зазначена у TRELLO_BOARD_NAME. 
	$listIdName =  array();
	foreach ($lists as $list) {
		$listIdName[] = array(
    	'id' => $list['id'],
    	'name' => $list['name']
		);
	}
	// --------------------------------------------------------------------------------------------------------------------------------
	// -----------------Підготовка звіту ----------------
	foreach ($users as $user) { //Перевіряється кожний зареєстрований учасник дошки
		$performer = "\n\n<b>". $user['username'] ."</b> (". $user['trello_fullname'].") = ";
		$content_work = '';
		$i = 0;
		foreach ($cardsListMemberName as $card) {
			if($user['trello_id'] == $card['idMembers']){
				foreach ($listIdName as $list) {
					if($card['idList'] == $list['id']){
						$content_work .= "\n- завдання: '<b>".$card['name']."</b>'\n- список (колонка): '<b>".$list['name']."</b>'\n";						
						$i++;
					}
				}
			}
		}	
		$message .=  $performer.$i.$content_work;
	}
}
// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
if ($text == '/board_events') {
	// Перевірка наявності термінових повідомлень з Trello
	if (file_exists('./trello_log.txt')) {
	    $content = file_get_contents('./trello_log.txt');
	    if (!empty($content)) {
					$message .= "\n\n"."<b>УВАГА! Термінове повідомлення з Trello:</b>\n\n".$content;
	        unlink('./trello_log.txt');
	    }
	} else {
		$message = "Події переміщення карток завдань зі списку (колонки) <b>InProgress</b> до списку (колонки) <b>Done</b> і навпаки - не зафіксовані.\u{1F937}";
	}
}	
// ---------------------------------------
// Відправка повідомлення користувачу
if($message != ''){
	$response = MessageToBot(TELEGRAM_BOT_ID, TELEGRAM_CHAT_ID, $message);
}
// ---------------------------------------
?>
