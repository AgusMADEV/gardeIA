<?php
header('Content-Type: application/json');

function importCSV($csvFile, $db) {
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
       $header = fgetcsv($handle, 1000, ",");
       if(!$header) {
           throw new Exception("CSV sin encabezado.");
       }
       $gatilloIndex   = array_search("Gatillo", $header);
       $preguntaIndex  = array_search("Pregunta", $header);
       if($gatilloIndex === false || $preguntaIndex === false){
          throw new Exception("El CSV debe tener las columnas 'Gatillo' y 'Pregunta'.");
       }
       while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
          if(trim($row[$preguntaIndex]) == '') continue;
          $gatillo  = trim($row[$gatilloIndex]);
          $question = trim($row[$preguntaIndex]);
          $answers = [];
          for($i = 1; $i <= 13; $i++){
             $colIndex = $i + 1;
             $answers[$i] = isset($row[$colIndex]) ? trim($row[$colIndex]) : '';
          }
          $stmt = $db->prepare("
            INSERT INTO faq (
              gatillo, question,
              respuesta1,respuesta2,respuesta3,
              respuesta4,respuesta5,respuesta6,
              respuesta7,respuesta8,respuesta9,
              respuesta10,respuesta11,respuesta12,
              respuesta13
            ) VALUES (
              :gatillo, :question,
              :respuesta1,:respuesta2,:respuesta3,
              :respuesta4,:respuesta5,:respuesta6,
              :respuesta7,:respuesta8,:respuesta9,
              :respuesta10,:respuesta11,:respuesta12,
              :respuesta13
            )");
          $params = [':gatillo'=>$gatillo,':question'=>$question];
          for($i=1;$i<=13;$i++){
            $params[":respuesta$i"] = $answers[$i];
          }
          $stmt->execute($params);
       }
       fclose($handle);
    } else {
      throw new Exception("No se pudo abrir el archivo CSV.");
    }
}

try {
    $dbFile = 'chatbot.db';
    $initDb = !file_exists($dbFile);
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($initDb) {
      $db->exec("
        CREATE TABLE IF NOT EXISTS faq (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          gatillo TEXT NOT NULL,
          question TEXT NOT NULL,
          respuesta1 TEXT, respuesta2 TEXT, respuesta3 TEXT,
          respuesta4 TEXT, respuesta5 TEXT, respuesta6 TEXT,
          respuesta7 TEXT, respuesta8 TEXT, respuesta9 TEXT,
          respuesta10 TEXT, respuesta11 TEXT, respuesta12 TEXT,
          respuesta13 TEXT
        )");
      if (file_exists('faq.csv')) {
          try {
             importCSV('faq.csv', $db);
          } catch (Exception $e) {
             $stmt = $db->prepare("
               INSERT INTO faq (gatillo, question, respuesta1)
               VALUES (:gatillo, :question, :respuesta1)");
             $stmt->execute([
               ':gatillo'   => '¡Hola! ¿En qué te puedo ayudar?',
               ':question'  => 'Seleccione una opción:',
               ':respuesta1'=> 'Opción de ejemplo'
             ]);
          }
      } else {
          $stmt = $db->prepare("
            INSERT INTO faq (gatillo, question, respuesta1)
            VALUES (:gatillo, :question, :respuesta1)");
          $stmt->execute([
            ':gatillo'   => '¡Hola! ¿En qué te puedo ayudar?',
            ':question'  => 'Seleccione una opción:',
            ':respuesta1'=> 'Opción de ejemplo'
          ]);
      }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      if (!empty($_GET['trigger'])) {
          $stmt = $db->prepare("SELECT * FROM faq WHERE gatillo = :trigger LIMIT 1");
          $stmt->execute([':trigger'=>trim($_GET['trigger'])]);
      } else {
          $stmt = $db->query("SELECT * FROM faq ORDER BY id ASC LIMIT 1");
      }
      $node = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($node) {
         $answers = [];
         for ($i = 1; $i <= 13; $i++) {
             $key = 'respuesta' . $i;
             if (!empty(trim($node[$key]))) {
                $answers[] = $node[$key];
             }
         }
         echo json_encode(['question'=>$node['question'],'answers'=>$answers]);
      } else {
         echo json_encode([
           'question'=>'Fin de la conversación. Gracias por usar el chatbot.',
           'answers'=>[]
         ]);
      }
    } else {
         echo json_encode(['error'=>'Método no permitido.']);
    }
} catch (Exception $e) {
    echo json_encode(['error'=>$e->getMessage()]);
}
?>
